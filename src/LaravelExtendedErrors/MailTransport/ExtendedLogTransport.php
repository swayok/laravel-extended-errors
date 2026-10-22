<?php

namespace LaravelExtendedErrors\MailTransport;

use Illuminate\Mail\Transport\LogTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class ExtendedLogTransport extends LogTransport {

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage {
        if (!$message instanceof Email) {
            return parent::send($message, $envelope);
        }

        $this->logger->debug('E-mail message log', [
            'email_message' => [
                'headers' => $message->getHeaders()->toString(),
                'subject' => $message->getSubject(),
                'body' => $message->getHtmlBody() ?? $message->getTextBody(),
            ]
        ]);

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }
}
