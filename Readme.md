#Installation and configuration

##1. Install 
Add require to `composer.json` and run `composer update`

    "require": {
        "swayok/laravel_extended_errors": "master@dev",
    }
    
Note: If you're using PeskyCMF - it is already included  

##2. Add logger to your app

###a. For Laravel <= 5.3

To `/bootstrap/app.php` add:

    \LaravelExtendedErrors\ConfigureLogging::init($app);
    
###b. For Laravel >= 5.4

To `config/app.php` add `\LaravelExtendedErrors\LoggingServiceProvider::class` to `$providers` array. 
Place it at the beginning to make it work as soon as providers start loading. If there were no errors earlier 
this logger will replace default laravel's logger before it is created. In other cases - something went wrong
at applicaton's startup.  

##3. Configure exception handler
Modify `app/Exceptions/Handler.php` to extend `LaravelExtendedErrors\ExceptionHandler` or `PeskyCMF\CmfExceptionHandler`:

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

##4. (optional) Configure .env file
 
Configure environment variables in `.env` file or use `config/logging.php` (next step):

    LOGS_SEND_TO_EMAILS="some1@email.com,some2@email.com"
    LOGS_EMAIL_SUBJECT="Error report"
    LOGS_EMAIL_FROM="sender@host.com"
    LOGS_MIN_LEVEL=300
    
Logging levels: 100 - debug, 200 - info, 250 - notice, 300 - warning, 400 - error
    
##5. (optional) Install config file 
Copy `LaravelExtendedErrors/config/logging.php` to your app's `config` folder (if it is not there already)
This is needed to make it possible to do `php artisan config:cache`
Also you can add `\LaravelExtendedErrors\LoggingServiceProvider::class` to your `config/app.php` 'providers' list 
to publish logging config automatically

Now you will get additional information for errors:
[screenshot.png](https://raw.githubusercontent.com/swayok/laravel_extended_errors/master/screenshot.png)