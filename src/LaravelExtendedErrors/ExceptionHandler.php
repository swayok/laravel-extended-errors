<?php

namespace LaravelExtendedErrors;

use Exception;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExceptionHandler extends Handler {

    private $request = null;

    public function render($request, \Exception $exc) {
        try {
            $this->request = $request;
            return $this->convertExceptionToResponse($exc);
        } catch (\Exception $exc2) {
            return parent::render($request, $exc2);
        }
    }

    protected function convertExceptionToResponse(\Exception $exc) {
        try {
            if ($exc instanceof HttpException && \Request::ajax()) {
                return new JsonResponse([
                    '_message' => $exc->getMessage(),
                ], $exc->getStatusCode());
            } else {
                return parent::render($this->request, $exc);
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