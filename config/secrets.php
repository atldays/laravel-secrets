<?php

use Atldays\Secrets\Drivers\AwsSecretManager;
use Atldays\Secrets\Filters\AwsSecretManagerFilter;

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
            | List Max Results
            |--------------------------------------------------------------------------
            |
            | Optionally limit how many secrets AWS Secrets Manager should
            | return per ListSecrets page.
            |
            | When set, the package passes this value to the AWS API as
            | MaxResults and continues paginating until every page is read.
            |
            | This is mainly useful when you want explicit control over
            | pagination behavior, including integration testing.
            |
            */

            'list_max_results' => env('AWS_SECRETS_MANAGER_LIST_MAX_RESULTS'),

            /*
            |--------------------------------------------------------------------------
            | Filter
            |--------------------------------------------------------------------------
            |
            | Define which filter class or classes should decide whether an AWS
            | secret should be included in the cached payload.
            |
            | You may provide:
            | - one filter class name
            | - an array of filter class names
            |
            | Every filter class must implement:
            | Atldays\Secrets\Contracts\SecretFilter
            |
            | The default built-in AWS filter understands the "filter_options"
            | values defined below.
            |
            | If the resolved filters array is empty, all available secrets
            | match.
            |
            */

            'filter' => AwsSecretManagerFilter::class,

            /*
            |--------------------------------------------------------------------------
            | Filter Mode
            |--------------------------------------------------------------------------
            |
            | Control how multiple filter classes should be combined.
            |
            | Supported values:
            | - or: at least one filter class must match the secret
            | - and: every filter class must match the secret
            |
            */

            'filter_mode' => env('AWS_SECRETS_MANAGER_FILTER_MODE', 'or'),

            /*
            |--------------------------------------------------------------------------
            | Filter Options
            |--------------------------------------------------------------------------
            |
            | Define the raw options used by the built-in AWS filter class.
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
            | The built-in AWS filter treats these options as OR conditions.
            | If all filter options are empty, all available secrets match.
            |
            */

            'filter_options' => [
                'tags' => env('AWS_SECRETS_MANAGER_TAGS'),
                'prefixes' => env('AWS_SECRETS_MANAGER_PREFIXES'),
                'names' => env('AWS_SECRETS_MANAGER_NAMES'),
            ],
        ],
    ],
];
