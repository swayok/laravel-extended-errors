<?php

namespace LaravelExtendedErrors\Formatter;

use LaravelExtendedErrors\Renderer\ExceptionHtmlRenderer;
use LaravelExtendedErrors\Renderer\LogEmailRenderer;
use LaravelExtendedErrors\Renderer\LogHtmlRenderer;
use Monolog\Formatter\HtmlFormatter as MonologHtmlFormatter;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class HtmlFormatter extends MonologHtmlFormatter {

    protected $renderAsPage = false;
    protected $allowJavaScript = true;

    /**
     * Customize logger to use this formatter
     * @param LoggerInterface|Logger $logger
     */
    public function __invoke($logger) {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter($this);
        }
    }

    public function format(array $record): string {
        $hasContext = isset($record['context']) && is_array($record['context']);
        if (
            $hasContext
            && isset($record['context']['exception'])
            && $record['context']['exception'] instanceof \Throwable
        ) {
            $exception = $record['context']['exception'];
            unset($record['context']['exception']);
            $renderer = new ExceptionHtmlRenderer($exception, $record);
        } else if (
            $hasContext
            && isset($record['context']['email_message'])
            && is_array($record['context']['email_message'])
            && isset($record['context']['email_message']['headers'], $record['context']['email_message']['body'])
        ) {
            $renderer = new LogEmailRenderer($record);
        } else {
            $renderer = new LogHtmlRenderer($record);
        }
        return $this->renderAsPage ? $renderer->renderPage() : $renderer->renderPageBody($this->allowJavaScript);
    }

    /**
     * @param bool $renderAsPage - true: render log record as full html page instead of <body> contents
     * @return $this
     */
    public function setRenderAsPage(bool $renderAsPage) {
        $this->renderAsPage = $renderAsPage;
        return $this;
    }
}