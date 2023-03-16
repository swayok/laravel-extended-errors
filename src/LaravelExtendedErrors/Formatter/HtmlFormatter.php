<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Formatter;

use LaravelExtendedErrors\Renderer\ExceptionHtmlRenderer;
use LaravelExtendedErrors\Renderer\LogEmailRenderer;
use LaravelExtendedErrors\Renderer\LogHtmlRenderer;
use Monolog\Formatter\HtmlFormatter as MonologHtmlFormatter;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

class HtmlFormatter extends MonologHtmlFormatter
{
    protected bool $renderAsPage = false;

    /**
     * Customize logger to use this formatter
     */
    public function __invoke(LoggerInterface $logger)
    {
        if (method_exists($logger, 'getHandlers')) {
            foreach ($logger->getHandlers() as $handler) {
                if (method_exists($handler, 'setFormatter')) {
                    $handler->setFormatter($this);
                }
            }
        }
    }

    public function format(LogRecord $record): string
    {
        $hasContext = isset($record->context) && is_array($record->context);
        if (
            $hasContext
            && isset($record->context['exception'])
            && $record->context['exception'] instanceof \Throwable
        ) {
            $exception = $record->context['exception'];
            unset($record->context['exception']);
            $renderer = new ExceptionHtmlRenderer($exception, $record);
        } elseif (
            isset($record->context['email_message']['headers'], $record->context['email_message']['body'])
            && $hasContext
            && is_array($record->context['email_message'])
        ) {
            $renderer = new LogEmailRenderer($record);
        } else {
            $renderer = new LogHtmlRenderer($record);
        }
        return $this->renderAsPage ? $renderer->renderPage() : $renderer->renderPageBody();
    }

    /**
     * @param bool $renderAsPage - true: render log record as full html page instead of <body> contents
     */
    public function setRenderAsPage(bool $renderAsPage): static
    {
        $this->renderAsPage = $renderAsPage;
        return $this;
    }
}