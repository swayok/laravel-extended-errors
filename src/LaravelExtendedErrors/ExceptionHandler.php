<?php

namespace LaravelExtendedErrors;

use Exception;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExceptionHandler extends Handler {

    protected function convertExceptionToResponse(\Exception $exc) {
        try {
            return $this->_convertExceptionToResponse($exc);
        } catch (\Exception $exc2) {
            return parent::convertExceptionToResponse($exc2);
        }
    }

    protected function _convertExceptionToResponse(\Exception $exc) {
        try {
            if ($exc instanceof HttpException && \Request::ajax()) {
                return new JsonResponse([
                    '_message' => $exc->getMessage(),
                ], $exc->getStatusCode());
            } else {
                return (new ExceptionRenderer(config('app.debug')))->createResponse($exc);
            }
        } catch (\Exception $exc2) {
            return parent::convertExceptionToResponse($exc2);
        }
    }

    public function renderExceptionForEmail(Exception $exc) {
        try {
            return (new EmailExceptionRenderer())->createResponse($exc)->getContent();
        } catch (\Exception $exc2) {
            return parent::convertExceptionToResponse($exc2)->getContent();
        }
    }

    protected function shouldntReport(Exception $e) {
        if (preg_match('%xdebug|debug-eval%', $e->getFile())) {
            return true;
        }
        return parent::shouldntReport($e);
    }
}