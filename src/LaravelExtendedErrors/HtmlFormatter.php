<?php


namespace LaravelExtendedErrors;

use Monolog\Formatter\HtmlFormatter as MonologHtmlFormatter;

class HtmlFormatter extends MonologHtmlFormatter {

    public function format(array $record) {
        if (preg_match('%html-exception-content%is', $record['message'])) {
            return $record['message'];
        }
        return parent::format($record);
    }
}