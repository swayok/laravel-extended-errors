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
        $this->critical($exception, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param  string  $message
     * @param  array  $context
     *
     * @return void
     */
    public function critical($message, array $context = []): void {
        if ($message instanceof \Throwable) {
            $context['exception'] = $message;
            $message = $message->getMessage();
        }
        parent::critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param  string  $message
     * @param  array  $context
     *
     * @return void
     */
    public function error($message, array $context = []): void {
        if ($message instanceof \Throwable) {
            $context['exception'] = $message;
            $message = $message->getMessage();
        }
        parent::error($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param  string  $message
     * @param  array  $context
     *
     * @return void
     */
    public function alert($message, array $context = []): void {
        if ($message instanceof \Throwable) {
            $context['exception'] = $message;
            $message = $message->getMessage();
        }
        parent::alert($message, $context);
    }

    /**
     * System is unusable.
     *
     * @param  string  $message
     * @param  array  $context
     *
     * @return void
     */
    public function emergency($message, array $context = []): void {
        if ($message instanceof \Throwable) {
            $context['exception'] = $message;
            $message = $message->getMessage();
        }
        parent::emergency($message, $context);
    }

}
