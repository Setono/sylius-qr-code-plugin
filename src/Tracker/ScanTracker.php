<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tracker;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusQRCodePlugin\Factory\QRCodeScanFactoryInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Symfony\Component\HttpFoundation\Request;

final class ScanTracker implements ScanTrackerInterface
{
    use ORMTrait;

    public function __construct(
        private readonly QRCodeScanFactoryInterface $qrCodeScanFactory,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function track(QRCodeInterface $qrCode, Request $request): void
    {
        $scan = $this->qrCodeScanFactory->createFromRequest($qrCode, $request);

        $manager = $this->getManager($scan);
        $manager->persist($scan);
        $manager->flush();
    }
}
