<?php

namespace LaravelExtendedErrors\Utils;

use TelegramBot\Api\BotApi;

class TelegramBotApi extends BotApi {

    /** @var string */
    protected $baseUrl;

    protected $messageUrlPath = '/bot';
    protected $fileUrlPath = '/file/bot';

    public function setApiBaseUrl(string $baseUrl) {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl() {
        return $this->baseUrl ? $this->baseUrl . $this->messageUrlPath . $this->token: parent::getUrl();
    }

    /**
     * @return string
     */
    public function getFileUrl() {
        return $this->baseUrl ? $this->baseUrl . $this->fileUrlPath . $this->token: parent::getFileUrl();
    }
}