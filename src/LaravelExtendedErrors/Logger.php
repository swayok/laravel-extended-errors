<?php


namespace LaravelExtendedErrors;

use Monolog\Logger as Monolog;

class Logger extends Monolog {

    public function addRecord($level, $message, array $context = array()) {
        if ($message instanceof \Exception || array_get($context, 'exception', null) instanceof \Exception) {
            /** @var \Exception $exception */
            $exception = $message instanceof \Exception ? $message : $context['exception'];
            $context['title'] = $exception->getMessage();
            $handler = new ExceptionHandler($this);
            $message = $handler->renderExceptionForLogger($exception);
        } else {
            $context['title'] = $message;
        }
        return parent::addRecord($level, $message, $context);
    }

}