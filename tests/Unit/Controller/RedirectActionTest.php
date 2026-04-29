<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Setono\SyliusQRCodePlugin\Controller\RedirectAction;
use Setono\SyliusQRCodePlugin\Event\QRCodeScannedEvent;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Setono\SyliusQRCodePlugin\Resolver\TargetUrlResolverInterface;
use Setono\SyliusQRCodePlugin\Resolver\UriFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RedirectActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_dispatches_the_scanned_event_and_returns_a_redirect(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class);
        $qrCode->getId()->willReturn(42);

        $qrCodeRepository = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrCodeRepository->findOneEnabledBySlug('winter-sale')->willReturn($qrCode->reveal());

        $resolver = $this->prophesize(TargetUrlResolverInterface::class);
        $resolver->resolve($qrCode->reveal())->willReturn(UriFactory::fromString('https://example.com/winter'));

        $dispatcher = $this->prophesize(EventDispatcherInterface::class);
        $dispatcher->dispatch(Argument::that(function (object $event) use ($qrCode): bool {
            self::assertInstanceOf(QRCodeScannedEvent::class, $event);
            self::assertSame($qrCode->reveal(), $event->qrCode);

            return true;
        }))->shouldBeCalledOnce()->willReturnArgument(0);

        $action = new RedirectAction(
            $qrCodeRepository->reveal(),
            $resolver->reveal(),
            $dispatcher->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal(),
            redirectType: 307,
        );

        $response = $action(new Request(), 'winter-sale');

        self::assertSame(307, $response->getStatusCode());
        self::assertSame('https://example.com/winter', $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function it_404s_when_no_enabled_qr_code_matches_the_slug(): void
    {
        $qrCodeRepository = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrCodeRepository->findOneEnabledBySlug('missing')->willReturn(null);

        $action = new RedirectAction(
            $qrCodeRepository->reveal(),
            $this->prophesize(TargetUrlResolverInterface::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal(),
            redirectType: 302,
        );

        $this->expectException(NotFoundHttpException::class);
        $action(new Request(), 'missing');
    }

    /**
     * @test
     */
    public function it_404s_when_the_resolver_rejects_the_qr_code(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();

        $qrCodeRepository = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrCodeRepository->findOneEnabledBySlug('x')->willReturn($qrCode);

        $resolver = $this->prophesize(TargetUrlResolverInterface::class);
        $resolver->resolve($qrCode)->willThrow(new UnsupportedQRCodeException($qrCode));

        $action = new RedirectAction(
            $qrCodeRepository->reveal(),
            $resolver->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal(),
            redirectType: 302,
        );

        $this->expectException(NotFoundHttpException::class);
        $action(new Request(), 'x');
    }

    /**
     * @test
     */
    public function a_listener_exception_is_logged_and_does_not_block_the_redirect(): void
    {
        // A third-party listener throwing during dispatch must not prevent the user from
        // reaching the target URL — this is the contract the spec locks in ("Listener
        // exception does not block the redirect").
        $qrCode = $this->prophesize(QRCodeInterface::class);
        $qrCode->getId()->willReturn(7);

        $qrCodeRepository = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrCodeRepository->findOneEnabledBySlug('flaky')->willReturn($qrCode->reveal());

        $resolver = $this->prophesize(TargetUrlResolverInterface::class);
        $resolver->resolve($qrCode->reveal())->willReturn(UriFactory::fromString('https://example.com/target'));

        $dispatcher = $this->prophesize(EventDispatcherInterface::class);
        $dispatcher->dispatch(Argument::any())->willThrow(new \RuntimeException('listener blew up'));

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error(
            Argument::containingString('Failed to dispatch'),
            Argument::that(static fn (array $ctx): bool => 7 === $ctx['qr_code_id'] && 'flaky' === $ctx['slug']),
        )->shouldBeCalledOnce();

        $response = (new RedirectAction(
            $qrCodeRepository->reveal(),
            $resolver->reveal(),
            $dispatcher->reveal(),
            $logger->reveal(),
            redirectType: 302,
        ))(new Request(), 'flaky');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://example.com/target', $response->getTargetUrl());
    }
}
