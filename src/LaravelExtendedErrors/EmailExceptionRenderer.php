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
        try {
            $url = !empty($_SERVER['REQUEST_URI']) ? $request->url() : 'Probably console command';
        } catch (\UnexpectedValueException $exc) {
            $url = 'Error: ' . $exc->getMessage();
        }
        $content = sprintf(<<<EOF
            <div class="sf-request-info">
                <h2 style="margin: 20px 0 20px 0; text-align: center; font-weight: bold; font-size: 18px;">
                    <b>%s</b><br>
                </h2>
                <h2 style="font-size: 18px;">(%s -> %s) %s</h2>
                <br>
                <div style="font-size: 14px !important">

EOF
            , 'Request Information', $request->getRealMethod(), $request->getMethod(), $url);

        foreach ($this->getAdditionalData() as $label => $data) {
            $content .= '<h2 style="margin: 20px 0 20px 0; font-weight: bold; font-size: 18px;">' . $label . '</h2>';
            $content .= '<pre style="padding: 10px; border: 1px solid #BBBBBB; font-size: 14px !important; background: #EEEEEE; word-break: break-all; white-space: pre-wrap;">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
        }

        return $this->cleanPasswords($content) . '</div></div>';
    }

    public function getStylesheet(FlattenException $exception) {
        return '';
    }

    public function getContent(FlattenException $exception) {
        $content = '';
        $title = 'Exception happened';
        try {
            foreach ($exception->toArray() as $position => $e) {
                $class = $this->formatClass($e['class']);
                $message = nl2br($this->escapeHtml($e['message']));
                $content .= sprintf(<<<EOF
                    <h2>%s</h2>
                    <h3>Type: %s</h3>
                    <div style="margin-bottom: 50px;">
                        <ol>

EOF
                    , $message, $class, $this->formatPath($e['trace'][0]['file'], $e['trace'][0]['line']));
                foreach ($e['trace'] as $trace) {
                    $content .= '       <li style="border-bottom: 1px solid #DDDDDD; padding: 5px 0 9px 0; margin: 0;">';
                    if (isset($trace['file']) && isset($trace['line'])) {
                        $content .= '<p>' . $this->formatPath($trace['file'], $trace['line']) .'</p>';
                    }
                    if ($trace['function']) {
                        $content .= sprintf('<p>at %s<span style="color: #FF0000">%s</span><span style="color: #FF0000">%s</span>( %s )</p>', $this->formatClass($trace['class']), $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
                    }
                    unset($trace['file'], $trace['line'], $trace['short_class'], $trace['namespace']);
                    if (empty($trace['class'])) {
                        unset($trace['class']);
                        if (empty($trace['type'])) {
                            unset($trace['type']);
                        }
                    }
                    //$content .= '<pre>' . json_encode($trace, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
                    $content .= "</li>\n";
                }

                $content .= "    </ol>\n</div>\n<hr>\n";
            }
        } catch (\Exception $e) {
            // something nasty happened and we cannot throw an exception anymore
            $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $this->escapeHtml($e->getMessage()));
        }

        return <<<EOF
            <div id="sf-resetcontent" class="sf-reset">
                <h1>$title</h1>
                $content
            </div>
EOF;
    }

    protected function escapeHtml($str) {
        return htmlspecialchars($str, ENT_QUOTES | (PHP_VERSION_ID >= 50400 ? ENT_SUBSTITUTE : 0), $this->charset);
    }

    protected function formatClass($class) {
        return sprintf('<span style="color: #0000FF">%s</span>', $class);
    }

    protected function formatPath($path, $line) {
        $path = preg_replace(
            [
                '%(' . preg_quote(app_path()) . '.*)%i',
                '%(' . preg_quote(base_path() . DIRECTORY_SEPARATOR . 'vendor') . '.*)%i',
                '%(' . preg_quote(base_path()) . ')%i',
            ],
            [
                '<span style="color: #008d00; font-weight: bold;">$1</span>',
                '<span style="color: #8d0389; font-weight: bold;">$1</span>',
                '<span style="color: #aaaaaa; font-weight: normal;">$1</span>',
            ],
            $path
        );
        //$path = $this->escapeHtml($path);
        $file = preg_match('#[^/\\\\]*$#', $path, $file) ? $file[0] : $path;
        return sprintf(' in %s line %d</a>', $path, $line);
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    protected function formatArgs(array $args) {
        $result = array();
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $formattedValue = sprintf('<span style="border-bottom: 1px dotted #888888">%s</span>', $this->formatClass($item[1]));
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('<span>array</span>( %s )', is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('string' === $item[0]) {
                $formattedValue = sprintf("<span style=\"color: #bb0044\">'%s'</span>", $this->escapeHtml($item[1]));
            } elseif ('null' === $item[0]) {
                $formattedValue = '<span style="color: #008d00;">null</span>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<span style="color: #008d00;">' . strtolower(var_export($item[1], true)) . '</span>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<span style="color: #8d0389;">resource</span>';
            } else {
                $formattedValue = str_replace("\n", '', var_export($this->escapeHtml((string)$item[1]), true));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }
}