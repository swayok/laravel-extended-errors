<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Utils;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Telegram Bot API client built on Laravel's HTTP client.
 * Supports only what this package needs: sending text messages and sending
 * a file with a caption, against a configurable Bot API base URL, synchronously
 * or asynchronously.
 */
class TelegramBotApi
{
    protected const DEFAULT_API_BASE_URL = 'https://api.telegram.org';

    protected string $apiBaseUrl = self::DEFAULT_API_BASE_URL;

    protected array $httpOptions = [];

    public function __construct(
        protected string $token
    ) {
    }

    public function setApiBaseUrl(string $baseUrl): static
    {
        $this->apiBaseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    /**
     * @param string $proxy Full proxy URI, e.g. 'http://user:pass@127.0.0.1:8080' or 'socks5://127.0.0.1:1080'
     */
    public function setProxy(string $proxy): static
    {
        $this->httpOptions['proxy'] = $proxy;
        return $this;
    }

    /**
     * @throws TelegramBotApiException
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?string $parseMode = null,
        bool $disableNotification = false
    ): array {
        return $this->processResponse(
            $this->sendRequest('sendMessage', $this->messageFields($chatId, $text, $parseMode, $disableNotification))
        );
    }

    public function sendMessageAsync(
        int|string $chatId,
        string $text,
        ?string $parseMode = null,
        bool $disableNotification = false
    ): PromiseInterface {
        return $this->sendRequestAsync('sendMessage', $this->messageFields($chatId, $text, $parseMode, $disableNotification));
    }

    /**
     * @throws TelegramBotApiException
     */
    public function sendDocument(
        int|string $chatId,
        string $fileContents,
        string $fileName,
        string $caption = '',
        ?string $parseMode = null,
        bool $disableNotification = false
    ): array {
        return $this->processResponse(
            $this->sendRequest(
                'sendDocument',
                $this->documentFields($chatId, $caption, $parseMode, $disableNotification),
                [['name' => 'document', 'contents' => $fileContents, 'filename' => $fileName]]
            )
        );
    }

    public function sendDocumentAsync(
        int|string $chatId,
        string $fileContents,
        string $fileName,
        string $caption = '',
        ?string $parseMode = null,
        bool $disableNotification = false
    ): PromiseInterface {
        return $this->sendRequestAsync(
            'sendDocument',
            $this->documentFields($chatId, $caption, $parseMode, $disableNotification),
            [['name' => 'document', 'contents' => $fileContents, 'filename' => $fileName]]
        );
    }

    protected function messageFields(int|string $chatId, string $text, ?string $parseMode, bool $disableNotification): array
    {
        return [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_notification' => $disableNotification,
        ];
    }

    protected function documentFields(int|string $chatId, string $caption, ?string $parseMode, bool $disableNotification): array
    {
        return [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => $parseMode,
            'disable_notification' => $disableNotification,
        ];
    }

    /**
     * @throws TelegramBotApiException
     */
    protected function sendRequest(string $method, array $fields, array $attachments = []): Response
    {
        try {
            return $this->buildRequest($attachments)->post(
                $this->getMethodUrl($method),
                $this->normalizeFields($fields, (bool) $attachments)
            );
        } catch (ConnectionException $exception) {
            throw new TelegramBotApiException($this->redactToken($exception->getMessage()));
        }
    }

    /**
     * Unlike the synchronous client, Laravel's async HTTP client does not reject the promise
     * on a transport failure by default — it resolves it with the Throwable as the value
     * (so e.g. Http::pool() callers can inspect results without a try/catch per request).
     * Both that case and a genuine promise rejection are normalized into the same exception here.
     */
    protected function sendRequestAsync(string $method, array $fields, array $attachments = []): PromiseInterface
    {
        return $this->buildRequest($attachments)
            ->async()
            ->post($this->getMethodUrl($method), $this->normalizeFields($fields, (bool) $attachments))
            ->then(
                fn (Response|\Throwable $response) => $this->resolveAsyncResponse($response),
                fn (\Throwable $exception) => $this->resolveAsyncResponse($exception)
            );
    }

    /**
     * @throws TelegramBotApiException
     */
    protected function resolveAsyncResponse(Response|\Throwable $response): array
    {
        if ($response instanceof \Throwable) {
            throw new TelegramBotApiException($this->redactToken($response->getMessage()));
        }
        return $this->processResponse($response);
    }

    protected function buildRequest(array $attachments): PendingRequest
    {
        $request = Http::withOptions($this->httpOptions);
        foreach ($attachments as $attachment) {
            $request = $request->attach($attachment['name'], $attachment['contents'], $attachment['filename']);
        }
        return $attachments ? $request : $request->asForm();
    }

    /**
     * Multipart fields must be strings, unlike form_params which Guzzle coerces itself.
     */
    protected function normalizeFields(array $fields, bool $multipart): array
    {
        $fields = array_filter($fields, static fn (mixed $value): bool => $value !== null);
        if (!$multipart) {
            return $fields;
        }
        return array_map(
            static fn (mixed $value): string => is_bool($value) ? (string) (int) $value : (string) $value,
            $fields
        );
    }

    /**
     * @throws TelegramBotApiException
     */
    protected function processResponse(Response $response): array
    {
        $decoded = $response->json();
        if (!is_array($decoded) || empty($decoded['ok'])) {
            throw new TelegramBotApiException(
                is_array($decoded) ? (string) ($decoded['description'] ?? 'Unknown Telegram API error') : 'Invalid Telegram API response'
            );
        }

        return $decoded['result'] ?? [];
    }

    protected function getMethodUrl(string $method): string
    {
        return $this->apiBaseUrl . '/bot' . $this->token . '/' . $method;
    }

    /**
     * Transport exceptions embed the full request URI (which contains the bot token)
     * in their message, so it must be stripped before the message leaves this class.
     */
    protected function redactToken(string $message): string
    {
        return str_replace($this->token, '***', $message);
    }
}
