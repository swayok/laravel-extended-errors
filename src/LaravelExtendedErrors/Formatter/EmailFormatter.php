<?php

namespace LaravelExtendedErrors\Formatter;

class EmailFormatter extends HtmlFormatter {

    protected $renderAsPage = true;
    protected $allowJavaScript = false;
}