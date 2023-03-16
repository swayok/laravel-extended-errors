<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Handler;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LaravelExtendedErrors\Utils\TelegramBotApi;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;
use TelegramBot\Api\Types\Message;

class TelegramHandler extends AbstractProcessingHandler
{
    protected TelegramBotApi|BotApi|null $botApi = null;

    public const PARSE_MODE_HTML = 'HTML';
    public const PARSE_MODE_MARKDOWN = 'Markdown';

    static protected bool $ignoreNextException = false;

    public function __construct(
        int|string|Level $level,
        ?string $token = null,
        protected int|string|null $chatId = null,
        bool $bubble = false,
        ?array $proxy = null
    ) {
        if ($token && $this->chatId) {
            $this->initBotApi($token, $proxy);
        }

        parent::__construct($level, $bubble);
    }

    /**
     * @param string     $token
     * @param array|null $proxy
     */
    protected function initBotApi(string $token, ?array $proxy = null): void
    {
        if (empty($proxy) || Arr::get($proxy, 'type') !== 'nginx') {
            $this->botApi = new BotApi($token);
            $this->setupNormalProxy($proxy);
        } else {
            $this->botApi = new TelegramBotApi($token);
            $this->setupNginxProxy($proxy);
        }
    }

    protected function setupNormalProxy(?array $proxy): void
    {
        if (!empty($proxy) && !empty($proxy['host']) && !empty($proxy['port'])) {
            $proxyServer = $proxy['host'] . ':' . $proxy['port'];
            if (empty($proxy['user']) || empty($proxy['password'])) {
                $this->botApi->setProxy($proxyServer);
            } else {
                $this->botApi->setCurlOption(CURLOPT_HTTPPROXYTUNNEL, true);
                $this->botApi->setCurlOption(CURLOPT_PROXY, $proxyServer);
                switch (Arr::get($proxy, 'type')) {
                    case 'socks4':
                        $this->botApi->setCurlOption(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
                        break;
                    case 'socks5':
                        $this->botApi->setCurlOption(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                        break;
                    case 'http':
                    default:
                        $this->botApi->setCurlOption(CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                }
                $this->botApi->setCurlOption(
                    CURLOPT_PROXYUSERPWD,
                    $proxy['user'] . ':' . $proxy['password']
                );
                $this->botApi->setCurlOption(CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }
        }
    }

    protected function setupNginxProxy(?array $proxy): void
    {
        if (!empty($proxy) && !empty($proxy['host']) && str_starts_with($proxy['host'], 'http')) {
            $this->botApi->setApiBaseUrl($proxy['host']);
            if (!empty($proxy['user']) && !empty($proxy['password'])) {
                $this->botApi->setCurlOption(CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                $this->botApi->setCurlOption(
                    CURLOPT_PROXYUSERPWD,
                    $proxy['user'] . ':' . $proxy['password']
                );
            }
        }
    }

    protected function write(LogRecord $record): void
    {
        if (!$this->botApi) {
            return;
        }
        if (static::$ignoreNextException) {
            static::$ignoreNextException = false;
            return;
        }
        try {
            $fileContents = $this->getFormatter()->format($record);
            $filePath = tempnam(sys_get_temp_dir(), 'php');
            register_shutdown_function(static function () use ($filePath) {
                @unlink($filePath);
            });
            file_put_contents($filePath, $fileContents);
            $document = new \CURLFile(
                $filePath,
                'text/html',
                strtolower($record->level->name) . '_message_' . date('Y-m-d_H-i-s') . '.html'
            );
            $messagePrefix = "*{$record->level->name}* @ " . gethostname();
            $message = substr($messagePrefix . ': ' . $record['message'], 0, 200);
            try {
                $this->sendDocument($document, $message);
            } catch (Exception $exception) {
                static::$ignoreNextException = true;
                Log::debug($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
                static::$ignoreNextException = false;
                try {
                    if (stripos($exception->getMessage(), 'strings must be encoded in UTF-8') !== false) {
                        $this->sendDocument($document, $messagePrefix);
                    } else {
                        $this->sendMessage(
                            $messagePrefix . ': There was an error sending exception report. Review file log.'
                        );
                    }
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable $exception) {
            if (!empty($filePath)) {
                @unlink($filePath);
            }
            static::$ignoreNextException = true;
            /** @noinspection PhpStrictTypeCheckingInspection */
            Log::critical($exception);
            static::$ignoreNextException = false;
        }
        if (!empty($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * @throws \TelegramBot\Api\Exception
     * @throws \TelegramBot\Api\HttpException
     * @throws \TelegramBot\Api\InvalidJsonException
     */
    protected function sendDocument(
        \CURLFile $document,
        string $caption = '',
        ?string $parseMode = null,
        bool $disableNotification = false
    ): ?Message {
        if (!$this->botApi) {
            return null;
        }
        return Message::fromResponse(
            $this->botApi->call('sendDocument', [
                'chat_id' => $this->chatId,
                'document' => $document,
                'caption' => $caption,
                'parse_mode' => $parseMode,
                'disable_notification' => $disableNotification,
            ])
        );
    }

    /**
     * @throws \TelegramBot\Api\Exception
     * @throws \TelegramBot\Api\InvalidArgumentException
     */
    protected function sendMessage(
        string $message,
        string $parseMode = null,
        bool $disableNotification = false
    ): ?Message {
        return $this->botApi?->sendMessage(
            $this->chatId,
            $message,
            $parseMode,
            false,
            null,
            null,
            $disableNotification
        );
    }
}
