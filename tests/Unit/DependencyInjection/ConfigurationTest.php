<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusQRCodePlugin\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    /**
     * @test
     */
    public function it_defaults_redirect_type_to_302(): void
    {
        $this->assertProcessedConfigurationEquals([[]], ['redirect_type' => 302], 'redirect_type');
    }

    /**
     * @test
     */
    public function it_defaults_utm_parameters(): void
    {
        $this->assertProcessedConfigurationEquals(
            [[]],
            ['utm' => ['source' => 'qr', 'medium' => 'qrcode']],
            'utm',
        );
    }

    /**
     * @test
     */
    public function it_accepts_allowed_redirect_types(): void
    {
        foreach ([301, 302, 307] as $redirectType) {
            $this->assertConfigurationIsValid([['redirect_type' => $redirectType]]);
        }
    }

    /**
     * @test
     */
    public function it_rejects_disallowed_redirect_types(): void
    {
        $this->assertConfigurationIsInvalid(
            [['redirect_type' => 308]],
            'Invalid redirect_type',
        );
    }
}
