# Contributing

Thank you for contributing to `atldays/laravel-secrets`.

This package is security-sensitive. We want it to stay small, predictable, and easy to trust in production. Contributions are welcome, especially around new secret drivers, but they should come with the same level of care we expect from code that touches credentials, runtime configuration, and deployment flows.

## What We Are Building

`laravel-secrets` is built around a simple operational model:

- secrets live in a provider, not in application code
- provider APIs are contacted during refresh, not during normal request handling
- Laravel cache becomes the runtime handoff between provider data and application boot
- the package should feel Laravel-native, even when the underlying provider is not

The main area for contributions is new drivers.

Examples:

- additional cloud secret managers
- provider-specific filters
- provider-specific testing utilities
- improvements to live and adapter-level coverage

## Current Compatibility

The package currently targets:

- PHP `8.1+`
- Laravel `10`, `11`, `12`, and `13`

Contributions should preserve that support matrix unless the change is explicitly intended to raise the minimum supported version.

## Development Workflow

This repository follows two conventions:

- `Conventional Commits`
- `Gitflow`

### Conventional Commits

Commit messages must follow this format:

```text
<type>(optional-scope): <description>
```

Examples:

```text
feat(aws): add explicit pagination control
fix(filters): pass driver config into resolved filters
docs(readme): clarify deployment workflow
test(live): add tag filter integration coverage
```

Allowed commit types are enforced by the repository hooks.

### Gitflow

We use Gitflow-style branches and release discipline.

Typical branch categories:

- `feature/...`
- `fix/...`
- `hotfix/...`
- `release/...`

Keep branches focused. A driver contribution should usually be one branch, one coherent feature, one PR.

## Git Hooks

The repository uses `.githooks` as the active hook path.

Current hooks:

- `commit-msg`
  Enforces Conventional Commits.
- `pre-commit`
  Runs Laravel Pint on staged PHP files and re-stages them.
- `pre-push`
  Runs the test suite through Docker before push.

If hooks are not active locally:

```bash
composer install
```

or:

```bash
git config core.hooksPath .githooks
```

## Local Development

Install dependencies:

```bash
composer install
```

Useful commands:

```bash
composer test
composer format
composer format:test
composer check
```

This repository is Docker-first for formatting and tests. If host PHP or Composer is unavailable, use the Docker-backed commands already wired into the repo scripts and hooks.

## Testing Expectations

Every meaningful contribution should include tests.

For this package, that means more than ordinary unit coverage.

We expect contributors to cover:

- adapter-level behavior
- manager behavior
- boot/apply behavior when relevant
- console behavior when relevant
- live provider behavior for new drivers

### Required Coverage for New Drivers

If you add a new driver, you are expected to provide both:

1. local adapter-level tests with provider-shaped responses
2. live integration tests against the real provider

Why both matter:

- local tests make edge cases and regressions easy to run in CI
- live tests prove that the driver actually matches the real provider contract

Do not rely only on fake drivers or only on container-level orchestration tests for a new provider adapter.

### What Local Tests Should Cover

At minimum, a new driver should cover:

- happy-path fetch behavior
- provider pagination, when supported
- provider-specific secret shapes
- filtering behavior
- duplicate-key collisions or overwrite behavior, where relevant
- invalid configuration or invalid filter handling
- any provider-specific normalization rules

### What Live Tests Should Cover

Live tests should verify the real provider contract, not only the package internals.

For a new driver, cover the cases that matter to production use, such as:

- plain secret values
- structured values, if the provider supports them
- authentication/credential resolution
- filtering behavior
- pagination behavior
- provider-specific encryption behavior, if relevant

Live tests are especially important for security-sensitive behavior and provider-specific edge cases.

## Live Test Environment Files

Live tests must use a local environment file that is not committed.

Recommended pattern:

- `.env.aws`
- `.env.<driver>`

These files must stay local-only. The repository already ignores `.env*`, and contributor setup should respect that.

Example:

```env
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_SESSION_TOKEN=...
AWS_DEFAULT_REGION=eu-central-1
AWS_SECRETS_MANAGER_INTEGRATION=1
AWS_SECRETS_MANAGER_TEST_PREFIX=/atldays/laravel-secrets/test/
```

Run the live suite like this:

```bash
docker run --rm -u $(id -u):$(id -g) \
  -v "$PWD:/app" \
  -w /app \
  --env-file .env.aws \
  composer:2 sh -lc "AWS_SECRETS_MANAGER_INTEGRATION=1 vendor/bin/phpunit --filter AwsSecretManagerIntegrationTest"
```

For additional drivers, follow the same pattern with a driver-specific env file and an integration test class dedicated to that provider.

## Live Test Data

If you add a new driver, document the live test data required to validate it.

That usually means:

- the test secret namespace or prefix
- required tags or labels
- required structured values
- any encryption setup
- pagination setup, if multiple pages must be forced

### AWS Secret Manager Reference Setup

The AWS driver currently validates against live test data such as:

- plain string secrets
- JSON object secrets
- `name/value` wrapped secrets
- `SecretBinary`
- KMS-encrypted secrets
- tag-based filtering
- custom filter behavior
- forced pagination using `list_max_results`

The AWS test namespace uses a dedicated prefix such as:

```text
/atldays/laravel-secrets/test/
```

This keeps live validation isolated from real application secrets.

## Writing New Drivers

When contributing a new driver:

1. keep the public package API Laravel-native
2. keep provider-specific complexity inside the driver layer
3. reuse the shared filter model where possible
4. provide `SecretReference` data without exposing secret values during listing
5. avoid runtime provider calls during ordinary request handling

New drivers should fit the same operating model as the existing ones:

- fetch during refresh
- cache the payload
- apply from cache during boot

## Filters and Secret References

Custom filters should depend on `SecretFilter` and `SecretReferenceContract`.

That contract is intentionally read-only and designed for matching logic, not mutation.

Filters should be able to reason about:

- name
- tags
- identifier presence
- provider metadata exposed by listing APIs

They should not require the actual secret payload in order to decide whether a secret should be fetched.

## Pull Request Checklist

Before opening a PR, make sure you have:

- written focused commits in Conventional Commit format
- kept the branch scoped to one coherent change
- run `composer test`
- run `composer format:test`
- added or updated local tests
- added or updated live tests when changing or adding a driver
- updated documentation when the public behavior changed

## Security and Scope

Do not weaken the package's core model.

Please avoid contributions that:

- fetch provider secrets during normal request handling
- bypass the cache-first runtime model
- expose secret payloads during listing or filter resolution
- blur the boundary between provider data and Laravel configuration

If a proposed change makes the package easier to misuse in production, it is unlikely to be accepted.
