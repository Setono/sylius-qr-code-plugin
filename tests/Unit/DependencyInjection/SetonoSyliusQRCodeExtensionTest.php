<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusQRCodePlugin\DependencyInjection\SetonoSyliusQRCodeExtension;

final class SetonoSyliusQRCodeExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusQRCodeExtension(),
        ];
    }

    /**
     * @test
     */
    public function it_registers_the_default_parameters_when_no_config_is_provided(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.redirect_type', 307);
        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.utm.source', 'qr');
        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.utm.medium', 'qrcode');
        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.logo.path', null);
        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.logo.size', 60);
    }

    /**
     * @test
     */
    public function it_overrides_defaults_with_explicit_config(): void
    {
        $this->load([
            'redirect_type' => 302,
            'utm' => ['source' => 'custom-src', 'medium' => 'custom-med'],
            'logo' => ['path' => '/tmp/logo.png', 'size' => 80],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.redirect_type', 302);
        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.utm.source', 'custom-src');
        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.utm.medium', 'custom-med');
        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.logo.path', '/tmp/logo.png');
        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.logo.size', 80);
    }
}
