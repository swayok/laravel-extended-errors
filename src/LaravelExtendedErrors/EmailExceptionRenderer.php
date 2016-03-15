<?php


namespace LaravelExtendedErrors;

use Symfony\Component\Debug\Exception\FlattenException;

class EmailExceptionRenderer extends ExceptionRenderer {

    public function __construct($charset = null) {
        parent::__construct(true, $charset);
    }

    public function getStylesheet(FlattenException $exception) {
        return '';
    }

}