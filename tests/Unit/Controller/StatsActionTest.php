<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Controller\StatsAction;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeScanRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class StatsActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_renders_the_stats_template_with_the_aggregated_payload(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();

        $qrCodeRepository = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrCodeRepository->find(42)->willReturn($qrCode);

        // Repo returns an already-zero-filled map (that's its contract — see
        // QRCodeScanRepositoryInterface::countDailyBuckets). The controller passes it through
        // unchanged; we only assert the passthrough + the other payload keys.
        $buckets = ['2026-04-20' => 5, '2026-04-21' => 0, '2026-04-22' => 8];

        $scanRepository = $this->prophesize(QRCodeScanRepositoryInterface::class);
        $scanRepository->countForQrCode($qrCode)->willReturn(123);
        $scanRepository->countForQrCodeSince($qrCode, Argument::type(\DateTimeImmutable::class))
            ->willReturn(3, 10, 50);
        $scanRepository->countDailyBuckets($qrCode, Argument::cetera())
            ->willReturn($buckets);
        $scanRepository->findRecentForQrCode($qrCode, Argument::cetera())
            ->willReturn([]);

        $twig = $this->prophesize(Environment::class);
        $twig->render(
            '@SetonoSyliusQRCodePlugin/admin/qr_code/stats.html.twig',
            Argument::that(function (array $context) use ($qrCode, $buckets): bool {
                self::assertSame($qrCode, $context['qr_code']);
                self::assertSame(123, $context['total_scans']);
                self::assertSame(30, $context['range_days']);
                self::assertSame($buckets, $context['daily_buckets']);
                self::assertSame(['2026-04-20', '2026-04-21', '2026-04-22'], $context['chart_labels']);
                self::assertSame([5, 0, 8], $context['chart_data']);
                self::assertArrayHasKey('scans_last_7_days', $context);
                self::assertArrayHasKey('scans_last_30_days', $context);
                self::assertArrayHasKey('scans_last_90_days', $context);

                return true;
            }),
        )->willReturn('<rendered/>');

        $action = new StatsAction(
            $qrCodeRepository->reveal(),
            $scanRepository->reveal(),
            $twig->reveal(),
        );

        $response = $action(42);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<rendered/>', $response->getContent());
    }

    /**
     * @test
     */
    public function it_404s_when_the_qr_code_does_not_exist(): void
    {
        $qrCodeRepository = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrCodeRepository->find(999)->willReturn(null);

        $action = new StatsAction(
            $qrCodeRepository->reveal(),
            $this->prophesize(QRCodeScanRepositoryInterface::class)->reveal(),
            $this->prophesize(Environment::class)->reveal(),
        );

        $this->expectException(NotFoundHttpException::class);
        $action(999);
    }
}
