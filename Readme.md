#How to setup:

##Add require to composer.json

```
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/swayok/laravel_extended_errors.git"
    },
],
"require": {
    "swayok/laravel_extended_errors": "master@dev",
}
```

##In `/bootstrap/app.php`:

replace 

```
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);
```

with

```
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    LaravelExtendedErrors\ExceptionHandler::class
);
```

and add

```
$app->configureMonologUsing(function ($monolog) {
    $emalsForLogs = env('LOGS_SEND_TO_EMAILS', false);
    \LaravelExtendedErrors\ConfigureLogging::configureEmails($monolog, $emalsForLogs);
    \LaravelExtendedErrors\ConfigureLogging::configureFileLogs($monolog);
});
```

##In `/app/Http/Kernel.php`: 

add constructor

```
public function __construct(Application $app, Router $router) {
    if (($idx = array_search('Illuminate\Foundation\Bootstrap\ConfigureLogging', $this->bootstrappers)) !== false) {
        array_splice($this->bootstrappers, $idx, 1, \LaravelExtendedErrors\ConfigureLogging::class);
    } else {
        $this->bootstrappers[] = \LaravelExtendedErrors\ConfigureLogging::class;
    }
    parent::__construct($app, $router);
}
```

**Note**: make sure `'Illuminate\Foundation\Bootstrap\ConfigureLogging'` is really there in parent class and update it required

Now you will get additional information for errors
[screenshot.png](https://raw.githubusercontent.com/swayok/laravel_extended_errors/master/screenshot.png)