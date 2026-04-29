<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Repository;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<QRCodeInterface>
 */
interface QRCodeRepositoryInterface extends RepositoryInterface
{
    public function findOneEnabledBySlug(string $slug): ?QRCodeInterface;

    public function findOneBySlug(string $slug): ?QRCodeInterface;

    public function getScansCount(QRCodeInterface $qrCode): int;
}
