<?php

declare(strict_types=1);

return [

    /*
     * HTTP Basic Auth credentials protecting the OpenAPI documentation routes.
     * The DocsBasicAuth middleware fails closed: when either value is empty,
     * the documentation is unreachable instead of publicly exposed.
     */
    'basic_auth' => [
        'username' => env('DOCS_BASIC_AUTH_USERNAME', ''),
        'password' => env('DOCS_BASIC_AUTH_PASSWORD', ''),
    ],

];
