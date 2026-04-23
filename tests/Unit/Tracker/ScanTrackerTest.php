<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Tracker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Factory\QRCodeScanFactoryInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScan;
use Setono\SyliusQRCodePlugin\Tracker\ScanTracker;
use Symfony\Component\HttpFoundation\Request;

final class ScanTrackerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_asks_the_factory_for_a_scan_then_persists_and_flushes_it(): void
    {
        $qrCode = $this->prophesize(QRCodeInterface::class)->reveal();
        $request = new Request();
        $scan = new QRCodeScan();

        $factory = $this->prophesize(QRCodeScanFactoryInterface::class);
        $factory->createFromRequest($qrCode, $request)->shouldBeCalledOnce()->willReturn($scan);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->persist($scan)->shouldBeCalledOnce();
        $entityManager->flush()->shouldBeCalledOnce();

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass($scan::class)->willReturn($entityManager->reveal());

        (new ScanTracker($factory->reveal(), $managerRegistry->reveal()))->track($qrCode, $request);
    }
}
