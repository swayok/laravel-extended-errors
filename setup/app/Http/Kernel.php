<?php

namespace App\Http;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;

class Kernel extends HttpKernel {

    public function __construct(Application $app, Router $router) {
        if (($idx = array_search('Illuminate\Foundation\Bootstrap\ConfigureLogging', $this->bootstrappers)) !== false) {
            array_splice($this->bootstrappers, $idx, 1, \LaravelExtendedErrors\ConfigureLogging::class);
        } else {
            $this->bootstrappers[] = \LaravelExtendedErrors\ConfigureLogging::class;
        }
        parent::__construct($app, $router);
    }
}
