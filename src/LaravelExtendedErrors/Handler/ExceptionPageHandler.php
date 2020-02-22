<?php

namespace LaravelExtendedErrors\Handler;

use LaravelExtendedErrors\Renderer\ExceptionHtmlRenderer;
use Whoops\Handler\Handler;

class ExceptionPageHandler extends Handler {

    public function handle() {
        $exception = $this->getException();
        $renderer = new ExceptionHtmlRenderer($exception, []);
        echo $renderer->renderPage();

        return Handler::QUIT;
    }

    public function contentType() {
        return 'text/html';
    }
}
