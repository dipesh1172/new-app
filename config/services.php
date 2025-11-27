<?php

return [
    'clients' => [
        'txu' => [
            'rate_api' => [
                'url' => env('TXU_RATE_API_URL', null),
                'username' => env('TXU_RATE_API_USERNAME', null),
                'password' => env('TXU_RATE_API_PASSWORD', null),
            ],
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */
    'google' => [
        'maps' => [
            'key' => env('GOOGLE_MAPS_API_KEY', null),
            'secret' => env('GOOGLE_MAPS_API_SECRET', null),
        ],
    ],

    'twilio' => [
        'account' => env('TWILIO_ACCOUNT_SID', null),
        'auth_token' => env('EB_TWILIO_AUTH_TOKEN', env('TWILIO_AUTH_TOKEN', null)),
        'app' => env('TWILIO_APP_SID', null),
        'workflow' => env('TWILIO_WORKFLOW', null),
        'motion_workflow' => env('TWILIO_MOTION_WORKFLOW', null),
        'workspace' => env('TWILIO_WORKSPACE', null),
        'api_key_sid' => env('EB_TWILIO_API_KEY_SID', env('TWILIO_API_KEY_SID', null)),
        'api_secret' => env('EB_TWILIO_API_SECRET_KEY', env('TWILIO_API_SECRET_KEY', null)),
        'default_number' => env('TWILIO_DEFAULT_NUMBER', null),
        'messaging_sid' => env('TWILIO_MESSAGING_SID', null),
        'idt_number' => env('TWILIO_IDT_NUMBER', null),
        'res_number' => env('TWILIO_RES_NUMBER', null),
        'tse_number' => env('TWILIO_TSE_NUMBER', null),
    ],

    'rpv' => [
        'token' => env('RPV_TOKEN', null),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'webhook_key' => env('MAILGUN_WEBHOOK_KEY'),
    ],

    'mailersend' => [
        'secret' => env('MAILERSEND_SECRET'),
        'domain' => env('MAILERSEND_DOMAIN', 'api.mailersend.com'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'zipcodes' => [
        'key' => env('ZIPCODES_APIKEY')
    ],

    'aws' => [
        'region' => env('AWS_REGION'),
        'key' => env('EB_AWS_KEY', env('AWS_KEY')),
        'secret' => env('EB_AWS_SECRET', env('AWS_SECRET')),
        'transcode' => [
            'preset' => env('AWS_TRANSCODE_PRESET', '1351620000001-100250'), //VP9 webm
            'pipeline' => env('AWS_TRANSCODE_PIPELINE'),
        ],
        'cloudfront' => [
            'domain' => env('AWS_CLOUDFRONT'),
            'key_id' => env('CLOUDFRONT_KEY_ID'),
            'private_key' => env('CLOUDFRONT_KEY'),
        ],
        'buckets' => [
            'upload' => env('AWS_UPLOAD_BUCKET'),
            'videos' => env('AWS_VIDEO_BUCKET'),
        ],
        'dynamo' => [
            'enabled' => env('AWS_DYNAMO_ENABLED', false),
            'endpoint' => env('AWS_DYNAMO_ENDPOINT', null),
        ],
        'contracts_sqs' => [
            'key' => env('AWS_CONTRACT_GENERATOR_KEY'),
            'secret' => env('AWS_CONTRACT_GENERATOR_SECRET'),
        ]
    ],

    'slack' => [
        'hook_url' => env('SLACK_HOOK_URL', null),
    ],

    'mattermost' => [
        'default_channel' => env('MATTERMOST_DEFAULT_CHANNEL', 'general'),
        'server' => env('MATTERMOST_SERVER', 'https://mm.answernet.com/'),
        'token' => env('MATTERMOST_TOKEN', 'rs7wug1zsbyifmc5opgrwcwrwe'),
    ],

    'postmark' => [
        'secret' => env('POSTMARK_SECRET'),
    ],

    'motion' => [
        'domain' => env( 'MOTION_DOMAIN'),
        'file_url' => env( 'MOTION_FILE_URL'),
        's3_bucket' => env( 'MOTION_S3_BUCKET'),
        'signed_url' => env( 'MOTION_SIGNED_URL'),
        'outbound' => [
            'english' => env('MOTION_OUTBOUND_ENGLISH'),
            'spanish' => env('MOTION_OUTBOUND_SPANISH')
        ]
    ],
    
    'tpvapi' => [
        'jwt' => env('ANSWERNET_API_STATIC_JWT')
    ],



    'notifications' => env('NOTIFICATIONS_SERVICE'),
    'dxc_proxy_url' => env('DXC_PROXY_URL'),
];
