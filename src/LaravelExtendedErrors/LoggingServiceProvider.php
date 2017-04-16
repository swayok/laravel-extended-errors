<?php

namespace LaravelExtendedErrors;

use Illuminate\Log\Writer;
use Illuminate\Support\ServiceProvider;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as Monolog;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class LoggingServiceProvider extends ServiceProvider {

    public function boot() {
        $this->publishes([
            __DIR__ . '/config/logging.php' => config_path('logging.php'),
        ], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        if (!$this->app->resolved('log')) {
            $this->app->singleton('log', function () {
                return $this->createLogger();
            });
        } else {
            $this->app->instance('log', $this->createLogger());
        }
    }

    /**
     * @return Writer
     */
    protected function createLogger() {
        $log = new Writer(
            new Logger($this->channel()), $this->app['events']
        );
        $this->configureLogger($log->getMonolog());
        return $log;
    }

    /**
     * Get the name of the log "channel".
     *
     * @return string
     */
    protected function channel() {
        return $this->app->bound('env') ? $this->app->environment() : 'production';
    }

    /**
     * @param Monolog $monolog
     */
    protected function configureLogger(Monolog $monolog) {
        $emalAddresses = config('logging.send_to_emails') ?: false;
        $emailSubject = config('logging.email_subject') ?: 'Error report';
        $this->configureEmails($monolog, $emalAddresses, $emailSubject);
        $logsFilePath = storage_path('logs/errors.log.html');
        $this->configureFileLogs($monolog, $logsFilePath);
    }

    /**
     * @param Monolog $monolog
     * @param string $emailAddresses
     * @param $emailSubject
     */
    protected function configureEmails(Monolog $monolog, $emailAddresses, $emailSubject) {
        if (!empty($emailAddresses)) {
            $senderEmail = config('logging.email_sender_address') ?: false;
            if (empty($senderEmail)) {
                $senderEmail = 'errors@' . (empty($_SERVER['HTTP_HOST']) ? 'unknown.host' : $_SERVER['HTTP_HOST']);
            }
            $mail = new NativeMailerHandler(
                $emailAddresses,
                $emailSubject,
                $senderEmail,
                config('logging.log_level', config('app.log_level', Monolog::WARNING))
            );
            $mail->setFormatter(new HtmlFormatter());
            $mail->pushProcessor(new WebProcessor());
            $mail->pushProcessor(new IntrospectionProcessor(Monolog::DEBUG));
            $mail->setContentType('text/html');
            $monolog->pushHandler($mail);
        }
    }

    /**
     * @param Monolog $monolog
     * @param string $filePath
     */
    protected function configureFileLogs(Monolog $monolog, $filePath) {
        $files = new RotatingFileHandler(
            $filePath,
            $this->maxFiles(),
            Monolog::DEBUG,
            true,
            0666
        );
        $files->setFormatter(new HtmlFormatter());
        $files->pushProcessor(new WebProcessor());
        $files->pushProcessor(new IntrospectionProcessor(Monolog::DEBUG));
        $monolog->pushHandler($files);
    }

    /**
     * Get the maximum number of log files for the application.
     * @return int
     */
    protected function maxFiles() {
        if ($this->app->bound('config')) {
            return $this->app->make('config')->get('app.log_max_files', 30);
        }
        return 0;
    }

}