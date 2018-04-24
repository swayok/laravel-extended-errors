<?php

namespace LaravelExtendedErrors\MailTransport;

use Illuminate\Mail\Transport\LogTransport;
use Swift_Mime_SimpleMessage;

class ExtendedLogTransport extends LogTransport {

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null) {
        $this->beforeSendPerformed($message);

        $this->logger->debug('E-mail message log', [
            'email_message' => [
                'headers' => $message->getHeaders()->toString(),
                'subject' => $message->getSubject(),
                'body' => $message->getBody(),
            ]
        ]);

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }
}