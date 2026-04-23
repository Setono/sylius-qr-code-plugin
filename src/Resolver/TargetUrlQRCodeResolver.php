<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Resolver;

use League\Uri\Contracts\UriInterface;
use League\Uri\Uri;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;
use Webmozart\Assert\Assert;

/**
 * Resolves the redirect URL for QR codes that carry a verbatim target URL.
 */
final class TargetUrlQRCodeResolver implements TargetUrlResolverInterface
{
    /**
     * @phpstan-assert-if-true TargetUrlQRCodeInterface $qrCode
     */
    public function supports(QRCodeInterface $qrCode): bool
    {
        return $qrCode instanceof TargetUrlQRCodeInterface;
    }

    public function resolve(QRCodeInterface $qrCode): UriInterface
    {
        if (!$this->supports($qrCode)) {
            throw new UnsupportedQRCodeException($qrCode);
        }

        $url = $qrCode->getTargetUrl();
        Assert::string($url, 'A persisted TargetUrlQRCode must have a target URL.');

        return Uri::new($url);
    }
}
