<?php

namespace LaravelExtendedErrors\Handler;

use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;
use TelegramBot\Api\Types\Message;

class TelegramHandler extends AbstractHandler {

    /**
     * @var int|null
     */
    protected $chatId;

    /**
     * @var BotApi|null
     */
    protected $botApi;

    protected $levels = [
        Logger::DEBUG => 'Debug',
        Logger::INFO => '‍Info',
        Logger::NOTICE => 'Notice',
        Logger::WARNING => 'Warning',
        Logger::ERROR => 'Error',
        Logger::CRITICAL => 'Critical',
        Logger::ALERT => 'Alert',
        Logger::EMERGENCY => 'Emergency',
    ];

    public const PARSE_MODE_HTML = 'HTML';
    public const PARSE_MODE_MARKDOWN = 'Markdown';

    static protected $ignoreNextExceptcion = false;

    /**
     * @param int $level
     * @param string|null $token
     * @param int|null $chatId
     * @param bool $bubble
     * @param array|null $proxy
     */
    public function __construct(int $level, ?string $token = null, ?int $chatId = null, bool $bubble = false, ?array $proxy = null) {
        if ($token && $chatId) {
            $this->chatId = $chatId;
            $this->initBotApi($token);
            $this->setupProxy($proxy);
        }

        parent::__construct($level, $bubble);
    }

    /**
     * @param string $token
     * @return $this
     */
    protected function initBotApi(string $token) {
        $this->botApi = new BotApi($token);
        return $this;
    }

    /**
     * @param array|null $proxy
     * @return $this
     */
    protected function setupProxy(?array $proxy) {
        if (!empty($proxy) && !empty($proxy['host']) && !empty($proxy['port'])) {
            $proxyServer = $proxy['host'] . ':' . $proxy['port'];
            if (empty($proxy['user']) || empty($proxy['password'])) {
                $this->botApi->setProxy($proxyServer);
            } else {
                $this->botApi->setCurlOption(CURLOPT_HTTPPROXYTUNNEL, true);
                $this->botApi->setCurlOption(CURLOPT_PROXY, $proxyServer);
                switch (array_get($proxy, 'type')) {
                    case 'socks4':
                        $this->botApi->setCurlOption(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
                        break;
                    case 'socks5':
                        $this->botApi->setCurlOption(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
                        break;
                    case 'http':
                    default:
                        $this->botApi->setCurlOption(CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                }
                $this->botApi->setCurlOption(CURLOPT_PROXYUSERPWD, $proxy['user'] . ':' . $proxy['password']);
                $this->botApi->setCurlOption(CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }
        }
        return $this;
    }

    /**
     * @param array $record
     * @return Boolean true means that this handler handled the record, and that bubbling is not permitted.
     *         false means the record was either not processed or that this handler allows bubbling.
     */
    public function handle(array $record): bool {
        if (!$this->botApi) {
            return false;
        }
        if (static::$ignoreNextExceptcion) {
            static::$ignoreNextExceptcion = false;
            return true;
        }
        $success = true;
        try {
            $fileContents = $this->getFormatter()->format($record);
            $filePath = tempnam(sys_get_temp_dir(), 'php');
            register_shutdown_function(function () use ($filePath) {
                @unlink($filePath);
            });
            file_put_contents($filePath, $fileContents);
            $document = new \CURLFile(
                $filePath,
                'text/html',
                strtolower($this->levels[$record['level']]) . '_message_' . date('Y-m-d_H-i-s') . '.html'
            );
            $message = substr("*{$this->levels[$record['level']]}* @ " . gethostname() . ': ' . $record['message'], 0, 200);
            $this->sendDocument($document, $message);
        } catch (Exception $exception) {
            $success = false;
            static::$ignoreNextExceptcion = true;
            \Log::debug($exception->getMessage());
            static::$ignoreNextExceptcion = false;
            try {
                $this->sendMessage('There was an error sending exception report. Review file log.');
            } catch (\Exception $exception) {
            }
        } catch (\Throwable $exception) {
            if (!empty($filePath)) {
                @unlink($filePath);
            }
            static::$ignoreNextExceptcion = true;
            \Log::critical($exception);
            static::$ignoreNextExceptcion = false;
        }
        if (!empty($filePath)) {
            @unlink($filePath);
        }
        return $success ? !$this->getBubble() : false;
    }

    /**
     * @param \CURLFile $document
     * @param string $caption
     * @param string|null $parseMode
     * @param bool $disableNotification
     * @return null|Message
     * @throws Exception
     * @throws \TelegramBot\Api\HttpException
     * @throws \TelegramBot\Api\InvalidJsonException
     */
    protected function sendDocument(\CURLFile $document, string $caption = '', string $parseMode = null, bool $disableNotification = false): ?Message {
        if (!$this->botApi) {
            return null;
        }
        return Message::fromResponse($this->botApi->call('sendDocument', [
            'chat_id' => $this->chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => $parseMode,
            'disable_notification' => $disableNotification,
        ]));
    }

    /**
     * @param string $message
     * @param string|null $parseMode
     * @param bool $disableNotification
     * @return null|Message
     * @throws Exception
     * @throws \TelegramBot\Api\InvalidArgumentException
     */
    protected function sendMessage(string $message, string $parseMode = null, bool $disableNotification = false): ?Message {
        if (!$this->botApi) {
            return null;
        }
        return $this->botApi->sendMessage($this->chatId, $message, $parseMode, false, null, null, $disableNotification);
    }
}