<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    // Aceita a chave como PEM direto OU em base64 (recomendado para painéis
    // de env que corrompem valores multi-linha). Vazio => usa storage/oauth-*.key.
    'private_key' => ($pk = env('PASSPORT_PRIVATE_KEY'))
        ? (str_contains($pk, 'BEGIN') ? $pk : base64_decode($pk))
        : null,

    'public_key' => ($pubk = env('PASSPORT_PUBLIC_KEY'))
        ? (str_contains($pubk, 'BEGIN') ? $pubk : base64_decode($pubk))
        : null,

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
