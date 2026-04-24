<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Setono\SyliusQRCodePlugin\Generator\QRCodeGenerator;
use Setono\SyliusQRCodePlugin\Generator\QRCodeGeneratorInterface;
use Setono\SyliusQRCodePlugin\Model\QRCode;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class QRCodeGeneratorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     * @dataProvider formatMimeTypes
     */
    public function it_emits_the_expected_mime_type_for_each_supported_format(string $format, string $expectedMime): void
    {
        $result = $this->buildGenerator()->generate(
            $this->qrCode('winter-sale'),
            $this->channel('example.com'),
            $format,
        );

        self::assertSame($expectedMime, $result->getMimeType());
        self::assertNotSame('', $result->getString());
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function formatMimeTypes(): iterable
    {
        yield 'png' => [QRCodeGeneratorInterface::FORMAT_PNG, 'image/png'];
        yield 'svg' => [QRCodeGeneratorInterface::FORMAT_SVG, 'image/svg+xml'];
        yield 'pdf' => [QRCodeGeneratorInterface::FORMAT_PDF, 'application/pdf'];
    }

    /**
     * @test
     */
    public function it_encodes_a_per_channel_url_so_different_channels_yield_different_output(): void
    {
        $qrCode = $this->qrCode('summer');

        $generator = $this->buildGenerator();

        $a = $generator->generate($qrCode, $this->channel('a.example.com'), QRCodeGeneratorInterface::FORMAT_SVG);
        $b = $generator->generate($qrCode, $this->channel('b.example.com'), QRCodeGeneratorInterface::FORMAT_SVG);

        self::assertNotSame($a->getString(), $b->getString());
    }

    /**
     * @test
     */
    public function it_rejects_an_unsupported_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->buildGenerator()->generate($this->qrCode('x'), $this->channel('example.com'), 'bmp');
    }

    /**
     * @test
     */
    public function it_rejects_a_channel_without_hostname(): void
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getHostname()->willReturn(null);
        $channel->getCode()->willReturn('default');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no hostname/');

        $this->buildGenerator()->generate($this->qrCode('x'), $channel->reveal(), QRCodeGeneratorInterface::FORMAT_PNG);
    }

    /**
     * @test
     */
    public function it_rejects_a_qr_code_without_a_slug(): void
    {
        $qrCode = new QRCode();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no slug/');

        $this->buildGenerator()->generate($qrCode, $this->channel('example.com'), QRCodeGeneratorInterface::FORMAT_PNG);
    }

    /**
     * @test
     */
    public function it_falls_back_to_medium_error_correction_and_logs_when_the_letter_is_unknown(): void
    {
        $qrCode = $this->qrCode('bad-ecc');
        $qrCode->setErrorCorrectionLevel('Z');

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->warning(Argument::containingString('Unknown error correction level'), Argument::type('array'))
            ->shouldBeCalledOnce();

        $generator = new QRCodeGenerator($this->urlGenerator(), 1200, null, 60);
        $generator->setLogger($logger->reveal());

        // The bytes don't matter here — we just need the generator not to throw and to log.
        $generator->generate($qrCode, $this->channel('example.com'), QRCodeGeneratorInterface::FORMAT_SVG);
    }

    /**
     * @test
     */
    public function it_logs_a_warning_and_continues_when_embed_logo_requested_but_path_not_configured(): void
    {
        $qrCode = $this->qrCode('with-logo');
        $qrCode->setEmbedLogo(true);

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->warning(Argument::containingString('not configured'), Argument::cetera())
            ->shouldBeCalledOnce();

        $generator = new QRCodeGenerator($this->urlGenerator(), 1200, null, 60);
        $generator->setLogger($logger->reveal());

        $generator->generate($qrCode, $this->channel('example.com'), QRCodeGeneratorInterface::FORMAT_SVG);
    }

    /**
     * @test
     */
    public function it_logs_a_warning_and_continues_when_embed_logo_requested_but_file_missing(): void
    {
        $qrCode = $this->qrCode('with-missing-logo');
        $qrCode->setEmbedLogo(true);

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->warning(Argument::containingString('does not exist'), Argument::type('array'))
            ->shouldBeCalledOnce();

        $generator = new QRCodeGenerator($this->urlGenerator(), 1200, '/tmp/does-not-exist.png', 60);
        $generator->setLogger($logger->reveal());

        $generator->generate($qrCode, $this->channel('example.com'), QRCodeGeneratorInterface::FORMAT_SVG);
    }

    /**
     * @test
     */
    public function the_default_size_parameter_is_applied_to_raster_output(): void
    {
        $qrCode = $this->qrCode('size-test');
        $smallGenerator = new QRCodeGenerator($this->urlGenerator(), 200, null, 60);
        $largeGenerator = new QRCodeGenerator($this->urlGenerator(), 1200, null, 60);

        $small = $smallGenerator->generate($qrCode, $this->channel('example.com'), QRCodeGeneratorInterface::FORMAT_PNG);
        $large = $largeGenerator->generate($qrCode, $this->channel('example.com'), QRCodeGeneratorInterface::FORMAT_PNG);

        self::assertLessThan(
            strlen($large->getString()),
            strlen($small->getString()),
            'A larger configured size should produce a larger PNG blob.',
        );
    }

    /**
     * @test
     */
    public function it_builds_the_redirect_url_from_the_channel_hostname_regardless_of_the_router_context(): void
    {
        // The generator must not read or mutate the router's RequestContext (which reflects the
        // admin hostname). Point the context at admin.example.com, then verify the QR still
        // encodes the shop channel's hostname by re-rendering with a different channel and
        // confirming the output changes (same QR payload → same bytes; per-channel swap → diff).
        $urlGenerator = $this->urlGenerator();
        $urlGenerator->getContext()->setHost('admin.example.com');
        $urlGenerator->getContext()->setScheme('http');

        $generator = new QRCodeGenerator($urlGenerator, 1200, null, 60);
        $qrCode = $this->qrCode('summer');

        $shop = $generator->generate($qrCode, $this->channel('shop.example.com'), QRCodeGeneratorInterface::FORMAT_SVG);
        $store = $generator->generate($qrCode, $this->channel('store.example.com'), QRCodeGeneratorInterface::FORMAT_SVG);

        self::assertNotSame($shop->getString(), $store->getString());
        // Admin context is untouched afterwards.
        self::assertSame('admin.example.com', $urlGenerator->getContext()->getHost());
        self::assertSame('http', $urlGenerator->getContext()->getScheme());
    }

    private function buildGenerator(): QRCodeGenerator
    {
        return new QRCodeGenerator($this->urlGenerator(), 1200, null, 60);
    }

    private function urlGenerator(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('setono_sylius_qr_code_redirect', new Route('/qr/{slug}'));

        return new UrlGenerator($routes, new RequestContext());
    }

    private function qrCode(string $slug): QRCodeInterface
    {
        $qrCode = new QRCode();
        $qrCode->setSlug($slug);

        return $qrCode;
    }

    private function channel(string $hostname): ChannelInterface
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getHostname()->willReturn($hostname);
        $channel->getCode()->willReturn($hostname);

        return $channel->reveal();
    }
}
