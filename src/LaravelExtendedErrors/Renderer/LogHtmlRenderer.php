<?php

namespace LaravelExtendedErrors\Renderer;

use Illuminate\Support\Arr;
use Monolog\Logger;

class LogHtmlRenderer {

    /**
     * @var array
     */
    protected $logLevelsNames = array(
        Logger::DEBUG     => 'Debug Log',
        Logger::INFO      => 'Information',
        Logger::NOTICE    => 'Notice',
        Logger::WARNING   => 'Warning Log',
        Logger::ERROR     => 'Error Log',
        Logger::CRITICAL  => 'Critical Error Log',
        Logger::ALERT     => 'Alert Log',
        Logger::EMERGENCY => 'Emergency Log',
    );

    /**
     * @var array
     */
    protected $logLevelsColors = array(
        Logger::DEBUG     => '#cccccc',
        Logger::INFO      => '#468847',
        Logger::NOTICE    => '#3a87ad',
        Logger::WARNING   => '#E78B00',
        Logger::ERROR     => '#c12a19',
        Logger::CRITICAL  => '#DC3961',
        Logger::ALERT     => '#D7046F',
        Logger::EMERGENCY => '#ff361c',
    );

    /**
     * @var array
     */
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
        'muted' => '#888888',
    ];

    /**
     * @var array
     */
    protected $logRecord;

    /**
     * @var string
     */
    protected $charset;

    /**
     * @var int
     */
    protected $logLevel;

    public function __construct(array $logRecord, string $charset = null) {
        $this->logRecord = $logRecord;
        $this->charset = $charset ?: 'UTF-8';
        $this->logLevel = Arr::get($logRecord, 'level', Logger::ERROR);
    }

    public function renderPage(): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="{$this->charset}" />
        <meta name="robots" content="noindex,nofollow" />
        <title>{$this->getPageTitle()}</title>
        {$this->getStylesheet()}
    </head>
    <body style="padding: 20px 30px 20px 30px; margin: 0; background-color: {$this->colors['page_bg']};">
        {$this->renderPageBody(false)}
    </body>
</html>
HTML;
    }
    
    protected function getPageTitle(): string {
        return $this->logLevelsNames[$this->logLevel] . ': ' . $this->getMessage();
    }

    public function renderPageBody(bool $allowJavaScript = false): string {
        $date = ' @ ' . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ')';
        return <<<HTML
        <div
            class="html-log-content"
            style="background-color: {$this->colors['content_bg']};
                padding: 20px 30px 30px 30px; font: 11px Verdana, Arial, sans-serif; margin: 0 auto 40px auto;
                border: 1px solid {$this->logLevelsColors[$this->logLevel]}; width:100%; max-width:900px;"
        >
            <h1 style="color: {$this->logLevelsColors[$this->logLevel]}">
                {$this->logLevelsNames[$this->logLevel]}
                <span style="font-size: 13px; color: {$this->colors['muted']}">{$date}</span>
            </h1>
            <h2>{$this->getMessage()}</h2>
            <h3>Channel: {$this->getChannel()}</h3>
            {$this->renderContext()}
        </div>
HTML;
    }

    protected function getMessage(): string {
        return Arr::get($this->logRecord, 'message', '*Empty message*');
    }

    protected function getChannel(): string {
        return Arr::get($this->logRecord, 'channel', '*Channel not provided*');
    }

    protected function getStylesheet(): string {
        return '
            <style>
                html { padding: 10px }
                img { border: 0; }
            </style>
        ';
    }

    protected function renderContext(): string {
        $context = Arr::get($this->logRecord, 'context');
        if (empty($context)) {
            return '';
        }
        $content = <<<HTML
            <div class="request-info">
                <hr>
                <h2 style="margin: 20px 0 20px 0; text-align: center; font-weight: bold; font-size: 18px;">
                    <b>Context</b><br>
                </h2>
                <div style="font-size: 14px !important">
HTML;
        foreach ((array)$context as $label => $data) {
            $content .= '<h2 style="margin: 20px 0 20px 0; font-weight: bold; font-size: 18px;">' . $label . '</h2>';
            if (!is_array($data)) {
                $data = [$data];
            }
            foreach ($data as $key => $value) {
                if (!is_array($value)) {
                    $data[$key] = htmlentities($value);
                }
            }
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $content .= <<<EOF
                    <pre style="border: 1px solid {$this->colors['json_block_border']}; background: {$this->colors['json_block_bg']};
                    padding: 10px; font-size: 14px !important; word-break: break-all; white-space: pre-wrap;">$json</pre>
EOF;
        }
        return $content . "\n               </div>\n            </div>";
    }

}