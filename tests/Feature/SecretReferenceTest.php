<?php

declare(strict_types=1);

namespace Tests\Feature;

use Atldays\Secrets\Data\SecretReference;
use Tests\TestCase;

class SecretReferenceTest extends TestCase
{
    public function test_it_exposes_helper_methods_for_filters(): void
    {
        $reference = new SecretReference(
            driver: 'aws-secret-manager',
            name: '/project/testing/APP_KEY',
            identifier: 'arn:aws:secretsmanager:example',
            tags: [
                'environment' => 'testing',
                'application' => 'laravel-secrets',
            ],
            metadata: [
                'Name' => '/project/testing/APP_KEY',
                'KmsKeyId' => 'kms-key-id',
            ],
        );

        $this->assertSame('testing', $reference->tag('environment'));
        $this->assertTrue($reference->hasTag('environment'));
        $this->assertTrue($reference->hasTag('environment', 'testing'));
        $this->assertFalse($reference->hasTag('environment', 'production'));
        $this->assertTrue($reference->hasTagIn('application', ['laravel-secrets', 'other']));
        $this->assertTrue($reference->hasName('/project/testing/APP_KEY'));
        $this->assertTrue($reference->hasNameIn(['/other', '/project/testing/APP_KEY']));
        $this->assertTrue($reference->nameStartsWith('/project/testing/'));
        $this->assertTrue($reference->nameEndsWith('APP_KEY'));
        $this->assertTrue($reference->nameContains(['/kms/', '/testing/']));
        $this->assertTrue($reference->hasIdentifier());
        $this->assertTrue($reference->hasMetadata('KmsKeyId'));
        $this->assertSame('kms-key-id', $reference->meta('KmsKeyId'));
        $this->assertSame('fallback', $reference->meta('Missing', 'fallback'));
    }
}
