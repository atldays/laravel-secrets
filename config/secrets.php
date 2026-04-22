<?php

use Atldays\Secrets\Drivers\AwsSecretManager;

return [

    /*
    |--------------------------------------------------------------------------
    | Apply Secrets
    |--------------------------------------------------------------------------
    |
    | Determine whether cached secrets should be applied to the application
    | automatically during package boot.
    |
    | When enabled, the package reads cached secrets, writes them into the
    | environment repository, and overwrites the configured Laravel config
    | values listed in the "config_variables" section below.
    |
    | This option does not fetch secrets from remote providers. It only controls
    | whether already cached secrets affect the running application.
    |
    */

    'apply_secrets' => env('SECRETS_APPLY', true),

    /*
    |--------------------------------------------------------------------------
    | Failure Mode
    |--------------------------------------------------------------------------
    |
    | Control how the package should behave when applying cached secrets during
    | package boot.
    |
    | Supported values:
    | - throw: stop bootstrapping and rethrow the exception
    | - warn: report the error and continue booting the application
    | - ignore: swallow the error and continue silently
    |
    | This option does not affect the cache refresh command. Console commands
    | always report their own execution errors directly.
    |
    */

    'failure_mode' => env('SECRETS_FAILURE_MODE', 'throw'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Configure where fetched secrets should be stored after the cache command
    | runs successfully.
    |
    | store:
    | The Laravel cache store that should hold the secrets payload.
    |
    | key:
    | The cache key where the full secrets payload will be stored.
    |
    | ttl:
    | The cache lifetime in minutes.
    |
    | The default value is 43200 minutes, which is equal to 30 days.
    |
    */

    'cache' => [
        'store' => env('SECRETS_CACHE_STORE', 'file'),
        'key' => env('SECRETS_CACHE_KEY', 'laravel-secrets'),
        'ttl' => env('SECRETS_CACHE_TTL', 43200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Config Variables
    |--------------------------------------------------------------------------
    |
    | Laravel resolves most env() calls inside config files before package
    | providers boot. Because of that, cached secrets must explicitly overwrite
    | the config paths that depend on them.
    |
    | The array key is the Laravel config path.
    | The array value is the secret name that should be applied to that path.
    |
    | Example:
    | 'database.connections.pgsql.password' => 'DB_PASSWORD'
    |
    */

    'config_variables' => [
        'app.key' => 'APP_KEY',
    ],

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | Define the secrets drivers that should be used when the package refreshes
    | the cached secrets payload.
    |
    | The array key must be the driver class name.
    | Drivers are resolved in array order.
    | When multiple drivers return the same secret name, later drivers overwrite
    | earlier values.
    |
    | Each driver may define its own driver-specific configuration values.
    |
    */

    'drivers' => [
        AwsSecretManager::class => [

            /*
            |--------------------------------------------------------------------------
            | Region
            |--------------------------------------------------------------------------
            |
            | The AWS region where Secrets Manager requests should be sent.
            |
            | This must match the region where your secrets are stored.
            | Example values: us-east-1, eu-central-1.
            |
            */

            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

            /*
            |--------------------------------------------------------------------------
            | Version
            |--------------------------------------------------------------------------
            |
            | The AWS Secrets Manager API version that should be used by the SDK
            | client when communicating with AWS.
            |
            | In most cases, the default value should not be changed unless your
            | SDK integration requires a different explicit version.
            |
            */

            'version' => env('AWS_SECRETS_MANAGER_VERSION', '2017-10-17'),

            /*
            |--------------------------------------------------------------------------
            | Key Strategy
            |--------------------------------------------------------------------------
            |
            | Define how the package should convert an AWS secret identifier into
            | the final secret key used inside the cached payload.
            |
            | Supported values:
            | - basename: use only the last segment of the secret name
            | - name: use the full AWS secret name as-is
            |
            | Example:
            | /project/production/DB_PASSWORD
            |
            | basename => DB_PASSWORD
            | name     => /project/production/DB_PASSWORD
            |
            */

            'key_strategy' => env('AWS_SECRETS_MANAGER_KEY_STRATEGY', 'basename'),

            /*
            |--------------------------------------------------------------------------
            | Filter
            |--------------------------------------------------------------------------
            |
            | Define the raw filter input for AWS Secret Manager secret
            | selection.
            |
            | tags:
            | Raw tag expression, for example:
            | application:api|admin,environment:production
            |
            | prefixes:
            | Raw comma-separated list of allowed secret name prefixes.
            |
            | names:
            | Raw comma-separated list of exact secret names.
            |
            | When multiple filter groups are provided, the AWS driver treats
            | them as OR conditions. If all filters are empty, no secrets match.
            |
            */

            'filter' => [
                'tags' => env('AWS_SECRETS_MANAGER_TAGS'),
                'prefixes' => env('AWS_SECRETS_MANAGER_PREFIXES'),
                'names' => env('AWS_SECRETS_MANAGER_NAMES'),
            ],
        ],
    ],
];
