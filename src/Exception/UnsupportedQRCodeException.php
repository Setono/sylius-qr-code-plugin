<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Exception;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;

/**
 * Signals that a {@see TargetUrlResolverInterface} implementation cannot handle the given QR
 * code's subtype. The composite resolver catches this and tries the next tagged resolver.
 * Other exception types (typically {@see \LogicException}) bubble up as real errors.
 */
final class UnsupportedQRCodeException extends \RuntimeException
{
    public function __construct(QRCodeInterface $qrCode, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('No registered resolver supports QR code subtype "%s".', $qrCode::class),
            previous: $previous,
        );
    }
}
