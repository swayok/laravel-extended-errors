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

    /**
     * @param int $level
     * @param string $token
     * @param int $chatId
     * @param bool $bubble
     */
    public function __construct(int $level, string $token = null, int $chatId = null, bool $bubble = false) {
        if ($token && $chatId) {
            $this->chatId = $chatId;
            $this->initBotApi($token);
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
     * @param array $record
     * @return Boolean true means that this handler handled the record, and that bubbling is not permitted.
     *         false means the record was either not processed or that this handler allows bubbling.
     * @throws Exception
     * @throws \TelegramBot\Api\InvalidArgumentException
     */
    public function handle(array $record): bool {
        if (!$this->botApi) {
            return false;
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
            $message = "*{$this->levels[$record['level']]}* @ " . gethostname() . ": {$record['message']}";
            $this->sendDocument($document, $message);
        } catch (Exception $exception) {
            $success = false;
            $this->sendMessage('There was an error sending exception report. Review file log.');
        } catch (\Exception $exception) {
            if (!empty($filePath)) {
                @unlink($filePath);
            }
            throw new $exception;
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