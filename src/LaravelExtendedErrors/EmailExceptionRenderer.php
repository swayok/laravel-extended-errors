<?php


namespace LaravelExtendedErrors;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;

class EmailExceptionRenderer extends ExceptionRenderer {

    public function __construct($charset = null) {
        parent::__construct(true, $charset);
    }

    public function createResponse($exception) {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        return Response::create(
            $this->decorate($this->getContent($exception), $this->getRequestInfo()),
            $exception->getStatusCode(),
            $exception->getHeaders()
        )->setCharset($this->charset);
    }

    private function decorate($content, $requestInfo) {
        $content = preg_replace('%</div>\s*</div>\s*$%is', '', $content);
        $requestInfo .= '</div></div>';
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta charset="{$this->charset}" />
        <meta name="robots" content="noindex,nofollow" />
    </head>
    <body style="padding: 20px 30px 20px 30px; margin: 0; background-color: #FFFFFF; font: 11px Verdana, Arial, sans-serif;">
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
                <h2 style="margin: 20px 0 20px 0; text-align: center; font-weight: bold; font-size: 18px;">
                    <b>%s</b><br>
                </h2>
                <h2 style="font-size: 18px;">(%s -> %s) %s</h2>
                <br>
                <div style="font-size: 14px !important">

EOF
            , 'Request Information', $request->getRealMethod(), $request->getMethod(), $request->url());

        foreach ($this->getAdditionalData() as $label => $data) {
            $content .= '<h2 style="margin: 20px 0 20px 0; font-weight: bold; font-size: 18px;">' . $label . '</h2>';
            $content .= '<pre style="padding: 10px; border: 1px solid #BBBBBB; font-size: 14px !important; background: #EEEEEE; word-break: break-all; white-space: pre-wrap;">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
        }

        return $this->cleanPasswords($content) . '</div></div>';
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
                font-size: 14px !important; background: #EEEEEE; word-break: break-all; white-space: pre-wrap;
            }
EOF;
        return $styles;
    }
}