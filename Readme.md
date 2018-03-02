# What is this
This package provides additional drivers (telegram and email) and log renderers for Laravel logging system. 
Renderers generate HTML code to be written to log files or sent to external services (slack, email, telegram).

Example of logs:
[screenshot_log.png](https://raw.githubusercontent.com/swayok/laravel_extended_errors/master/screenshot_log.png)

Example of exception log:
[screenshot_exception.png](https://raw.githubusercontent.com/swayok/laravel_extended_errors/master/screenshot_exception.png)

## 1. Installation 

### Laravel <= 5.5

Add require to `composer.json` and run `composer update`

    "require": {
        "swayok/laravel_extended_errors": "5.5.*",
    }

[Proceed using step 2 in branch laravel_up_to_5.5](https://github.com/swayok/laravel-extended-errors/blob/laravel_up_to_5.5/Readme.md)

### Laravel 5.6+

Add require to `composer.json` and run `composer update`

    "require": {
        "swayok/laravel_extended_errors": "master@dev",
    }
    

## Configuration

### Service provider

Automatically added via package auto-discovery.

### Renderers

#### HTML renderer injection into `daily` and `single` channel drivers

    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.html'),
        'tap' => [\LaravelExtendedErrors\Formatter\HtmlFormatter::class],
        'level' => 'debug',
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.html'),
        'level' => 'debug',
        'days' => 7,
        'tap' => [\LaravelExtendedErrors\Formatter\HtmlFormatter::class],
    ], 

### Drivers

All changes will be applied to `'channels'` array in `config/logging.php`.

#### Telegram channel

    'telegram' => [
        'driver' => 'telegram',
        'token' => env('LOG_TELEGRAM_API_KEY'),
        'chat_id' => env('LOG_TELEGRAM_CHAT_ID'),
        'level' => 'debug',
        'bubble' => false',
    ]

Rendered logs and exceptions are sent as documents to provided `chat_id`

#### Email channel

    'email' => [
        'driver' => 'email',
        'level' => 'debug',
        'subject' => 'Server log',
        'sender' => 'local@test.lh',
        'receiver' => ['your@email.com'],
        'bubble' => false',
    ],

**Warning**: there is no limit for exceptions and you may eventually get 
thousands of errors at once if you use this channels in high loaded project.

#### Sentry
Actually there is no channel driver for Sentry but here is quick tutorial
on how to add add exceptions reporting to Sentry via Handler.php:

Require sentry packages:

    "require": {
        "sentry/sentry": "^1.8",
        "sentry/sentry-laravel": "^0.8.0",
    }
    
In your `app/Exception/Handler.php` update `report()` method to look like:

    public function report(Exception $exception) {
        if ($this->shouldReport($exception) && app()->bound('sentry')) {
            app('sentry')->captureException($exception, ['extra' => \LaravelExtendedErrors\Utils::getMoreInformationAboutRequest()]);
        }

        parent::report($exception);
    }

To `.env` file add url provided by Setry when you create a new project there.
It will look like this:
 
    SENTRY_DSN=https://8158bc7a6110...e7b152b@sentry.domain.com/1

Note that there is `'extra'` key used to send report to sentry. 
This one stores all data from current request just like exception logs generated
by HTML Renderer. This provides better understanding of what happened.

