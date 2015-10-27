<?php


namespace LaravelExtendedErrors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\ConfigureLogging as ParentConfigureLogging;
use Illuminate\Log\Writer;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as Monolog;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class ConfigureLogging extends ParentConfigureLogging {

    /**
     * Register the logger instance in the container.
     *
     * @param  \Illuminate\Contracts\Foundation\Application $app
     * @return \Illuminate\Log\Writer
     */
    protected function registerLogger(Application $app) {
        $logger = new Logger($app->environment()); //< do not move this! it might produce "Class declarations may not be nested" error
        $app->instance('log', $log = new Writer(
            $logger, $app['events'])
        );

        return $log;
    }

    static public function configureEmails(Monolog $monolog, $emalsForLogs) {
        if (!empty($emalsForLogs)) {
            $mail = new NativeMailerHandler(
                $emalsForLogs,
                env('LOGS_EMAIL_SUBJECT', 'Error report'),
                env('LOGS_EMAIL_FROM', 'errors@platido.ru')
            );
            $mail->setFormatter(new HtmlFormatter());
            $mail->pushProcessor(new WebProcessor());
            $mail->pushProcessor(new IntrospectionProcessor(Logger::NOTICE));
            $mail->setContentType('text/html');
            $monolog->pushHandler($mail);
        }
    }

    static public function configureFileLogs(Monolog $monolog) {
        $files = new RotatingFileHandler(
            storage_path('/logs') . '/errors.log.html',
            3000,
            Logger::NOTICE,
            true,
            0666
        );
        $files->setFormatter(new HtmlFormatter());
        $files->pushProcessor(new WebProcessor());
        $files->pushProcessor(new IntrospectionProcessor(Logger::NOTICE));
        $monolog->pushHandler($files);
    }
}