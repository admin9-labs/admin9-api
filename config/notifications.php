<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Define which channels each notification type should use.
    | Supported channels: "mail", "database", "sms"
    |
    | Downstream projects can add channels here or override per notification.
    | The "sms" channel requires a custom driver — see documentation.
    |
    */

    'channels' => [
        'default' => ['mail', 'database'],

        'password_reset' => ['mail'],
    ],

];
