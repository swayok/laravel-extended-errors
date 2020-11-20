<?php

namespace LaravelExtendedErrors\Renderer;

use Illuminate\Support\Arr;

class LogEmailRenderer extends LogHtmlRenderer {

    protected function renderContext(): string {
        $context = Arr::get($this->logRecord, 'context.email_message');
        if (empty($context)) {
            return '';
        }
        $subject = Arr::get($context, 'subject', '');
        $headers = htmlentities(Arr::get($context, 'headers', ''));
        $content = <<<HTML
            <div class="request-info">
                <hr>
                <div style="font-size: 14px !important">
                    <h2 style="margin: 20px 0 20px 0; font-weight: bold; font-size: 18px;">E-mail subject</h2>
                    <p>$subject</p>
                    <h2 style="margin: 20px 0 20px 0; font-weight: bold; font-size: 18px;">E-mail headers</h2>
                    <pre style="border: 1px solid {$this->colors['json_block_border']}; background: {$this->colors['json_block_bg']};
                    padding: 10px; font-size: 14px !important; word-break: break-all; white-space: pre-wrap;">$headers</pre>
                </div>
            </div>
HTML;
        return $content . $this->showEmailHtml();
    }

    protected function showEmailHtml(): string {
        return '<iframe width="100%" height="400px" sandbox="" frameborder="0" srcdoc="'
            . htmlentities(Arr::get($this->logRecord, 'context.email_message.body', ''))
            . '"></iframe>';
    }

}