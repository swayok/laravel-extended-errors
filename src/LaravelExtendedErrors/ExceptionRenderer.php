<?php


namespace LaravelExtendedErrors;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionRenderer;
use Symfony\Component\HttpFoundation\Response;

class ExceptionRenderer extends SymfonyExceptionRenderer {

    protected $charset;

    public function __construct($debug = true, $charset = null, $fileLinkFormat = null) {
        parent::__construct($debug, $charset, $fileLinkFormat);
        $this->charset = $charset ?: env('DEFAULT_CHARSET') ?: 'UTF-8';
    }

    public function createResponse($exception) {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        return Response::create(
            $this->decorate($this->getContent($exception), $this->getRequestInfo(), $this->getStylesheet($exception)),
            $exception->getStatusCode(),
            $exception->getHeaders()
        )->setCharset($this->charset);
    }

    private function decorate($content, $requestInfo, $css) {
        $content = preg_replace('%</div>\s*</div>\s*$%is', '', $content);
        $requestInfo .= '</div></div>';
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta charset="{$this->charset}" />
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
        <hr>
        $requestInfo
    </body>
</html>
EOF;
    }

    private function getRequestInfo() {
        if (empty($_SERVER['REQUEST_METHOD'])) {
            return '';
        }
        $request = request();
        $content = sprintf(<<<EOF
            <div class="sf-request-info">
                <h2 class="clear_fix sf-request-info-header sf-text-center">
                    <b>%s</b><br>
                </h2>
                <h2>(%s -> %s) %s</h2>
                <br>
                <div style="font-size: 14px !important">

EOF
            , 'Request Information', $request->getRealMethod(), $request->getMethod(), $request->url());

        $content .= '<h2 class="sf-request-info-header">$_GET</h2>';
        $content .= '<pre>' . json_encode($_GET, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
        $content .= '<h2 class="sf-request-info-header">$_POST</h2>';
        $content .= '<pre>' . json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
        $content .= '<h2 class="sf-request-info-header">$_FILES</h2>';
        $content .= '<pre>' . json_encode($_FILES, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
        $content .= '<h2 class="sf-request-info-header">$_COOKIE</h2>';
        $content .= '<pre>' . json_encode(\Cookie::get(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
        $content .= '<h2 class="sf-request-info-header">$_SERVER</h2>';
        $serverInfo = [
            'HTTP_ACCEPT_LANGUAGE',
            'HTTP_ACCEPT_ENCODING',
            'HTTP_REFERER',
            'HTTP_USER_AGENT',
            'HTTP_ACCEPT',
            'HTTP_CONNECTION',
            'HTTP_HOST',
            'REMOTE_PORT',
            'REMOTE_ADDR',
            'REQUEST_URI',
            'REQUEST_METHOD',
            'QUERY_STRING',
            'DOCUMENT_URI',
            'REQUEST_TIME_FLOAT',
            'REQUEST_TIME',
            'argv',
            'argc',
        ];
        $content .= '<pre>' . json_encode(
                array_intersect_key($_SERVER, array_flip($serverInfo)),
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ) . '</pre>';

        $content = preg_replace(
            [
                '%("[^"]*?pass(?:word)?[^"]*"\s*:\s*")[^"]*"%is', //< for $_GET / $_POST
                '%(pass(?:word)?[^=]*?=)[^&^"]*(&|$|")%im' //< for $_SERVER (in http query)
            ],
            [
                '$1*****"',
                '$1*****$2',
            ],
            $content
        );

        return $content . '</div></div>';
    }

    public function getStylesheet(FlattenException $exception) {
        $styles = parent::getStylesheet($exception);
        $styles .= <<<EOF
            .sf-request-info-header {
                margin: 20px 0 20px 0;
            }
            .sf-text-center {
                text-align: center;
            }
            .sf-request-info pre {
                font-size: 14px !important;
                border: 1px solid #CCCCCC;
                background: #EEEEEE;
                padding: 20px;
                word-break: break-word;
            }
EOF;
        return $styles;
    }
}