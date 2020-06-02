<?php

namespace LaravelExtendedErrors;

use Illuminate\Log\LogManager;
use Illuminate\Mail\MailManager;
use LaravelExtendedErrors\MailTransport\ExtendedLogTransport;
use Psr\Log\LoggerInterface;

class ExtendedMailManager extends MailManager {
    
    protected function createLogTransport(array $config) {
        $logger = $this->app->make(LoggerInterface::class);
    
        if ($logger instanceof LogManager) {
            $logger = $logger->channel(
                $config['channel'] ?? $this->app['config']->get('mail.log_channel')
            );
        }
    
        return new ExtendedLogTransport($logger);
    }
}