<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Utils;

use TelegramBot\Api\BotApi;

class TelegramBotApi extends BotApi
{
    protected ?string $baseUrl = null;

    protected string $messageUrlPath = '/bot';
    protected string $fileUrlPath = '/file/bot';

    public function setApiBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->baseUrl ? $this->baseUrl . $this->messageUrlPath . $this->token : parent::getUrl();
    }

    public function getFileUrl(): string
    {
        return $this->baseUrl ? $this->baseUrl . $this->fileUrlPath . $this->token : parent::getFileUrl();
    }
}