<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Handler;

use LaravelExtendedErrors\Renderer\ExceptionHtmlRenderer;
use Monolog\Level;
use Monolog\LogRecord;
use Whoops\Handler\Handler;
use Whoops\Handler\PrettyPageHandler;

class ExceptionPageHandler extends PrettyPageHandler
{
    public function handle(): int
    {
        $exception = $this->getException();
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'Exception',
            Level::Critical,
            $exception->getMessage(),
            method_exists($exception, 'context') ? $exception->context() : []
        );
        $renderer = new ExceptionHtmlRenderer($exception, $record);
        echo $renderer->renderPage();

        return Handler::QUIT;
    }
}
