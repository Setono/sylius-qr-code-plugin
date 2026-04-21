<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\DependencyInjection;

use Setono\SyliusQRCodePlugin\DependencyInjection\SetonoSyliusQRCodeExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyDependencyInjectionTest
 */
final class SetonoSyliusQRCodeExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusQRCodeExtension(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMinimalConfiguration(): array
    {
        return [
            'option' => 'option_value',
        ];
    }

    /**
     * @test
     */
    public function after_loading_the_correct_parameter_has_been_set(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_qr_code.option', 'option_value');
    }
}
