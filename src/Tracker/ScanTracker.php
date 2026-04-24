<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tracker;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusQRCodePlugin\Event\QRCodeScannedEvent;
use Setono\SyliusQRCodePlugin\Factory\QRCodeScanFactoryInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Subscribes to `QRCodeScannedEvent` to persist a `QRCodeScan` row per scan. The `track()`
 * method is still exposed on `ScanTrackerInterface` so callers that want to record a scan
 * without going through an event (batch imports, replays, tests) can call it directly.
 *
 * Adopting apps that want to substitute tracking can either decorate/replace the service bound
 * to `ScanTrackerInterface` (transparent — the shipped subscriber keeps using the replacement)
 * or turn off the shipped subscriber and register their own listener on `QRCodeScannedEvent`.
 */
final class ScanTracker implements ScanTrackerInterface, EventSubscriberInterface
{
    use ORMTrait;

    public function __construct(
        private readonly QRCodeScanFactoryInterface $qrCodeScanFactory,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            QRCodeScannedEvent::class => 'onQrCodeScanned',
        ];
    }

    public function onQrCodeScanned(QRCodeScannedEvent $event): void
    {
        $this->track($event->qrCode, $event->request);
    }

    public function track(QRCodeInterface $qrCode, Request $request): void
    {
        $scan = $this->qrCodeScanFactory->createFromRequest($qrCode, $request);

        $manager = $this->getManager($scan);
        $manager->persist($scan);
        $manager->flush();
    }
}
