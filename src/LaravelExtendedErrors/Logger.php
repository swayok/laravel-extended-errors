<?php


namespace LaravelExtendedErrors;

use \Monolog\Logger as Monolog;

class Logger extends Monolog {

    public function addRecord($level, $message, array $context = array()) {
        if ($message instanceof \Exception) {
            $handler = new ExceptionHandler($this);
            $message = $handler->renderExceptionAsHtml($message);
        }
        return parent::addRecord($level, $message, $context);
    }
}