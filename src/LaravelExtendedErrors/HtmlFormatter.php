<?php


namespace LaravelExtendedErrors;

use Monolog\Formatter\HtmlFormatter as MonologHtmlFormatter;

class HtmlFormatter extends MonologHtmlFormatter {

    public function format(array $record) {
        if (preg_match('%<html>%is', $record['message'])) {
            return $record['message'];
        }
        return parent::format($record);
    }
}