<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Resolver;

use League\Uri\Contracts\UriInterface;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;

interface TargetUrlResolverInterface
{
    /**
     * Reports whether this resolver knows how to build a target URL for the given QR code.
     * The composite implementation uses this to pick the first supporting subtype resolver
     * tagged `setono_sylius_qr_code.target_url_resolver` without provoking exceptions.
     */
    public function supports(QRCodeInterface $qrCode): bool;

    /**
     * Resolves the absolute URL a QR code should redirect to. A decorator
     * ({@see UtmTargetUrlResolver}) appends the entity's snapshotted UTM parameters on top.
     *
     * @throws UnsupportedQRCodeException if this resolver does not support the QR code's subtype
     *                                    (callers can use {@see self::supports()} to avoid it)
     * @throws \LogicException            on runtime errors a supporting resolver cannot recover from
     */
    public function resolve(QRCodeInterface $qrCode): UriInterface;
}
