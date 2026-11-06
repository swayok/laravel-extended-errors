<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LaravelExtendedErrors\Utils\TelegramBotApi;
use LaravelExtendedErrors\Utils\TelegramBotApiException;

/**
 * Implements ShouldBeEncrypted because the serialized payload carries the bot token
 * (via $botApi) and the rendered log/exception content, both of which would otherwise
 * sit in plaintext in the queue backend and in failed_jobs until processed.
 */
class SendTelegramLogMessage implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected TelegramBotApi $botApi,
        protected int|string $chatId,
        protected string $fileContents,
        protected string $fileName,
        protected string $caption,
        protected string $fallbackCaption,
        protected string $fallbackMessage
    ) {
    }

    public function handle(): void
    {
        try {
            $this->botApi->sendDocument($this->chatId, $this->fileContents, $this->fileName, $this->caption);
        } catch (TelegramBotApiException $exception) {
            try {
                if (stripos($exception->getMessage(), 'strings must be encoded in UTF-8') !== false) {
                    $this->botApi->sendDocument($this->chatId, $this->fileContents, $this->fileName, $this->fallbackCaption);
                } else {
                    $this->botApi->sendMessage($this->chatId, $this->fallbackMessage);
                }
            } catch (\Throwable) {
            }
        }
    }
}
