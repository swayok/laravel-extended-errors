<?php

declare(strict_types=1);

namespace LaravelExtendedErrors\Renderer;

use Illuminate\Support\Arr;
use Monolog\Level;
use Monolog\LogRecord;

class LogHtmlRenderer
{
    protected array $colors = [
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

    protected string $charset;

    public function __construct(
        protected LogRecord $logRecord,
        ?string $charset = null
    ) {
        $this->charset = $charset ?: 'UTF-8';
    }

    public function renderPage(): string
    {
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
        {$this->renderPageBody()}
    </body>
</html>
HTML;
    }

    protected function getPageTitle(): string
    {
        return $this->getLogLevelTitle() . ': ' . $this->getMessage();
    }

    public function renderPageBody(): string
    {
        $date = ' @ ' . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ')';
        return <<<HTML
        <div
            class="html-log-content"
            style="background-color: {$this->colors['content_bg']};
                padding: 20px 30px 30px 30px; font: 11px Verdana, Arial, sans-serif; margin: 0 auto 40px auto;
                border: 1px solid {$this->getLogLevelColor()}; width:100%; max-width:900px;"
        >
            <h1 style="color: {$this->getLogLevelColor()}">
                {$this->getLogLevelTitle()}
                <span style="font-size: 13px; color: {$this->colors['muted']}">{$date}</span>
            </h1>
            <h2>{$this->getMessage()}</h2>
            <h3>Channel: {$this->getChannel()}</h3>
            {$this->renderContext()}
        </div>
HTML;
    }

    protected function getMessage(): string
    {
        return Arr::get($this->logRecord, 'message', '*Empty message*');
    }

    protected function getChannel(): string
    {
        return Arr::get($this->logRecord, 'channel', '*Channel not provided*');
    }

    protected function getStylesheet(): string
    {
        return '
            <style>
                html { padding: 10px }
                img { border: 0; }
            </style>
        ';
    }

    protected function renderContext(): string
    {
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
            /** @noinspection JsonEncodingApiUsageInspection */
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $content .= <<<EOF
                    <pre style="border: 1px solid {$this->colors['json_block_border']}; background: {$this->colors['json_block_bg']};
                    padding: 10px; font-size: 14px !important; word-break: break-all; white-space: pre-wrap;">$json</pre>
EOF;
        }
        return $content . "\n               </div>\n            </div>";
    }

    protected function getLogLevelTitle(): string
    {
        return match ($this->logRecord->level) {
            Level::Debug => 'Debug Log',
            Level::Info => 'Information',
            Level::Notice => 'Notice',
            Level::Warning => 'Warning Log',
            Level::Error => 'Error Log',
            Level::Critical => 'Critical Error Log',
            Level::Alert => 'Alert Log',
            Level::Emergency => 'Emergency Log',
        };
    }

    protected function getLogLevelColor(): string
    {
        return match ($this->logRecord->level) {
            Level::Debug => '#cccccc',
            Level::Info => '#468847',
            Level::Notice => '#3a87ad',
            Level::Warning => '#E78B00',
            Level::Error => '#c12a19',
            Level::Critical => '#DC3961',
            Level::Alert => '#D7046F',
            Level::Emergency => '#ff361c',
        };
    }
}