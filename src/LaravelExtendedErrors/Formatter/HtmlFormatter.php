<?php

namespace LaravelExtendedErrors\Formatter;

use LaravelExtendedErrors\Renderer\ExceptionHtmlRenderer;
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

    public function format(array $record) {
        if (
            isset($record['context'])
            && is_array($record['context'])
            && isset($record['context']['exception'])
            && $record['context']['exception'] instanceof \Exception
        ) {
            $exception = $record['context']['exception'];
            unset($record['context']['exception']);
            $renderer = new ExceptionHtmlRenderer($exception, $record);
        } else {
            return parent::format($record);
            //$renderer = new LogHtmlRenderer($record);
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