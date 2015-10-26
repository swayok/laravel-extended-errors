<?php

// replace default singleton with this one to enable extended exceptions
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    LaravelExtendedErrors\ExceptionHandler::class
);

// basic monolog config to emails extended errors as html and write html error logs to files
$app->configureMonologUsing(function ($monolog) {
    $emalsForLogs = env('LOGS_SEND_TO_EMAILS', false);
    \LaravelExtendedErrors\ConfigureLogging::configureEmails($monolog, $emalsForLogs);
    \LaravelExtendedErrors\ConfigureLogging::configureFileLogs($monolog);
});

