<?php

declare(strict_types=1);

// local XAMPP setup
return [
    'host' => 'localhost',
    'db' => 'That_db',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',

    // Mail — currently Mailtrap (sandbox): messages appear at https://mailtrap.io inboxes,
    // NOT in Gmail/Outlook. For real delivery, replace with your SMTP provider credentials.
    'mail_host' => 'sandbox.smtp.mailtrap.io',
    'mail_port' => 2525,
    'mail_encryption' => 'tls', // tls for Mailtrap 2525/587; use 'ssl' for port 465; '' to disable
    'mail_username' => 'e0f86384025cc6',
    'mail_password' => '7fbb0f8edfc423',
    'mail_from' => 'no-reply@that.com',
    'mail_from_name' => 'ذات للاستشارات النفسية',

    // App
    'app_url' => 'http://localhost/That-Copy',
];
