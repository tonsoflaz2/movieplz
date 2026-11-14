<?php

return [
    /*
    | App Review access. A fixed 5-digit code (MOVIEPLZ_REVIEW_CODE) that
    | joins the demo library named MOVIEPLZ_REVIEW_HOST (made by
    | `php artisan movieplz:review-demo`), for Apple's and Google's
    | reviewers, who can't be read a live 10-minute code. Each use issues a
    | fresh one-time invite, so the normal join flow runs. Leave the code
    | unset to turn it off.
    */
    'review_code' => env('MOVIEPLZ_REVIEW_CODE'),
    'review_host' => env('MOVIEPLZ_REVIEW_HOST', 'MoviePlz Demo'),

    // Shown on /privacy and /support (the store listing's required pages).
    'support_email' => env('MOVIEPLZ_SUPPORT_EMAIL', 'lazarus@hypeapps.dev'),
];
