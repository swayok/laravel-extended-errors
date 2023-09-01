<?php

declare(strict_types=1);

namespace LaravelExtendedErrors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;

/**
 * Unfortunately default LogManager does not handle exceptions correctly
 * when exception passed like Log::error(new \Exception()).
 * It uses $exception->getMessage() and discards exception.
 * In result - log formatters cannot use exception object to render log.
 * This is not convenient and reduces usability of this lib.
 * That's why default logger is replaced by this one where exception instance
 * is added to $context and can be used by renderers to render beautiful logs.
 */
class ExtendedLogManager extends LogManager
{
    public const CONTEXT_EXCEPTION_KEY = 'exception';

    /**
     * @param Application $app
     */
    public function __construct($app)
    {
        parent::__construct($app);
        if ($app->resolved('log')) {
            // Import customCreators from already resolved log manager.
            $logManager = $app->make('log');
            if ($logManager instanceof LogManager) {
                $this->customCreators = $logManager->customCreators;
            }
        }
    }

    /**
     * Exceptions (logged using critical log level)
     *
     * @param \Throwable $exception
     * @param array $context
     *
     * @return void
     * @deprecated Do not use - it is not compatible with PSR.
     */
    public function exception(\Throwable $exception, array $context = []): void
    {
        $this->critical($exception, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|\Throwable $message
     * @param array $context
     *
     * @return void
     */
    public function critical($message, array $context = []): void
    {
        if ($message instanceof \Throwable) {
            $context[static::CONTEXT_EXCEPTION_KEY] = $message;
            $message = $message->getMessage();
        }
        parent::critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|\Throwable $message
     * @param array $context
     *
     * @return void
     */
    public function error($message, array $context = []): void
    {
        if ($message instanceof \Throwable) {
            $context[static::CONTEXT_EXCEPTION_KEY] = $message;
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
     * @param string|\Throwable $message
     * @param array $context
     *
     * @return void
     */
    public function alert($message, array $context = []): void
    {
        if ($message instanceof \Throwable) {
            $context[static::CONTEXT_EXCEPTION_KEY] = $message;
            $message = $message->getMessage();
        }
        parent::alert($message, $context);
    }

    /**
     * System is unusable.
     *
     * @param string|\Throwable $message
     * @param array $context
     *
     * @return void
     */
    public function emergency($message, array $context = []): void
    {
        if ($message instanceof \Throwable) {
            $context[static::CONTEXT_EXCEPTION_KEY] = $message;
            $message = $message->getMessage();
        }
        parent::emergency($message, $context);
    }

}
