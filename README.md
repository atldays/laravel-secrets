# Laravel Secrets

[![Latest Version on Packagist](https://img.shields.io/packagist/v/atldays/laravel-secrets.svg?logo=packagist&style=for-the-badge)](https://packagist.org/packages/atldays/laravel-secrets)
[![Total Downloads](https://img.shields.io/packagist/dt/atldays/laravel-secrets.svg?style=for-the-badge&color=blue)](https://packagist.org/packages/atldays/laravel-secrets/stats)
[![CI](https://img.shields.io/github/actions/workflow/status/atldays/laravel-secrets/ci.yml?style=for-the-badge&label=CI)](https://github.com/atldays/laravel-secrets/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](LICENSE.md)

`atldays/laravel-secrets` lets Laravel applications load sensitive configuration from external secret providers without turning every request into a network call.

It gives you the same developer experience as regular environment variables, but the values come from a provider, are cached inside Laravel, and are applied during boot only from that cached payload.

That means:

- no provider API calls during normal requests
- no custom runtime lookup layer in your application code
- no need to replace your config with package-specific accessors
- one explicit refresh step when secrets change

This package is designed for teams that want a safer and more operationally friendly alternative to keeping production secrets directly in `.env` files.

## Why Use It

Most Laravel applications still depend on environment variables for things like:

- `APP_KEY`
- database credentials
- queue and mail credentials
- API tokens
- service-to-service authentication

That works well in local development, but in production it often means:

- secrets are copied into files on disk
- rotation becomes manual and error-prone
- infrastructure and application config drift apart
- secret storage is handled outside a dedicated secret manager

Laravel Secrets keeps the familiar Laravel config flow, but moves secret storage to a provider and uses Laravel cache as the runtime handoff.

## How It Works

The package uses a simple two-step flow:

1. `php artisan secrets:cache` fetches secrets from every configured driver, or from one driver when you pass `--driver`.
2. The resulting payload is stored in the configured Laravel cache store.
3. During application boot, the package reads only that cached payload.
4. The package writes the resolved values into Laravel's env repository and into the config paths defined in `config_variables`.

No provider calls are made while handling a normal HTTP request, queue job, or console command unless you explicitly ask for fresh values.

## Supported Drivers

Currently supported drivers:

- `AWS Secrets Manager`

More drivers are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for the driver contribution workflow.

## Installation

```bash
composer require atldays/laravel-secrets
```

## Compatibility

Current support matrix:

- PHP `8.1+`
- Laravel `10`, `11`, `12`, and `13`
- AWS Secrets Manager as the first built-in driver

The package is tested in CI across the supported Laravel versions, and the AWS driver also includes live integration coverage.

Publish the config when you want to customize the package:

```bash
php artisan vendor:publish --tag=secrets-config
```

## Quick Start

Minimal setup:

```php
<?php

use Atldays\Secrets\Drivers\AwsSecretManager;

return [
    'apply_secrets' => env('SECRETS_APPLY', true),

    'cache' => [
        'store' => env('SECRETS_CACHE_STORE', 'file'),
        'key' => env('SECRETS_CACHE_KEY', 'laravel-secrets'),
        'ttl' => env('SECRETS_CACHE_TTL', 43200),
    ],

    'config_variables' => [
        'app.key' => 'APP_KEY',
        'database.connections.pgsql.host' => 'DB_HOST',
        'database.connections.pgsql.database' => 'DB_DATABASE',
        'database.connections.pgsql.username' => 'DB_USERNAME',
        'database.connections.pgsql.password' => 'DB_PASSWORD',
    ],

    'drivers' => [
        AwsSecretManager::class => [
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => env('AWS_SECRETS_MANAGER_VERSION', '2017-10-17'),
            'key_strategy' => env('AWS_SECRETS_MANAGER_KEY_STRATEGY', 'basename'),
            'filter' => \Atldays\Secrets\Filters\AwsSecretManagerFilter::class,
            'filter_mode' => env('AWS_SECRETS_MANAGER_FILTER_MODE', 'or'),
            'filter_options' => [
                'tags' => env('AWS_SECRETS_MANAGER_TAGS'),
                'prefixes' => env('AWS_SECRETS_MANAGER_PREFIXES'),
                'names' => env('AWS_SECRETS_MANAGER_NAMES'),
            ],
        ],
    ],
];
```

Then refresh the cache:

```bash
php artisan secrets:cache
```

From that point on, the application boots with cached secrets applied to the env repository and to the config paths you mapped in `config_variables`.

## Configuration

### `apply_secrets`

Controls whether cached secrets should be applied during boot.

- `true`: the package applies cached secrets
- `false`: the package leaves the application config untouched

### `failure_mode`

Controls what happens when applying cached secrets during boot fails.

Supported values:

- `throw`
- `warn`
- `ignore`

This affects boot-time application of cached secrets, not the `secrets:cache` command itself. The cache command always reports its own execution errors directly.

### `cache`

Controls where the secrets payload is stored after a successful refresh.

- `store`: any Laravel cache store
- `key`: the cache key that holds the payload
- `ttl`: cache lifetime in minutes

The default `ttl` is `43200`, which equals 30 days.

### `config_variables`

This is the explicit bridge between your secret payload and Laravel config.

Laravel resolves many `env()` calls before package boot, so the package cannot rely on env mutation alone. That is why `config_variables` exists.

Example:

```php
'config_variables' => [
    'app.key' => 'APP_KEY',
    'database.connections.pgsql.password' => 'DB_PASSWORD',
]
```

## AWS Secret Manager

### Driver Configuration

The AWS driver supports:

- `region`
- `version`
- `key_strategy`
- `list_max_results`
- `filter`
- `filter_mode`
- `filter_options`

Example:

```php
use Atldays\Secrets\Drivers\AwsSecretManager;
use Atldays\Secrets\Filters\AwsSecretManagerFilter;

'drivers' => [
    AwsSecretManager::class => [
        'region' => env('AWS_DEFAULT_REGION', 'eu-central-1'),
        'version' => env('AWS_SECRETS_MANAGER_VERSION', '2017-10-17'),
        'key_strategy' => env('AWS_SECRETS_MANAGER_KEY_STRATEGY', 'basename'),
        'list_max_results' => env('AWS_SECRETS_MANAGER_LIST_MAX_RESULTS'),
        'filter' => AwsSecretManagerFilter::class,
        'filter_mode' => env('AWS_SECRETS_MANAGER_FILTER_MODE', 'or'),
        'filter_options' => [
            'tags' => env('AWS_SECRETS_MANAGER_TAGS'),
            'prefixes' => env('AWS_SECRETS_MANAGER_PREFIXES'),
            'names' => env('AWS_SECRETS_MANAGER_NAMES'),
        ],
    ],
],
```

### AWS Authentication Best Practices

If your application runs on AWS infrastructure, prefer IAM-based runtime credentials instead of hardcoding access keys.

Recommended:

- EC2: attach an IAM role to the instance profile
- ECS: use a task role
- EKS: use IAM Roles for Service Accounts
- Lambda: use the function execution role

This lets the AWS SDK resolve credentials automatically without storing long-lived secrets in your application environment.

If your application runs outside AWS, use standard AWS SDK credentials such as:

- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_SESSION_TOKEN`
- shared credentials profiles

That works fine, but should usually be treated as the less preferred option compared to IAM-based runtime identity on AWS-managed infrastructure.

### Pagination

The AWS driver supports provider pagination through `NextToken`.

If you set `list_max_results`, the package passes it to AWS as `MaxResults` and keeps requesting pages until the full result set is collected.

This is useful when you want explicit control over page size or when you want to verify pagination behavior in integration tests.

## Secret Shapes

The AWS driver supports multiple secret shapes.

### JSON object

If the secret value is a JSON object, every key becomes a secret entry.

Example:

```json
{
  "DB_PASSWORD": "secret",
  "DB_PORT": 5432
}
```

Result:

```php
[
    'DB_PASSWORD' => 'secret',
    'DB_PORT' => '5432',
]
```

### `name` / `value` wrapper

If the secret value looks like this:

```json
{
  "name": "APP_KEY",
  "value": "base64:..."
}
```

the package produces exactly one secret entry:

```php
[
    'APP_KEY' => 'base64:...',
]
```

### Plain text

If the secret is plain text, the final key is derived from the secret name using `key_strategy`.

Given:

```text
Name: /project/production/APP_KEY
Value: base64:...
```

With `basename`:

```php
[
    'APP_KEY' => 'base64:...',
]
```

With `name`:

```php
[
    '/project/production/APP_KEY' => 'base64:...',
]
```

### SecretBinary

`SecretBinary` is supported. When AWS returns binary data in base64 form, the driver decodes it before building the final payload.

## Filtering

Filtering is built around a driver-agnostic contract, so the same model can be reused by future drivers.

### Built-in AWS Filter

The package ships with `Atldays\Secrets\Filters\AwsSecretManagerFilter`.

Its `filter_options` support:

- `tags`
- `prefixes`
- `names`

Example:

```php
'filter' => \Atldays\Secrets\Filters\AwsSecretManagerFilter::class,
'filter_mode' => 'or',
'filter_options' => [
    'tags' => 'application:api|admin,environment:production',
    'prefixes' => '/project/prod/,/project/shared/',
    'names' => '/project/exact/APP_KEY,/project/exact/DB_PASSWORD',
],
```

The built-in AWS filter treats `tags`, `prefixes`, and `names` as `OR` conditions inside the filter itself.

If all `filter_options` are empty, all available AWS secrets match.

### Custom Filters

You can replace the built-in filter with your own class, or pass multiple filter classes.

Each filter must implement `Atldays\Secrets\Contracts\SecretFilter`.

Example:

```php
namespace App\Secrets\Filters;

use Atldays\Secrets\Contracts\SecretFilter;
use Atldays\Secrets\Contracts\SecretReferenceContract;

class ProductionProjectFilter implements SecretFilter
{
    public function matches(SecretReferenceContract $secret): bool
    {
        return $secret->hasTag('environment', 'production')
            && $secret->nameStartsWith('/project/prod/');
    }
}
```

Use it like this:

```php
'filter' => App\Secrets\Filters\ProductionProjectFilter::class,
```

Or combine multiple filters:

```php
'filter' => [
    App\Secrets\Filters\EnvironmentFilter::class,
    App\Secrets\Filters\PrefixFilter::class,
],
'filter_mode' => 'and',
```

Supported `filter_mode` values:

- `or`
- `and`

Meaning:

- `or`: at least one filter class must match
- `and`: every filter class must match

## Secret References

Filters receive a `SecretReferenceContract`, not the final secret value.

That is intentional.

It allows filters to decide whether a secret should be fetched without exposing the secret payload itself during the listing stage.

The current reference object includes:

- driver name
- secret name
- provider identifier
- tags
- metadata returned by the provider's listing API

For AWS, `metadata` contains the raw `ListSecrets` entry, such as:

- `Name`
- `ARN`
- `Tags`
- `KmsKeyId`
- creation and rotation metadata

It does not contain:

- `SecretString`
- `SecretBinary`
- resolved secret values

Useful helper methods on the reference include:

- `tag()`
- `hasTag()`
- `hasTagIn()`
- `hasName()`
- `hasNameIn()`
- `nameStartsWith()`
- `nameEndsWith()`
- `nameContains()`
- `hasIdentifier()`
- `hasMetadata()`
- `meta()`

## Public API

### Facade

The package exposes the `Secrets` facade as the main public entry point.

```php
use Atldays\Secrets\Facades\Secrets;

$freshSecrets = Secrets::fetch(); // Read fresh secrets directly from the provider.
$freshAwsSecrets = Secrets::fetch(\Atldays\Secrets\Drivers\AwsSecretManager::class); // Read fresh secrets from one driver.

$payload = Secrets::cache(); // Fetch fresh secrets and store the payload in the configured cache.
$awsPayload = Secrets::cache(\Atldays\Secrets\Drivers\AwsSecretManager::class); // Refresh the cache for one driver only.

$cachedValues = Secrets::values(); // Read only the resolved KEY => VALUE pairs from the cached payload.
$storedPayload = Secrets::stored(); // Read the full cached payload DTO, including drivers and secrets.

$appliedCount = Secrets::apply(); // Apply cached secrets to Laravel env/config and get the number of applied secrets.
$cleared = Secrets::clear(); // Remove the cached payload from the configured cache store.
```

## Commands

### `secrets:cache`

Fetches fresh secrets from the provider and stores them in the configured cache.

Use it when:

- you deploy a new release
- secrets were rotated in the provider
- you want to refresh the cached payload before the application starts serving traffic

Optional flags:

- `--driver=...`
  Refresh secrets from one specific driver only

Example:

```bash
php artisan secrets:cache
php artisan secrets:cache --driver="App\\Secrets\\CustomDriver"
```

### `secrets:clear`

Removes the cached payload from the configured cache store.

Use it when:

- you want to force a completely fresh refresh cycle
- you are debugging stale secret payloads
- you want to invalidate the current cache before a deployment step

Example:

```bash
php artisan secrets:clear
```

### `secrets:list`

Reads secrets from the configured cache and lists the resolved secret names.

By default, values are masked.

Use it when:

- you want to confirm that the cache was successfully warmed
- you want to inspect which keys are currently available
- you want a safe operator-friendly validation step during deployment

Optional flags:

- `--fresh`
  Read directly from the provider instead of the cached payload
- `--driver=...`
  Limit fresh reads to one driver
- `--reveal`
  Show full values instead of masked values
- `--force`
  Required in production when revealing secret values

Examples:

```bash
php artisan secrets:list
php artisan secrets:list --fresh
php artisan secrets:list --reveal
```

### `secrets:get`

Reads one named secret from the configured cache.

Use it when:

- you want to verify one specific secret
- you want to compare cached values with provider values
- you need one shell-friendly value during CI or deployment scripts

Optional flags:

- `--fresh`
  Read directly from the provider instead of the cached payload
- `--driver=...`
  Limit fresh reads to one driver
- `--reveal`
  Show the full value
- `--raw`
  Print only the value, without extra console formatting
- `--force`
  Required in production when revealing values

Examples:

```bash
php artisan secrets:get APP_KEY
php artisan secrets:get APP_KEY --fresh
php artisan secrets:get APP_KEY --reveal
php artisan secrets:get APP_KEY --raw
```

### Deployment Example

A typical deployment flow looks like this:

```bash
php artisan secrets:clear
php artisan secrets:cache
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

This ensures the provider API is contacted during the deployment phase, not during normal request handling.

When `APP_ENV=production`, revealing values requires `--force`.

## Security Notes

- The package does not fetch provider secrets during normal request handling.
- Filters operate on secret references, not on secret values.
- Real values are fetched only after a secret has matched the configured filters.
- Cached secrets should be stored in a cache backend appropriate for your environment.
- `config_variables` should be treated as an explicit allowlist of Laravel config values that may be overwritten by secrets.

## Testing Status

The package currently includes:

- local adapter-level tests
- manager and command tests
- DTO and helper-method tests
- live AWS integration tests for:
  - prefixes
  - names
  - tags
  - custom filters
  - `SecretBinary`
  - KMS-encrypted secrets
  - forced pagination
