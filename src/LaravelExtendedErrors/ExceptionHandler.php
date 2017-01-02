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

    /**
     * @param Exception $exc
     * @return JsonResponse|SymfonyResponse
     */
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

    protected function decorate($content, $css) {
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta name="robots" content="noindex,nofollow" />
        <style>
            /* Copyright (c) 2010, Yahoo! Inc. All rights reserved. Code licensed under the BSD License: http://developer.yahoo.com/yui/license.html */
            html{color:#000;background:#FFF;}body,div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,code,form,fieldset,legend,input,textarea,p,blockquote,th,td{margin:0;padding:0;}table{border-collapse:collapse;border-spacing:0;}fieldset,img{border:0;}address,caption,cite,code,dfn,em,strong,th,var{font-style:normal;font-weight:normal;}li{list-style:none;}caption,th{text-align:left;}h1,h2,h3,h4,h5,h6{font-size:100%;font-weight:normal;}q:before,q:after{content:'';}abbr,acronym{border:0;font-variant:normal;}sup{vertical-align:text-top;}sub{vertical-align:text-bottom;}input,textarea,select{font-family:inherit;font-size:inherit;font-weight:inherit;}input,textarea,select{*font-size:100%;}legend{color:#000;}
            html { background: #eee; padding: 10px }
            img { border: 0; }
            #sf-resetcontent { width:970px; margin:0 auto; }
            $css
        </style>
    </head>
    <body>
        $content
    </body>
</html>
EOF;
    }

    protected function shouldntReport(Exception $e) {
        if (preg_match('%xdebug|debug-eval%', $e->getFile())) {
            return true;
        }
        return parent::shouldntReport($e);
    }
}