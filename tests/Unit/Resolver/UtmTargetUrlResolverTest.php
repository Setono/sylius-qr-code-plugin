<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Resolver;

use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Resolver\TargetUrlResolverInterface;
use Setono\SyliusQRCodePlugin\Resolver\UtmTargetUrlResolver;

final class UtmTargetUrlResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_delegates_supports_to_the_decorated_resolver(): void
    {
        $qrCode = new TargetUrlQRCode();

        $decorated = $this->prophesize(TargetUrlResolverInterface::class);
        $decorated->supports($qrCode)->willReturn(true);

        self::assertTrue((new UtmTargetUrlResolver($decorated->reveal()))->supports($qrCode));
    }

    /**
     * @test
     */
    public function it_returns_the_decorated_url_unchanged_when_no_utm_fields_are_set(): void
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setTargetUrl('https://example.com/page?existing=1');

        $url = (string) $this->resolveWith($qrCode, 'https://example.com/page?existing=1');

        self::assertSame('https://example.com/page?existing=1', $url);
    }

    /**
     * @test
     */
    public function it_appends_entity_utm_parameters_to_the_decorated_url(): void
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setUtmSource('qr');
        $qrCode->setUtmMedium('qrcode');
        $qrCode->setUtmCampaign('spring-2026');

        $url = (string) $this->resolveWith($qrCode, 'https://example.com/page');

        self::assertStringContainsString('utm_source=qr', $url);
        self::assertStringContainsString('utm_medium=qrcode', $url);
        self::assertStringContainsString('utm_campaign=spring-2026', $url);
    }

    /**
     * @test
     */
    public function it_overrides_existing_utm_keys_on_the_target_url(): void
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setUtmSource('qr');

        $url = (string) $this->resolveWith(
            $qrCode,
            'https://example.com/page?utm_source=email&keep=1',
        );

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

        self::assertSame('qr', $query['utm_source'] ?? null);
        self::assertSame('1', $query['keep'] ?? null);
    }

    /**
     * @test
     */
    public function it_skips_utm_keys_that_are_null_on_the_entity(): void
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setUtmMedium('qrcode');

        $url = (string) $this->resolveWith($qrCode, 'https://example.com/page');

        self::assertStringNotContainsString('utm_source', $url);
        self::assertStringContainsString('utm_medium=qrcode', $url);
    }

    private function resolveWith(TargetUrlQRCode $qrCode, string $decoratedReturn): \League\Uri\Contracts\UriInterface
    {
        $decorated = $this->prophesize(TargetUrlResolverInterface::class);
        $decorated->resolve($qrCode)->willReturn(Uri::new($decoratedReturn));

        return (new UtmTargetUrlResolver($decorated->reveal()))->resolve($qrCode);
    }
}
