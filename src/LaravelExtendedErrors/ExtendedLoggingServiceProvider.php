<?php

declare(strict_types=1);

namespace LaravelExtendedErrors;

use Illuminate\Log\LogManager;
use Illuminate\Log\ParsesLogConfiguration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LaravelExtendedErrors\Formatter\EmailFormatter;
use LaravelExtendedErrors\Handler\ExceptionPageHandler;
use LaravelExtendedErrors\Handler\TelegramHandler;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Logger;

class ExtendedLoggingServiceProvider extends ServiceProvider
{
    use ParsesLogConfiguration;

    /**
     * Get fallback log channel name.
     */
    protected function getFallbackChannelName(): string
    {
        return 'production';
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->replaceLogManager();

        /*
            Channel Config:
            'telegram' => [
                'driver' => 'telegram',
                'token' => env('LOG_TELEGRAM_API_KEY'),
                'chat_id' => env('LOG_TELEGRAM_CHAT_ID'),
                'level' => 'debug',
                'bubble' => false',
            ],
        */
        $this->registerTelegramChannelDriver();

        /*
            Channel Config:
            'email' => [
                'driver' => 'email',
                'sender' => env('LOG_EMAIL_SENDER'),
                'subject' => env('LOG_EMAIL_SUBJECT'),
                'receiver' => preg_split('%\s*,\s*%', trim(env('LOG_EMAIL_RECEIVER'))),
                'level' => 'debug',
            ],
            Note: 'receiver' can be a string (single email) or array (several emails)
        */
        $this->registerEmailChannelDriver();

        /*
            Config:
            'logging.replace_whoops' => true/false
         */
        $this->replaceWhoopsPrettyPrintHandler();
    }

    protected function replaceLogManager(): void
    {
        Log::swap(new ExtendedLogManager($this->app));
    }

    protected function registerTelegramChannelDriver(): void
    {
        Log::extend('telegram', function ($app, array $config) {
            /** @var LogManager $this */
            $handler = new TelegramHandler(
                $this->level($config),
                Arr::get($config, 'token'),
                Arr::get($config, 'chat_id'),
                Arr::get($config, 'bubble', false),
                Arr::get($config, 'proxy')
            );
            $handler->setFormatter(new EmailFormatter());
            return new Logger($this->parseChannel($config), [$handler]);
        });
    }

    protected function registerEmailChannelDriver(): void
    {
        Log::extend('email', function ($app, array $config) {
            /** @var LogManager $this */
            $senderEmail = Arr::get($config, 'sender');
            $ip = (empty($_SERVER['SERVER_ADDR']) ? 'undefined ip' : $_SERVER['HTTP_HOST']);
            $host = (empty($_SERVER['HTTP_HOST']) ? 'unknown.host' : $_SERVER['HTTP_HOST']);
            if (empty($senderEmail)) {
                /** @noinspection HostnameSubstitutionInspection */
                $senderEmail = 'errors@' . $host;
            }
            $handler = new NativeMailerHandler(
                $config['receiver'],
                Arr::get($config, 'subject', "Log from $host ($ip)"),
                $senderEmail,
                $this->level($config)
            );
            $handler
                ->setContentType('text/html')
                ->setFormatter(new EmailFormatter());
            return new Logger($this->parseChannel($config), [$handler]);
        });
    }

    protected function replaceWhoopsPrettyPrintHandler(): void
    {
        if ($this->app['config']['logging.replace_whoops']) {
            /** @noinspection ClassConstantCanBeUsedInspection */
            $this->app->bind('\Whoops\Handler\PrettyPageHandler', function () {
                return new ExceptionPageHandler();
            });
        }
    }
}
