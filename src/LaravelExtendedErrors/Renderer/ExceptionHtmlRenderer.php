<?php

namespace LaravelExtendedErrors\Renderer;

use LaravelExtendedErrors\Utils;
use Symfony\Component\Debug\Exception\FlattenException;

class ExceptionHtmlRenderer {

    /**
     * @var FlattenException
     */
    protected $exception;

    /**
     * @var array
     */
    protected $logRecord;

    /**
     * @var string
     */
    protected $charset;

    /**
     * @var bool
     */
    protected $addRequestInfo = true;

    /**
     * @var array
     */
    protected $colors = [
        'page_bg' => '#FFFFFF',
        'content_bg' => '#F5F5F5',
        'content_border' => '#FF0000',
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
        'muted' => '#888888',
    ];

    /**
     * ExceptionHtmlRenderer constructor.
     * @param \Throwable $exception
     * @param array $logRecord
     * @param string|null $charset
     * @param bool $addRequestInfo - true: GET, POST, SERVER, COOKIE data will be added to exception report
     */
    public function __construct(\Throwable $exception, array $logRecord, string $charset = null, bool $addRequestInfo = true) {
        $this->exception = $exception instanceof FlattenException ?: FlattenException::create($exception);
        $this->logRecord = $logRecord;
        $this->charset = $charset ?: 'UTF-8';
        $this->addRequestInfo = $addRequestInfo;
    }

    public function renderPage(): string {
        return <<<HTML
<!DOCTYPE html>
<html>
    <head>
        <meta charset="{$this->charset}" />
        <meta name="robots" content="noindex,nofollow" />
        <title>Error report: {$this->exception->getMessage()}</title>
        {$this->getStylesheet()}
    </head>
    <body style="padding: 20px 30px 20px 30px; margin: 0; background-color: {$this->colors['page_bg']};">
        {$this->renderPageBody(false)}
    </body>
</html>
HTML;
    }

    public function renderPageBody(bool $allowJavaScript = false): string {
        // todo: implement next/prev log navigation using js if alowed
        return <<<HTML
            <div class="html-exception-content" style="background-color: {$this->colors['content_bg']};
            padding: 20px 30px 30px 30px; font: 11px Verdana, Arial, sans-serif; margin: 0 auto 40px auto;
            border: 1px solid {$this->colors['content_border']}; width:100%; max-width:900px;">
                {$this->renderExceptionContent()}
                {$this->renderRequestInfo()}
            </div>
HTML;
    }

    protected function renderRequestInfo(): string {
        if (empty($_SERVER['REQUEST_METHOD']) || !$this->addRequestInfo) {
            return '';
        }
        $request = request();
        try {
            $url = !empty($_SERVER['REQUEST_URI']) ? $request->url() : 'Probably console command';
        } catch (\UnexpectedValueException $exc) {
            $url = 'Error: ' . $exc->getMessage();
        }
        $content = <<<HTML
            <div class="request-info">
                <hr>
                <h2 style="margin: 20px 0 20px 0; text-align: center; font-weight: bold; font-size: 18px;">
                    <b>Request Information</b><br>
                </h2>
                <h2 style="font-size: 18px;">({$request->getRealMethod()} -> {$request->getMethod()}) $url</h2>
                <br>
                <div style="font-size: 14px !important">

HTML;
        foreach (Utils::getMoreInformationAboutRequest() as $label => $data) {
            $content .= '<h2 style="margin: 20px 0 20px 0; font-weight: bold; font-size: 18px;">' . $label . '</h2>';
            foreach ($data as $key => $value) {
                if (!is_array($value)) {
                    $data[$key] = htmlentities($value);
                }
            }
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $content .= <<<HTML
                <pre style="border: 1px solid {$this->colors['json_block_border']}; background: {$this->colors['json_block_bg']};
                padding: 10px; font-size: 14px !important; word-break: break-all; white-space: pre-wrap;">$json</pre>
HTML;
        }

        return $content . '</div></div>';
    }

    public function renderExceptionContent(): string {
        $content = '';
        $title = 'Exception Report';
        $date = ' @ ' . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ')';
        try {
            foreach ($this->exception->toArray() as $position => $e) {
                $content .= sprintf(
                    '<h2>%s</h2><h3>Type: %s</h3>',
                    nl2br($this->escapeHtml($e['message'])),
                    $this->formatClass($e['class'])
                );
                $content .= '<div style="margin-bottom: 50px;"><ol>' . "\n";
                foreach ($e['trace'] as $trace) {
                    $content .= '  <li style="border-bottom: 1px solid ' . $this->colors['trace_item_delimiter'] . '; padding: 5px 0 9px 0; margin: 0;">';
                    if (isset($trace['file'], $trace['line'])) {
                        $content .= '    <p>' . $this->formatPath($trace['file'], (int)$trace['line']) .'</p>';
                    }
                    if ($trace['function']) {
                        $content .= sprintf(
                            '    <p>at %s<span style="color: ' . $this->colors['error_position'] . '">%s%s</span>( %s )</p>',
                            $this->formatClass($trace['class']),
                            $trace['type'],
                            $trace['function'],
                            $this->formatArgs($trace['args'])
                        );
                    }
                    unset($trace['file'], $trace['line'], $trace['short_class'], $trace['namespace']);
                    if (empty($trace['class'])) {
                        unset($trace['class']);
                        if (empty($trace['type'])) {
                            unset($trace['type']);
                        }
                    }
                    $content .= "  </li>\n";
                }

                $content .= '</ol></div>';
            }
        } catch (\Throwable $e) {
            // something nasty happened and we cannot throw an exception anymore
            $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $this->escapeHtml($e->getMessage()));
        }

        return <<<HTML
            <h1>$title <span style="font-size: 13px; color: {$this->colors['muted']}">$date</span></h1>
            $content
HTML;
    }

    protected function getStylesheet(): string {
        return '
            <style>
                html { padding: 10px }
                img { border: 0; }
            </style>
        ';
    }

    protected function escapeHtml($str): string {
        return htmlspecialchars($str, ENT_QUOTES | (PHP_VERSION_ID >= 50400 ? ENT_SUBSTITUTE : 0), $this->charset);
    }

    protected function formatClass($class): string {
        return sprintf('<span style="color: ' . $this->colors['class'] . '">%s</span>', $class);
    }

    protected function formatPath(string $path, int $line): string {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $path = preg_replace(
            [
                '%(' . preg_quote(app_path(), '%') . '.*)%i',
                '%(' . preg_quote(base_path() . DIRECTORY_SEPARATOR . 'vendor', '%') . '.*)%i',
                '%(' . preg_quote(base_path(), '%') . ')%i',
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
     */
    protected function formatArgs(array $args): string {
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