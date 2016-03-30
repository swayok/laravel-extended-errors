<?php

return [

    /**
     * String that contains coma-separated list of email addresses.
     * Logs wiil be sent to all provided email-addresses
     */
    'send_to_emails' => env('LOGS_SEND_TO_EMAILS', false),

    /**
     * Subject for emails
     */
    'email_subject' => env('LOGS_EMAIL_SUBJECT', 'Error report'),

    /**
     * Sender's email-address for emails (placed in 'From' header)
     */
    'email_sender_address' => env('LOGS_EMAIL_FROM', false)

];