<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Controller;

use Endroid\QrCode\Writer\Result\ResultInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Channel\DefaultChannelResolverInterface;
use Setono\SyliusQRCodePlugin\Controller\DownloadAction;
use Setono\SyliusQRCodePlugin\Generator\QRCodeGeneratorInterface;
use Setono\SyliusQRCodePlugin\Model\QRCode;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_streams_the_generated_image_with_mime_type_and_content_disposition(): void
    {
        $qrCode = $this->buildQRCode(42, 'winter-sale');

        $qrRepo = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrRepo->find(42)->willReturn($qrCode);

        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getCode()->willReturn('default');
        $channel->isEnabled()->willReturn(true);

        $channelRepo = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepo->findOneByCode('default')->willReturn($channel->reveal());

        $defaultChannelResolver = $this->prophesize(DefaultChannelResolverInterface::class);

        $result = $this->prophesize(ResultInterface::class);
        $result->getString()->willReturn('PNG-BYTES');
        $result->getMimeType()->willReturn('image/png');

        $generator = $this->prophesize(QRCodeGeneratorInterface::class);
        $generator->generate($qrCode, $channel->reveal(), 'png')->willReturn($result->reveal());

        $action = new DownloadAction(
            $qrRepo->reveal(),
            $channelRepo->reveal(),
            $defaultChannelResolver->reveal(),
            $generator->reveal(),
        );

        $response = $action(new Request(), 42, 'png', 'default');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('PNG-BYTES', $response->getContent());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame(
            'attachment; filename=winter-sale-default.png',
            $response->headers->get('Content-Disposition'),
        );
        self::assertNotNull($response->getEtag());
    }

    /**
     * @test
     */
    public function it_uses_the_default_channel_resolver_when_the_channel_segment_is_omitted(): void
    {
        $qrCode = $this->buildQRCode(1, 'spring');

        $qrRepo = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrRepo->find(1)->willReturn($qrCode);

        $default = $this->prophesize(ChannelInterface::class);
        $default->getCode()->willReturn('default');

        $defaultChannelResolver = $this->prophesize(DefaultChannelResolverInterface::class);
        $defaultChannelResolver->getDefaultChannel()->willReturn($default->reveal());

        $channelRepo = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepo->findOneByCode(Argument::any())->shouldNotBeCalled();

        $result = $this->prophesize(ResultInterface::class);
        $result->getString()->willReturn('SVG-BYTES');
        $result->getMimeType()->willReturn('image/svg+xml');

        $generator = $this->prophesize(QRCodeGeneratorInterface::class);
        $generator->generate($qrCode, $default->reveal(), 'svg')->willReturn($result->reveal());

        $action = new DownloadAction(
            $qrRepo->reveal(),
            $channelRepo->reveal(),
            $defaultChannelResolver->reveal(),
            $generator->reveal(),
        );

        $response = $action(new Request(), 1, 'svg');

        // No channel code suffix when resolved by the default resolver.
        self::assertSame(
            'attachment; filename=spring.svg',
            $response->headers->get('Content-Disposition'),
        );
    }

    /**
     * @test
     */
    public function it_returns_304_when_the_request_if_none_match_matches_the_etag(): void
    {
        $qrCode = $this->buildQRCode(7, 'cached', new \DateTimeImmutable('2026-01-01 00:00:00'));

        $qrRepo = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrRepo->find(7)->willReturn($qrCode);

        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getCode()->willReturn('default');
        $channel->isEnabled()->willReturn(true);

        $channelRepo = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepo->findOneByCode('default')->willReturn($channel->reveal());

        $result = $this->prophesize(ResultInterface::class);
        $result->getString()->willReturn('BYTES');
        $result->getMimeType()->willReturn('image/png');

        // Warm-up call uses the generator once; the If-None-Match call must not.
        $generator = $this->prophesize(QRCodeGeneratorInterface::class);
        $generator->generate(Argument::cetera())->willReturn($result->reveal())->shouldBeCalledOnce();

        $action = new DownloadAction(
            $qrRepo->reveal(),
            $channelRepo->reveal(),
            $this->prophesize(DefaultChannelResolverInterface::class)->reveal(),
            $generator->reveal(),
        );

        $warm = $action(new Request(), 7, 'png', 'default');
        $etag = $warm->getEtag();
        self::assertNotNull($etag);

        $conditional = new Request();
        $conditional->headers->set('If-None-Match', $etag);

        $response = $action($conditional, 7, 'png', 'default');

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', (string) $response->getContent());
    }

    /**
     * @test
     */
    public function it_404s_when_the_qr_code_does_not_exist(): void
    {
        $qrRepo = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrRepo->find(999)->willReturn(null);

        $action = new DownloadAction(
            $qrRepo->reveal(),
            $this->prophesize(ChannelRepositoryInterface::class)->reveal(),
            $this->prophesize(DefaultChannelResolverInterface::class)->reveal(),
            $this->prophesize(QRCodeGeneratorInterface::class)->reveal(),
        );

        $this->expectException(NotFoundHttpException::class);
        $action(new Request(), 999);
    }

    /**
     * @test
     */
    public function it_404s_when_the_explicit_channel_code_does_not_resolve(): void
    {
        $qrCode = $this->buildQRCode(1, 'x');

        $qrRepo = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrRepo->find(1)->willReturn($qrCode);

        $channelRepo = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepo->findOneByCode('unknown')->willReturn(null);

        $action = new DownloadAction(
            $qrRepo->reveal(),
            $channelRepo->reveal(),
            $this->prophesize(DefaultChannelResolverInterface::class)->reveal(),
            $this->prophesize(QRCodeGeneratorInterface::class)->reveal(),
        );

        $this->expectException(NotFoundHttpException::class);
        $action(new Request(), 1, 'png', 'unknown');
    }

    /**
     * @test
     */
    public function it_404s_when_the_explicit_channel_is_disabled(): void
    {
        $qrCode = $this->buildQRCode(1, 'x');

        $qrRepo = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrRepo->find(1)->willReturn($qrCode);

        $channel = $this->prophesize(ChannelInterface::class);
        $channel->isEnabled()->willReturn(false);

        $channelRepo = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepo->findOneByCode('disabled')->willReturn($channel->reveal());

        $action = new DownloadAction(
            $qrRepo->reveal(),
            $channelRepo->reveal(),
            $this->prophesize(DefaultChannelResolverInterface::class)->reveal(),
            $this->prophesize(QRCodeGeneratorInterface::class)->reveal(),
        );

        $this->expectException(NotFoundHttpException::class);
        $action(new Request(), 1, 'png', 'disabled');
    }

    /**
     * @test
     */
    public function it_rejects_an_unsupported_format(): void
    {
        $action = new DownloadAction(
            $this->prophesize(QRCodeRepositoryInterface::class)->reveal(),
            $this->prophesize(ChannelRepositoryInterface::class)->reveal(),
            $this->prophesize(DefaultChannelResolverInterface::class)->reveal(),
            $this->prophesize(QRCodeGeneratorInterface::class)->reveal(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $action(new Request(), 1, 'bmp');
    }

    /**
     * @test
     */
    public function different_channels_produce_different_etags_for_the_same_qr_code(): void
    {
        $qrCode = $this->buildQRCode(1, 'spring');

        $qrRepo = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrRepo->find(1)->willReturn($qrCode);

        $a = $this->prophesize(ChannelInterface::class);
        $a->getCode()->willReturn('us');
        $a->isEnabled()->willReturn(true);
        $b = $this->prophesize(ChannelInterface::class);
        $b->getCode()->willReturn('dk');
        $b->isEnabled()->willReturn(true);

        $channelRepo = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepo->findOneByCode('us')->willReturn($a->reveal());
        $channelRepo->findOneByCode('dk')->willReturn($b->reveal());

        $result = $this->prophesize(ResultInterface::class);
        $result->getString()->willReturn('BYTES');
        $result->getMimeType()->willReturn('image/png');

        $generator = $this->prophesize(QRCodeGeneratorInterface::class);
        $generator->generate(Argument::cetera())->willReturn($result->reveal());

        $action = new DownloadAction(
            $qrRepo->reveal(),
            $channelRepo->reveal(),
            $this->prophesize(DefaultChannelResolverInterface::class)->reveal(),
            $generator->reveal(),
        );

        $etagA = $action(new Request(), 1, 'png', 'us')->getEtag();
        $etagB = $action(new Request(), 1, 'png', 'dk')->getEtag();

        self::assertNotSame($etagA, $etagB);
    }

    /**
     * Build a QR code with a fixed id/slug/updatedAt (the entity has no id setter, so we write
     * to the protected property via reflection — PHP 8.1+ no longer requires setAccessible).
     */
    private function buildQRCode(int $id, string $slug, ?\DateTimeImmutable $updatedAt = null): QRCode
    {
        $qrCode = new QRCode();
        $qrCode->setSlug($slug);
        $qrCode->setUpdatedAt($updatedAt ?? new \DateTimeImmutable('2026-04-23 10:00:00'));

        (new \ReflectionProperty(QRCode::class, 'id'))->setValue($qrCode, $id);

        return $qrCode;
    }
}
