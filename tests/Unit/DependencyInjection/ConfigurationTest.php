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
    public function it_defaults_the_driver_to_doctrine_orm(): void
    {
        $this->assertProcessedConfigurationEquals([[]], ['driver' => 'doctrine/orm'], 'driver');
    }

    /**
     * @test
     */
    public function it_defaults_redirect_type_to_307(): void
    {
        $this->assertProcessedConfigurationEquals([[]], ['redirect_type' => 307], 'redirect_type');
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
    public function it_defaults_logo_configuration(): void
    {
        $this->assertProcessedConfigurationEquals(
            [[]],
            ['logo' => ['path' => null, 'size' => 60]],
            'logo',
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

    /**
     * @test
     */
    public function it_rejects_logo_size_outside_0_to_100(): void
    {
        $this->assertConfigurationIsInvalid([['logo' => ['size' => 150]]]);
        $this->assertConfigurationIsInvalid([['logo' => ['size' => -1]]]);
    }

    /**
     * @test
     */
    public function it_accepts_logo_size_at_the_bounds(): void
    {
        $this->assertConfigurationIsValid([['logo' => ['size' => 0]]]);
        $this->assertConfigurationIsValid([['logo' => ['size' => 100]]]);
    }
}
