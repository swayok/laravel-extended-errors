<?php


namespace LaravelExtendedErrors;

use Monolog\Logger as Monolog;

class Logger extends Monolog {

    public function addRecord($level, $message, array $context = array()) {
        if ($message instanceof \Exception) {
            $context['title'] = $message->getMessage();
            $handler = new ExceptionHandler($this);
            $message = $handler->renderExceptionForLogger($message);
        } else {
            $context['title'] = $message;
        }
        return parent::addRecord($level, $message, $context);
    }

}