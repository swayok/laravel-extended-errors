<?php

namespace LaravelExtendedErrors;

use Illuminate\Log\LogManager;

class ExtendedLogManager extends LogManager {

    /**
     * Exceptions (logged usin critical log level)
     *
     * @param \Exception $exception
     * @param array $context
     *
     * @return void
     */
    public function exception(\Exception $exception, array $context = []) {
        $context['exception'] = $exception;
        return $this->critical($exception->getMessage(), $context);
    }

}