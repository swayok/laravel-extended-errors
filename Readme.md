#How to setup:

##1. Add require to `composer.json` and run `composer update`

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/swayok/laravel_extended_errors.git"
        },
    ],
    "require": {
        "swayok/laravel_extended_errors": "master@dev",
    }

##2. To `/bootstrap/app.php` add:

    \LaravelExtendedErrors\ConfigureLogging::init(
        $app,
        env('LOGS_SEND_TO_EMAILS') ?: false,
        env('LOGS_EMAIL_SUBJECT') ?: 'Error report'
    );

##3. Modify `app/Exceptions/Handler.php` to extend `LaravelExtendedErrors\ExceptionHandler` or `PeskyCMF\CmfExceptionHandler`:

    class Handler extends LaravelExtendedErrors\ExceptionHandler {
        /**
         * A list of the exception types that should not be reported.
         *
         * @var array
         */
        protected $dontReport = [
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
            \Illuminate\Session\TokenMismatchException::class,
            \Illuminate\Foundation\Validation\ValidationException::class,
            \Illuminate\Validation\ValidationException::class,
        ];
    
    }

##4. Configure environment variables in `.env` file:

    LOGS_SEND_TO_EMAILS="some1@email.com,some2@email.com"
    LOGS_EMAIL_SUBJECT="Error report"
    LOGS_EMAIL_FROM="sender@host.com"

Now you will get additional information for errors:
[screenshot.png](https://raw.githubusercontent.com/swayok/laravel_extended_errors/master/screenshot.png)