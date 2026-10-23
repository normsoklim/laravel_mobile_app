<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    |
    | Path to the service-account JSON downloaded from the Firebase
    | console. This file contains the private key used to authorise
    | server-to-server FCM calls and must NEVER be shipped inside the
    | Flutter app or committed to version control.
    |
    | The path is resolved relative to the Laravel base path when it is
    | not absolute.
    |
    */

    'credentials' => env(
        'FIREBASE_CREDENTIALS',
        'firebase/service-account.json.json'
    ),

    /*
    |--------------------------------------------------------------------------
    | Firebase Authentication (phone OTP password recovery)
    |--------------------------------------------------------------------------
    |
    | The Flutter app authenticates the customer with Firebase Phone
    | Authentication and sends the resulting Firebase ID token to the
    | Laravel API. The API verifies that token server-side before it
    | touches the customer account.
    |
    | `project_id` MUST be the project id of the Firebase project that
    | the Flutter app is configured with (see firebase_options.dart).
    | It is used to validate the token issuer
    | (https://securetoken.google.com/<project_id>), which is what
    | guarantees the token was issued by *our* project and not by some
    | other Firebase project that happens to use the same Google keys.
    |
    | `allowed_audiences` holds the Firebase app ids (audience) that
    | are allowed to issue tokens. Leave it empty to accept any audience
    | belonging to the project above.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'tinh-shop'),

    'allowed_audiences' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FIREBASE_ALLOWED_AUDIENCES', ''))
    ))),

    /*
    | The public keys Google uses to sign Firebase ID tokens. They are
    | fetched once and cached for an hour (the cache store is shared
    | across the servers when Redis is configured).
    */
    'id_token' => [
        'jwks_url' => env(
            'FIREBASE_ID_TOKEN_JWKS_URL',
            'https://www.googleapis.com/service_accounts/v1/metadata/jwk/securetoken@system.gserviceaccount.com'
        ),
        'certs_url' => env(
            'FIREBASE_ID_TOKEN_CERTS_URL',
            'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com'
        ),
        'cache_key' => 'firebase.id_token.public_keys',
        'cache_ttl' => (int) env('FIREBASE_ID_TOKEN_CACHE_TTL', 3600),
        // Clock-skew tolerance (seconds) applied when checking exp/nbf/iat.
        'leeway' => (int) env('FIREBASE_ID_TOKEN_LEEWAY', 60),
    ],

    /*
    | The phone-authentication session must be recent for a password
    | reset to be accepted (seconds).
    */
    'phone_auth_max_age' => (int) env('FIREBASE_PHONE_AUTH_MAX_AGE', 600),

    /*
    | Default country code used to normalise locally formatted phone
    | numbers (Cambodia = 855) before they are compared with the E.164
    | number coming from Firebase.
    */
    'default_country_code' => env('FIREBASE_DEFAULT_COUNTRY_CODE', '855'),

];
