<?php

namespace LaravelExtendedErrors;

use Exception;
use Illuminate\Foundation\Exceptions\Handler;

class ExceptionHandler extends Handler {

    protected function convertExceptionToResponse(\Exception $e) {
        return (new ExceptionRenderer(config('app.debug')))->createResponse($e);
//        return (new EmailExceptionRenderer())->createResponse($e);
    }

    public function renderExceptionForEmail(Exception $exc) {
        return (new EmailExceptionRenderer())->createResponse($exc)->getContent();
    }

    protected function shouldntReport(Exception $e) {
        if (preg_match('%xdebug|debug-eval%', $e->getFile())) {
            return true;
        }
        return parent::shouldntReport($e);
    }
}