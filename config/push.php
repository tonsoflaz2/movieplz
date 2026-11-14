<?php

return [
    /*
    | Android notifications go through Firebase Cloud Messaging (HTTP v1).
    | FCM_CREDENTIALS is the Firebase service account's JSON key, either
    | base64-encoded (how it goes in Forge's environment) or a path to the
    | file outside git. It's a secret: never logged or committed. The
    | project id comes from the key itself. Unset, Android pushes are
    | skipped with a warning, as iOS ones are without an APNs key.
    */
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
    ],
];
