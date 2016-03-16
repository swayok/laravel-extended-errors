<?php


namespace LaravelExtendedErrors;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionRenderer;
use Symfony\Component\HttpFoundation\Response;

class ExceptionRenderer extends SymfonyExceptionRenderer {

    protected $charset;
    protected $debug;

    protected $colors = [
        'page_bg' => '#FFFFFF',
        'content_bg' => '#F5F5F5',
        'content_border' => '#CCCCCC',
        'project_root' => '#888888',
        'app_file' => '#008d00',
        'vendor_file' => '#8d0389',
        'error_position' => '#FF0000',
        'trace_item_delimiter' => '#CCCCCC',
        'class' => '#0000FF',
        'object' => '#888888',
        'string' => '#bb0044',
        'null' => '#008d00',
        'boolean' => '#008d00',
        'resource' => '#8d0389',
        'json_block_bg' => '#FFFFFF',
        'json_block_border' => '#CCCCCC',
    ];

    public function __construct($debug = true, $charset = null, $fileLinkFormat = null) {
        parent::__construct($debug, $charset, $fileLinkFormat);
        $this->charset = $charset ?: (env('DEFAULT_CHARSET') ?: 'UTF-8');
        $this->debug = !!$debug;
    }

    public function createResponse($exception, $onlyHtmlBodyContent = false) {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }
        return Response::create(
            $this->render($exception, $onlyHtmlBodyContent),
            $exception->getStatusCode(),
            $exception->getHeaders()
        )->setCharset($this->charset);
    }

    /**
     * @param FlattenException $exception
     * @param bool $onlyHtmlBodyContent
     * @return string
     */
    protected function render($exception, $onlyHtmlBodyContent = false) {
        $bodyContent = <<<EOF
            <div class="html-exception-content" style="background-color: {$this->colors['content_bg']};
            padding: 20px 30px 30px 30px; font: 11px Verdana, Arial, sans-serif; margin: 0 auto 40px auto;
            border: 1px solid {$this->colors['content_border']}; width:100%; max-width:900px;">
                {$this->getContent($exception)}
                {$this->getRequestInfo()}
            </div>
EOF;
        if ($onlyHtmlBodyContent) {
            return $bodyContent;
        }
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta charset="{$this->charset}" />
        <meta name="robots" content="noindex,nofollow" />
        <title>Error report: {$exception->getMessage()}</title>
        {$this->getStylesheet($exception)}
    </head>
    <body style="padding: 20px 30px 20px 30px; margin: 0; background-color: {$this->colors['page_bg']};">
        {$bodyContent}
    </body>
</html>
EOF;
    }

    protected function getRequestInfo() {
        if (empty($_SERVER['REQUEST_METHOD']) || !$this->debug) {
            return '';
        }
        $request = request();
        try {
            $url = !empty($_SERVER['REQUEST_URI']) ? $request->url() : 'Probably console command';
        } catch (\UnexpectedValueException $exc) {
            $url = 'Error: ' . $exc->getMessage();
        }
        $title = 'Request Information';
        $content = <<<EOF
            <div class="request-info">
                <hr>
                <h2 style="margin: 20px 0 20px 0; text-align: center; font-weight: bold; font-size: 18px;">
                    <b>{$title}</b><br>
                </h2>
                <h2 style="font-size: 18px;">({$request->getRealMethod()} -> {$request->getMethod()}) $url</h2>
                <br>
                <div style="font-size: 14px !important">

EOF;
        foreach ($this->getAdditionalData() as $label => $data) {
            $content .= '<h2 style="margin: 20px 0 20px 0; font-weight: bold; font-size: 18px;">' . $label . '</h2>';
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $content .= <<<EOF
                <pre style="border: 1px solid {$this->colors['json_block_border']}; background: {$this->colors['json_block_bg']};
                padding: 10px; font-size: 14px !important; word-break: break-all; white-space: pre-wrap;">$json</pre>
EOF;
        }

        return $this->cleanPasswords($content) . '</div></div>';
    }

    protected function getAdditionalData() {
        return [
            '$_GET' => $_GET,
            '$_POST' => $_POST,
            '$_FILES' => $_FILES,
            '$_COOKIE' => class_exists('\Cookie') ? \Cookie::get() : (!empty($_COOKIE) ? $_COOKIE : []),
            '$_SERVER' => array_intersect_key($_SERVER, array_flip([
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
            ]))
        ];
    }

    protected function cleanPasswords($content) {
        return preg_replace(
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
    }

    public function getContent(FlattenException $exception) {
        $content = '';
        $title = 'Exception Report';
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
                    $content .= '       <li style="border-bottom: 1px solid ' . $this->colors['trace_item_delimiter'] . '; padding: 5px 0 9px 0; margin: 0;">';
                    if (isset($trace['file']) && isset($trace['line'])) {
                        $content .= '<p>' . $this->formatPath($trace['file'], $trace['line']) .'</p>';
                    }
                    if ($trace['function']) {
                        $content .= sprintf('<p>at %s<span style="color: ' . $this->colors['error_position'] . '">%s%s</span>( %s )</p>', $this->formatClass($trace['class']), $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
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

                $content .= "    </ol>\n</div>";
            }
        } catch (\Exception $e) {
            // something nasty happened and we cannot throw an exception anymore
            $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $this->escapeHtml($e->getMessage()));
        }

        return <<<EOF
            <h1>$title</h1>
            $content
EOF;
    }

    public function getStylesheet(FlattenException $exception) {
        $styles = <<<EOF
            <style>
                html { padding: 10px }
                img { border: 0; }
            </style>
EOF;
        return $styles;
    }

    protected function escapeHtml($str) {
        return htmlspecialchars($str, ENT_QUOTES | (PHP_VERSION_ID >= 50400 ? ENT_SUBSTITUTE : 0), $this->charset);
    }

    protected function formatClass($class) {
        return sprintf('<span style="color: ' . $this->colors['class'] . '">%s</span>', $class);
    }

    protected function formatPath($path, $line) {
        $path = preg_replace(
            [
                '%(' . preg_quote(app_path()) . '.*)%i',
                '%(' . preg_quote(base_path() . DIRECTORY_SEPARATOR . 'vendor') . '.*)%i',
                '%(' . preg_quote(base_path()) . ')%i',
            ],
            [
                '<span style="color: ' . $this->colors['app_file'] . '; font-weight: bold;">$1</span>',
                '<span style="color: ' . $this->colors['vendor_file'] . '; font-weight: bold;">$1</span>',
                '<span style="color: ' . $this->colors['project_root'] . '; font-weight: normal;">$1</span>',
            ],
            $path
        );
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
                $formattedValue = sprintf('<span style="border-bottom: 1px dotted ' . $this->colors['object'] . ';">%s</span>', $this->formatClass($item[1]));
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('<span>array</span>( %s )', is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('string' === $item[0]) {
                $formattedValue = sprintf("<span style=\"color: {$this->colors['string']};\">'%s'</span>", $this->escapeHtml($item[1]));
            } elseif ('null' === $item[0]) {
                $formattedValue = '<span style="color: ' . $this->colors['null'] . ';">null</span>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<span style="color: ' . $this->colors['boolean'] . ';">' . strtolower(var_export($item[1], true)) . '</span>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<span style="color: ' . $this->colors['resource'] . ';">resource</span>';
            } else {
                $formattedValue = str_replace("\n", '', var_export($this->escapeHtml((string)$item[1]), true));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }
}