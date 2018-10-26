<?php

namespace LaravelExtendedErrors;

use Illuminate\Log\LogManager;

class ExtendedLogManager extends LogManager {

    /**
     * Exceptions (logged using critical log level)
     *
     * @param \Throwable $exception
     * @param array $context
     *
     * @return void
     */
    public function exception(\Throwable $exception, array $context = []) {
        $context['exception'] = $exception;
        return $this->critical($exception->getMessage(), $context);
    }

}