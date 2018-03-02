<?php

namespace LaravelExtendedErrors\Handler;

use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;

class TelegramHandler extends AbstractHandler {

    private $token;
    private $chatId;

    private $levels = [
        Logger::DEBUG => 'Debug',
        Logger::INFO => '‍Info',
        Logger::NOTICE => 'Notice',
        Logger::WARNING => 'Warning',
        Logger::ERROR => 'Error',
        Logger::CRITICAL => 'Critical',
        Logger::ALERT => 'Alert',
        Logger::EMERGENCY => 'Emergency',
    ];

    public function __construct(int $level, string $token, int $chatId, bool $bubble = false) {
        $this->token = $token;
        $this->chatId = $chatId;

        parent::__construct($level, $bubble);
    }

    /**
     * @param array $record
     * @return Boolean true means that this handler handled the record, and that bubbling is not permitted.
     *         false means the record was either not processed or that this handler allows bubbling.
     * @throws Exception
     * @throws \TelegramBot\Api\InvalidArgumentException
     */
    public function handle(array $record): bool {
        if (empty($this->chatId) || empty($this->token)) {
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
            $document = new \CURLFile($filePath, 'text/html', 'message_from_server_' . date('Y-m-d_H-i-s') . '.html');
            $message = gethostname() . " / <b>{$this->levels[$record['level']]}</b>: {$record['message']}";
            $this->telegram()->sendDocument($this->chatId, $document, $message);
        } catch (Exception $exception) {
            $success = false;
            $this->telegram()->sendMessage($this->chatId, 'There was an error sending exception report. Review file log.');
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

    private function telegram(): BotApi {
        return new BotApi($this->token);
    }
}