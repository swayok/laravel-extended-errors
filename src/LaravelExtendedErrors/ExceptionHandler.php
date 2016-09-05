<?php

namespace LaravelExtendedErrors;

use Exception;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionHandler;

class ExceptionHandler extends Handler {

    protected $isDebug;

    public function __construct(LoggerInterface $log) {
        parent::__construct(app());
        $this->isDebug = config('app.debug');
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Auth\AuthenticationException $exception
     * @return \Illuminate\Http\Response
     */
    /*protected function unauthenticated($request) {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest('login');
    }*/

    protected function convertExceptionToResponse(\Exception $exc) {
        try {
            return $this->_convertExceptionToResponse($exc);
        } catch (\Exception $exc2) {
            return parent::convertExceptionToResponse($exc2);
        }
    }

    protected function renderHttpException(HttpException $exc) {
        if (\Request::ajax()) {
            /** @var HttpException $exc */
            return $this->convertHttpExceptionToJsonResponse($exc);
        } else {
            return parent::renderHttpException($exc);
        }
    }

    protected function convertHttpExceptionToJsonResponse(HttpException $exc) {
        $data = json_decode($exc->getMessage(), true);
        if (!is_array($data)) {
            $data = ['_message' => $exc->getMessage()];
        }
        return new JsonResponse($data, $exc->getStatusCode());
    }

    protected function _convertExceptionToResponse(\Exception $exc) {
        try {
            if ($exc instanceof HttpException && \Request::ajax()) {
                return $this->renderHttpException($exc);
            } else {
                if ($this->isDebug || $exc instanceof HttpException) {
                    return (new ExceptionRenderer($this->isDebug))->createResponse($exc);
                } else {
                    // try to render custom error page for HTTP status code = 500
                    // (by default: resources/errors/500.blade.php)
                    return $this->renderHttpException(new HttpException(500, $exc->getMessage(), $exc));
                }
            }
        } catch (\Exception $exc2) {
            return parent::convertExceptionToResponse($exc2);
        }
    }

    public function renderExceptionForLogger(Exception $exc) {
        try {
            return (new ExceptionRenderer(true))->createResponse($exc, true)->getContent();
        } catch (\Exception $exc2) {
            $ret = $this->originalConvertExceptionToResponseWithDebugEnabled($exc)->getContent();
            $ret .= $this->originalConvertExceptionToResponseWithDebugEnabled($exc2)->getContent();
            return $ret;
        }
    }

    protected function originalConvertExceptionToResponseWithDebugEnabled(Exception $exc) {
        // needed for emails and file logs, otherwise it will be useless
        $e = FlattenException::create($exc);
        $handler = new SymfonyExceptionHandler(true);
        $decorated = $this->decorate($handler->getContent($e), $handler->getStylesheet($e));
        return SymfonyResponse::create($decorated, $e->getStatusCode(), $e->getHeaders());
    }

    protected function shouldntReport(Exception $e) {
        if (preg_match('%xdebug|debug-eval%', $e->getFile())) {
            return true;
        }
        return parent::shouldntReport($e);
    }
}