<?php

return [
    'key_id' => env('APNS_KEY_ID'),
    'team_id' => env('APNS_TEAM_ID'),
    'key_path' => env('APNS_KEY_PATH', 'storage/app/apns/AuthKey.p8'),
    'bundle_id' => env('APNS_BUNDLE_ID', 'com.hypeapps.movieplz'),
    // No environment switch: each token's gateway (production or
    // development) is stored per client; see PushService::sendApns.
];
