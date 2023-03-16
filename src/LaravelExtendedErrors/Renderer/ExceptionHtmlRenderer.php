<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Renderer;

use Illuminate\Support\Facades\Request;
use LaravelExtendedErrors\Utils;
use Monolog\LogRecord;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class ExceptionHtmlRenderer extends LogHtmlRenderer
{
    protected FlattenException $exception;

    static protected ?\Closure $userInfoCollector = null;

    /**
     * ExceptionHtmlRenderer constructor.
     * @param \Throwable  $exception
     * @param LogRecord   $logRecord
     * @param string|null $charset
     * @param bool        $addRequestInfo - true: GET, POST, SERVER, COOKIE data will be added to exception report
     * @param bool        $addUserInfo - true: some user data will be added to exception report (class and primary key value received for Auth::guard()->user()
     */
    public function __construct(
        \Throwable $exception,
        LogRecord $logRecord,
        ?string $charset = null,
        protected bool $addRequestInfo = true,
        protected bool $addUserInfo = true
    ) {
        parent::__construct($logRecord, $charset);
        if ($exception instanceof FlattenException) {
            $this->exception = $exception;
        } else {
            $this->exception = FlattenException::createFromThrowable($exception);
        }
    }

    public static function setUserInfoCollector(?\Closure $closure): void
    {
        static::$userInfoCollector = $closure;
    }

    protected function getPageTitle(): string
    {
        return 'Error report: ' . $this->exception->getMessage();
    }

    public function renderPageBody(): string
    {
        return <<<HTML
            <div
                class="html-exception-content"
                style="background-color: {$this->colors['content_bg']};
                    padding: 20px 30px 30px 30px; font: 11px Verdana, Arial, sans-serif; margin: 0 auto 40px auto;
                    border: 1px solid {$this->getLogLevelColor()}; width:100%; max-width:900px;"
            >
                {$this->renderExceptionContent()}
                {$this->renderContext()}
                {$this->renderUserInfo()}
                {$this->renderRequestInfo()}
            </div>
HTML;
    }

    protected function renderRequestInfo(): string
    {
        if (!$this->addRequestInfo || empty($_SERVER['REQUEST_METHOD'])) {
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
            /** @noinspection JsonEncodingApiUsageInspection */
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $content .= <<<HTML
                <pre style="border: 1px solid {$this->colors['json_block_border']}; background: {$this->colors['json_block_bg']};
                padding: 10px; font-size: 14px !important; word-break: break-all; white-space: pre-wrap;">$json</pre>
HTML;
        }

        return $content . '</div></div>';
    }

    protected function renderUserInfo(): string
    {
        if (!$this->addUserInfo) {
            return '';
        }
        $content = <<<HTML
            <div class="user-info">
                <hr>
                <h2 style="margin: 20px 0 20px 0; text-align: center; font-weight: bold; font-size: 18px;">
                    <b>User Information</b><br>
                </h2>
                <div style="font-size: 14px !important">

HTML;
        try {
            $userInfo = $this->getUserInfo();
            if (!$userInfo) {
                $content .= '<b>Not authenticated</b>';
            } else {
                foreach ($userInfo as &$value) {
                    if (!is_array($value)) {
                        $value = htmlentities($value);
                    }
                }
                unset($value);
                /** @noinspection JsonEncodingApiUsageInspection */
                $json = json_encode($userInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $content .= <<<HTML
                <pre style="border: 1px solid {$this->colors['json_block_border']}; background: {$this->colors['json_block_bg']};
                padding: 10px; font-size: 14px !important; word-break: break-all; white-space: pre-wrap;">$json</pre>
HTML;
            }
        } catch (\Throwable $exception) {
            $content .= '<b>Exception: ' . $exception->getMessage() . '</b>';
            $content .= '<pre style="word-break: break-all; white-space: pre-wrap;">' . $exception->getTraceAsString() . '</pre>';
        }

        return $content . '</div></div>';
    }

    protected function getUserInfo(): ?array
    {
        if (isset(static::$userInfoCollector)) {
            return call_user_func(static::$userInfoCollector);
        }

        $user = Request::user();
        if (!$user) {
            return null;
        }

        return Utils::getUserInfo($user);
    }

    public function renderExceptionContent(): string
    {
        $content = '';
        $title = 'Exception Report';
        $date = ' @ ' . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ')';
        try {
            foreach ($this->exception->toArray() as $e) {
                $content .= sprintf(
                    '<h2>%s</h2><h3>Type: %s</h3>',
                    nl2br($this->escapeHtml($e['message'])),
                    $this->formatClass($e['class'])
                );
                $content .= '<div style="margin-bottom: 50px;"><ol>' . "\n";
                foreach ($e['trace'] as $trace) {
                    $content .= '  <li style="border-bottom: 1px solid ' . $this->colors['trace_item_delimiter'] . '; padding: 5px 0 9px 0; margin: 0;">';
                    if (isset($trace['file'], $trace['line'])) {
                        $content .= '    <p>' . $this->formatPath($trace['file'], (int)$trace['line']) . '</p>';
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
            <h1 style="color: {$this->getLogLevelColor()}">
                $title
                <span style="font-size: 13px; color: {$this->colors['muted']}">$date</span>
            </h1>
            $content
HTML;
    }

    protected function escapeHtml($str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | (PHP_VERSION_ID >= 50400 ? ENT_SUBSTITUTE : 0), $this->charset);
    }

    protected function formatClass($class): string
    {
        return sprintf('<span style="color: ' . $this->colors['class'] . '">%s</span>', $class);
    }

    protected function formatPath(string $path, int $line): string
    {
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
    protected function formatArgs(array $args): string
    {
        $result = [];
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