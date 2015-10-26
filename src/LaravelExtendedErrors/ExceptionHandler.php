<?php

namespace LaravelExtendedErrors;

use Exception;
use Illuminate\Foundation\Exceptions\Handler;

class ExceptionHandler extends Handler {

    protected function convertExceptionToResponse(\Exception $e) {
        return (new ExceptionRenderer(config('app.debug')))->createResponse($e);
    }

    public function renderExceptionAsHtml(Exception $exc) {
        return (new ExceptionRenderer(true))->createResponse($exc)->getContent();
    }
}