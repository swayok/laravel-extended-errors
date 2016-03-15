<?php


namespace LaravelExtendedErrors;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\ConfigureLogging as ParentConfigureLogging;
use Illuminate\Log\Writer;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as Monolog;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class ConfigureLogging extends ParentConfigureLogging {

    static public function init(Application $app, $emalsForLogs = false) {
        // replace default configurator
        $app->singleton(
            \Illuminate\Foundation\Bootstrap\ConfigureLogging::class,
            __CLASS__
        );

        $app->configureMonologUsing(function ($monolog) use ($emalsForLogs) {
            self::configureEmails($monolog, $emalsForLogs);
            self::configureFileLogs($monolog);
        });
    }

    /**
     * Register the logger instance in the container.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return \Illuminate\Log\Writer
     */
    protected function registerLogger(Application $app) {
        $logger = new Logger($app->environment()); //< do not move this! it might produce "Class declarations may not be nested" error
        $log = new Writer($logger, $app['events']);
        $app->instance('log', $log);

        return $log;
    }

    static public function configureEmails(Monolog $monolog, $emalsForLogs) {
        if (!empty($emalsForLogs)) {
            $senderEmail = env('LOGS_EMAIL_FROM', false);
            if (empty($senderEmail)) {
                $senderEmail = 'errors@' . (empty($_SERVER['HTTP_HOST']) ? 'unknown.host' : $_SERVER['HTTP_HOST']);
            }
            $level = env('DEBUG', false) ? Logger::DEBUG : Logger::ERROR;
            $mail = new NativeMailerHandler(
                $emalsForLogs,
                env('LOGS_EMAIL_SUBJECT', 'Error report'),
                $senderEmail,
                $level
            );
            $mail->setFormatter(new HtmlFormatter());
            $mail->pushProcessor(new WebProcessor());
            $mail->pushProcessor(new IntrospectionProcessor(Logger::NOTICE));
            $mail->setContentType('text/html');
            $monolog->pushHandler($mail);
        }
    }

    static public function configureFileLogs(Monolog $monolog) {
        // erros/nitices
        $files = new RotatingFileHandler(
            storage_path('/logs') . '/errors.log.html',
            365,
            Logger::DEBUG,
            true,
            0666
        );
        $files->setFormatter(new HtmlFormatter());
        $files->pushProcessor(new WebProcessor());
        $files->pushProcessor(new IntrospectionProcessor(Logger::NOTICE));
        $monolog->pushHandler($files);
    }
}