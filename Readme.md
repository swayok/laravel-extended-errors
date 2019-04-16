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
        "swayok/laravel-extended-errors": "5.5.*",
    }

[Proceed using step 2 in branch laravel_up_to_5.5](https://github.com/swayok/laravel-extended-errors/blob/laravel_up_to_5.5/Readme.md)

### Laravel 5.6+

Add require to `composer.json` and run `composer update`

    "require": {
        "swayok/laravel-extended-errors": "master@dev",
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
        'proxy' => [
            'type' => env('LOG_TELEGRAM_PROXY_TYPE', 'http'),
            'host' => env('LOG_TELEGRAM_PROXY_HOST'),
            'port' => env('LOG_TELEGRAM_PROXY_PORT'),
            'user' => env('LOG_TELEGRAM_PROXY_USER'),
            'password' => env('LOG_TELEGRAM_PROXY_PASSWORD'),
        ],
        'level' => 'debug',
        'bubble' => false',
    ]

Rendered logs and exceptions are sent as documents to provided `chat_id`

**Proxy settings:**
- `proxy.type` can be: `http`, `socks4`, `socks5`, `nginx`
- `proxy.user` and `proxy.password` can be empty if proxy has no authorisation
- `proxy.host` for `http`, `socks4` and `socks5` types usually is an IP address like `192.168.1.1`
- `proxy.host` for `nginx` type should be a full url like `http://bot.yourdomain.com`
,`https://bot.yourdomain.com` or `http://bot.yourdomain.com:8080`; 
`proxy.port` is not used for this type;

Proxy uses Basic Auth method to send user and password. 
Other auth methods are not supported right now. 
Make an issue if you need some (make sure CURL suppots it).

**Nginx vhost config to proxy requests to api.telegram.org**

    server {
        listen 80;
        server_name bot.yourdomain.com;
        location / {
            proxy_set_header X-Forwarded-Host $host;
            proxy_set_header X-Forwarded-Server $host;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_pass https://api.telegram.org/;
            client_max_body_size 100M;
        }
    }

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

To `.env` file add url provided by Sentry when you create a new project there.
It will look like this:
 
    SENTRY_DSN=https://8158bc7a6110...e7b152b@sentry.domain.com/1

Note that there is `'extra'` key used to send report to Sentry. 
This one stores all data from current request just like exception logs generated
by HTML Renderer. This provides better understanding of what happened.

