<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Resolver\CompositeTargetUrlResolver;
use Setono\SyliusQRCodePlugin\Resolver\TargetUrlResolverInterface;
use Setono\SyliusQRCodePlugin\Resolver\UriFactory;

final class CompositeTargetUrlResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_dispatches_to_the_first_resolver_that_reports_support(): void
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setTargetUrl('https://example.com/page');

        $unsupporting = $this->prophesize(TargetUrlResolverInterface::class);
        $unsupporting->supports($qrCode)->willReturn(false);
        $unsupporting->resolve($qrCode)->shouldNotBeCalled();

        $supporting = $this->prophesize(TargetUrlResolverInterface::class);
        $supporting->supports($qrCode)->willReturn(true);
        $supporting->resolve($qrCode)->willReturn(UriFactory::fromString('https://example.com/page'));

        $composite = new CompositeTargetUrlResolver();
        $composite->add($unsupporting->reveal());
        $composite->add($supporting->reveal());

        self::assertSame('https://example.com/page', (string) $composite->resolve($qrCode));
    }

    /**
     * @test
     */
    public function supports_is_true_when_any_registered_resolver_supports(): void
    {
        $qrCode = new TargetUrlQRCode();

        $no = $this->prophesize(TargetUrlResolverInterface::class);
        $no->supports($qrCode)->willReturn(false);

        $yes = $this->prophesize(TargetUrlResolverInterface::class);
        $yes->supports($qrCode)->willReturn(true);

        $composite = new CompositeTargetUrlResolver();
        $composite->add($no->reveal());
        $composite->add($yes->reveal());

        self::assertTrue($composite->supports($qrCode));
    }

    /**
     * @test
     */
    public function supports_is_false_when_no_registered_resolver_supports(): void
    {
        $qrCode = new TargetUrlQRCode();

        $none = $this->prophesize(TargetUrlResolverInterface::class);
        $none->supports($qrCode)->willReturn(false);

        $composite = new CompositeTargetUrlResolver();
        $composite->add($none->reveal());

        self::assertFalse($composite->supports($qrCode));
    }

    /**
     * @test
     */
    public function supports_is_false_when_no_resolvers_are_registered(): void
    {
        self::assertFalse((new CompositeTargetUrlResolver())->supports(new TargetUrlQRCode()));
    }

    /**
     * @test
     */
    public function resolve_throws_unsupported_when_no_registered_resolver_handles_the_qr_code(): void
    {
        $qrCode = new TargetUrlQRCode();

        $none = $this->prophesize(TargetUrlResolverInterface::class);
        $none->supports($qrCode)->willReturn(false);

        $composite = new CompositeTargetUrlResolver();
        $composite->add($none->reveal());

        $this->expectException(UnsupportedQRCodeException::class);

        $composite->resolve($qrCode);
    }

    /**
     * @test
     */
    public function resolve_throws_unsupported_when_no_resolvers_are_registered(): void
    {
        $this->expectException(UnsupportedQRCodeException::class);

        (new CompositeTargetUrlResolver())->resolve(new TargetUrlQRCode());
    }
}
