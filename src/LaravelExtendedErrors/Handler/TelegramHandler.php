<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Handler;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LaravelExtendedErrors\Jobs\SendTelegramLogMessage;
use LaravelExtendedErrors\Utils\TelegramBotApi;
use LaravelExtendedErrors\Utils\TelegramBotApiException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class TelegramHandler extends AbstractProcessingHandler
{
    protected ?TelegramBotApi $botApi = null;

    public const PARSE_MODE_HTML = 'HTML';
    public const PARSE_MODE_MARKDOWN = 'Markdown';

    /**
     * Send synchronously and wait for the result in the current request (default).
     */
    public const ASYNC_OFF = false;
    /**
     * Send asynchronously, deferring the wait (and any fallback handling) to a shutdown function,
     * so the rest of the request isn't blocked on the Telegram API round trip.
     */
    public const ASYNC_WAIT = 'async-wait';
    /**
     * Dispatch a queued job to send the message, fully decoupling it from the current request.
     * Requires a queue worker to be running.
     */
    public const ASYNC_QUEUE = 'queue';

    protected static bool $ignoreNextException = false;

    public function __construct(
        int|string|Level $level,
        ?string $token = null,
        protected int|string|null $chatId = null,
        protected ?string $messagePrefix = null,
        bool $bubble = false,
        ?array $proxy = null,
        protected string|false $async = self::ASYNC_OFF,
        protected ?string $asyncQueue = null
    ) {
        if (!in_array($this->async, [self::ASYNC_OFF, self::ASYNC_WAIT, self::ASYNC_QUEUE], true)) {
            throw new \InvalidArgumentException(
                'Invalid "async" option for telegram log channel: ' . var_export($this->async, true)
                . '. Expected false, "' . self::ASYNC_WAIT . '" or "' . self::ASYNC_QUEUE . '".'
            );
        }

        if ($token && $this->chatId) {
            $this->initBotApi($token, $proxy);
        }

        parent::__construct($level, $bubble);
    }

    /**
     * @param string $token
     * @param array|null $proxy
     */
    protected function initBotApi(string $token, ?array $proxy = null): void
    {
        $this->botApi = new TelegramBotApi($token);
        if (!empty($proxy) && Arr::get($proxy, 'type') === 'nginx') {
            $this->setupNginxProxy($proxy);
        } else {
            $this->setupNormalProxy($proxy);
        }
    }

    protected function setupNormalProxy(?array $proxy): void
    {
        if (empty($proxy) || empty($proxy['host']) || empty($proxy['port'])) {
            return;
        }
        $scheme = match (Arr::get($proxy, 'type')) {
            'socks4' => 'socks4',
            'socks5' => 'socks5',
            default => 'http',
        };
        $auth = '';
        if (!empty($proxy['user']) && !empty($proxy['password'])) {
            $auth = rawurlencode($proxy['user']) . ':' . rawurlencode($proxy['password']) . '@';
        }
        $this->botApi->setProxy($scheme . '://' . $auth . $proxy['host'] . ':' . $proxy['port']);
    }

    protected function setupNginxProxy(array $proxy): void
    {
        if (empty($proxy['host']) || !str_starts_with($proxy['host'], 'http')) {
            return;
        }
        $baseUrl = $proxy['host'];
        if (!empty($proxy['user']) && !empty($proxy['password'])) {
            $baseUrl = preg_replace(
                '#^(https?://)#',
                '$1' . rawurlencode($proxy['user']) . ':' . rawurlencode($proxy['password']) . '@',
                $baseUrl
            );
        }
        $this->botApi->setApiBaseUrl($baseUrl);
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
            $fileContents = (string) $this->getFormatter()->format($record);
            $fileName = strtolower($record->level->name) . '_message_' . date('Y-m-d_H-i-s') . '.html';
            $messagePrefix = "*{$record->level->name}* @ " . gethostname();
            $caption = $this->modifyMessage(substr($messagePrefix . ': ' . $record['message'], 0, 200));
            $fallbackCaption = $this->modifyMessage($messagePrefix);
            $fallbackMessage = $this->modifyMessage(
                $messagePrefix . ': There was an error sending exception report. Review file log.'
            );

            match ($this->async) {
                self::ASYNC_QUEUE => $this->dispatchToQueue($fileContents, $fileName, $caption, $fallbackCaption, $fallbackMessage),
                self::ASYNC_WAIT => $this->sendAsyncAndWaitOnShutdown($fileContents, $fileName, $caption, $fallbackCaption, $fallbackMessage),
                default => $this->sendSync($fileContents, $fileName, $caption, $fallbackCaption, $fallbackMessage),
            };
        } catch (\Throwable $exception) {
            static::$ignoreNextException = true;
            Log::critical($exception);
            static::$ignoreNextException = false;
        }
    }

    protected function sendSync(
        string $fileContents,
        string $fileName,
        string $caption,
        string $fallbackCaption,
        string $fallbackMessage
    ): void {
        try {
            $this->botApi->sendDocument($this->chatId, $fileContents, $fileName, $caption);
        } catch (TelegramBotApiException $exception) {
            static::$ignoreNextException = true;
            Log::debug($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            static::$ignoreNextException = false;
            $this->sendFallback($exception, $fileContents, $fileName, $fallbackCaption, $fallbackMessage);
        }
    }

    protected function sendAsyncAndWaitOnShutdown(
        string $fileContents,
        string $fileName,
        string $caption,
        string $fallbackCaption,
        string $fallbackMessage
    ): void {
        $promise = $this->botApi->sendDocumentAsync($this->chatId, $fileContents, $fileName, $caption);
        register_shutdown_function(function () use ($promise, $fileContents, $fileName, $fallbackCaption, $fallbackMessage) {
            try {
                $promise->wait();
            } catch (TelegramBotApiException $exception) {
                static::$ignoreNextException = true;
                Log::debug($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
                static::$ignoreNextException = false;
                $this->sendFallback($exception, $fileContents, $fileName, $fallbackCaption, $fallbackMessage);
            } catch (\Throwable) {
            }
        });
    }

    protected function dispatchToQueue(
        string $fileContents,
        string $fileName,
        string $caption,
        string $fallbackCaption,
        string $fallbackMessage
    ): void {
        SendTelegramLogMessage::dispatch(
            $this->botApi,
            $this->chatId,
            $fileContents,
            $fileName,
            $caption,
            $fallbackCaption,
            $fallbackMessage
        )->onQueue($this->asyncQueue);
    }

    protected function sendFallback(
        TelegramBotApiException $exception,
        string $fileContents,
        string $fileName,
        string $fallbackCaption,
        string $fallbackMessage
    ): void {
        try {
            if (stripos($exception->getMessage(), 'strings must be encoded in UTF-8') !== false) {
                $this->botApi->sendDocument($this->chatId, $fileContents, $fileName, $fallbackCaption);
            } else {
                $this->botApi->sendMessage($this->chatId, $fallbackMessage);
            }
        } catch (\Throwable) {
        }
    }

    protected function modifyMessage(string $text): string
    {
        if (empty($this->messagePrefix)) {
            return $text;
        }
        return "[{$this->messagePrefix}] {$text}";
    }
}
