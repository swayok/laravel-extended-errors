<?php

// replace default singleton with this one to enable extended exceptions or extend LaravelExtendedErrors\ExceptionHandler::class
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    LaravelExtendedErrors\ExceptionHandler::class
);

// or extend /app/Exceptions/Handler.php

class Handler extends PeskyCMF\CmfExceptionHandler {
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        Illuminate\Auth\Access\AuthorizationException::class,
        Symfony\Component\HttpKernel\Exception\HttpException::class,
        Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Foundation\Validation\ValidationException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

}

// and use default singleton in app.php

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    \App\Exceptions\Handler::class
);

// basic monolog config to emails extended errors as html and write html error logs to files

$app->configureMonologUsing(function ($monolog) {
    $emalsForLogs = env('LOGS_SEND_TO_EMAILS', false);
    \LaravelExtendedErrors\ConfigureLogging::configureEmails($monolog, $emalsForLogs);
    \LaravelExtendedErrors\ConfigureLogging::configureFileLogs($monolog);
});

