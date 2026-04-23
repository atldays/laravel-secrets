<?php

declare(strict_types=1);

namespace Atldays\Secrets;

use Atldays\Secrets\Commands\{CacheSecretsCommand, ClearSecretsCacheCommand, GetSecretCommand, ListSecretsCommand};
use Atldays\Secrets\Data\AwsSecretManagerConfig;
use Atldays\Secrets\Drivers\AwsSecretManager;
use Atldays\Secrets\Support\SecretsStore;
use Dotenv\Repository\RepositoryInterface as DotenvRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\{BindingResolutionException, Container};
use Illuminate\Support\Env;
use Spatie\LaravelPackageTools\{Package, PackageServiceProvider};

class SecretsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-secrets')
            ->hasConfigFile('secrets')
            ->hasCommand(CacheSecretsCommand::class)
            ->hasCommand(ClearSecretsCacheCommand::class)
            ->hasCommand(ListSecretsCommand::class)
            ->hasCommand(GetSecretCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->registerAwsSecretManager();

        $this->app->singleton(SecretsStore::class, function (Container $app) {
            $config = $app->make(Config::class);
            $store = (string)$config->get('secrets.cache.store', 'file');

            return new SecretsStore(
                cache: $app->make(CacheFactory::class)->store($store),
                key: (string)$config->get('secrets.cache.key'),
                ttl: $config->get('secrets.cache.ttl'),
            );
        });

        $this->app->singleton(DotenvRepository::class, static function (): DotenvRepository {
            return Env::getRepository();
        });

        $this->app->singleton(SecretsManager::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function packageBooted(): void
    {
        $this->app->make(SecretsManager::class)->apply();
    }

    protected function registerAwsSecretManager(): void
    {
        $this->app->singleton(AwsSecretManagerConfig::class, function (Container $app): AwsSecretManagerConfig {
            return AwsSecretManagerConfig::from($app->make(Config::class));
        });

        $this->app->singleton(AwsSecretManager::class);
    }
}
