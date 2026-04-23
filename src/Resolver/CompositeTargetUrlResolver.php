<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Resolver;

use League\Uri\Contracts\UriInterface;
use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;

/**
 * Composite resolver — dispatches to the first service tagged
 * `setono_sylius_qr_code.target_url_resolver` whose `supports()` returns true. UTM-parameter
 * appending is NOT done here; it is layered on top by {@see UtmTargetUrlResolver}, which
 * decorates this service via the `TargetUrlResolverInterface` alias.
 *
 * @extends CompositeService<TargetUrlResolverInterface>
 */
final class CompositeTargetUrlResolver extends CompositeService implements TargetUrlResolverInterface
{
    public function supports(QRCodeInterface $qrCode): bool
    {
        foreach ($this->services as $resolver) {
            if ($resolver->supports($qrCode)) {
                return true;
            }
        }

        return false;
    }

    public function resolve(QRCodeInterface $qrCode): UriInterface
    {
        foreach ($this->services as $resolver) {
            if ($resolver->supports($qrCode)) {
                return $resolver->resolve($qrCode);
            }
        }

        throw new UnsupportedQRCodeException($qrCode);
    }
}
