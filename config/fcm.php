<?php

return [
    'driver' => env('FCM_PROTOCOL', 'http'),
    'log_enabled' => false,
    'server_key' => env('FCM_SERVER_KEY', 'AAAAKbWsfeg:APA91asdSxqZZWocDMsRWufAn_SNfSgjewuj4yTiFUJEWmD9wUnjX-yzxvfAnZXV2K2T-GdbSCKroGUnp0fMRo3_35F42CCRBplh9F3NmwcY2tkYuulaQf6NXNUFs7Kvq083bi3zE'),
    'sender_id' => env('FCM_SENDER_ID', '17914231639656'),
    'click_url' => env('FCM_CLICK_ACTION_URL', 'workinaus.com.au'),
    'http' => [
        'server_key' => env('FCM_SERVER_KEY', 'AAAAKbWsfeg:APA91bH7UasdTaSxqZZWocDMsRWufAn_SNfSgjewuj4yTiFUJEWmD9wUnjX-yzxvfAnZXV2K2T-GdbSCKroGUnp0fMRo3_35F42CCRBplh9F3NmwcY2tkYuulaQf6NXNUFs7Kvq083bi3zE'),
        'sender_id' => env('FCM_SENDER_ID', '179141632139656'),
        'server_send_url' => 'https://fcm.googleapis.com/fcm/send',
        'server_group_url' => 'https://android.googleapis.com/gcm/notification',
        'timeout' => 30.0, // in second
    ],
];

