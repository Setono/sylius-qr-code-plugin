<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Resolver;

use League\Uri\Contracts\UriInterface;
use League\Uri\Uri;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

/**
 * Resolves the redirect URL for product-linked QR codes by generating an absolute
 * `sylius_shop_product_show` URL on the request's current Sylius channel, using the channel's
 * default-locale product translation slug.
 *
 * Availability checks live here, not in the redirect action, so every consumer of the
 * resolver (redirect, potential preview endpoints, etc.) gets the same guarantees. All
 * "this cannot be served" cases throw {@see \LogicException}, which the redirect action
 * turns into a 404.
 */
final class ProductRelatedQRCodeResolver implements TargetUrlResolverInterface
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @phpstan-assert-if-true ProductRelatedQRCodeInterface $qrCode
     */
    public function supports(QRCodeInterface $qrCode): bool
    {
        return $qrCode instanceof ProductRelatedQRCodeInterface;
    }

    public function resolve(QRCodeInterface $qrCode): UriInterface
    {
        if (!$this->supports($qrCode)) {
            throw new UnsupportedQRCodeException($qrCode);
        }

        $product = $qrCode->getProduct();

        if (!$product instanceof ProductInterface) {
            throw new \LogicException('Product QR code has no product attached.');
        }

        if (!$product->isEnabled()) {
            throw new \LogicException('Product is disabled.');
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException $exception) {
            throw new \LogicException(
                'Cannot resolve target URL for a product QR code without a channel in the request context.',
                previous: $exception,
            );
        }

        Assert::isInstanceOf($channel, ChannelInterface::class);

        if (!$product->getChannels()->contains($channel)) {
            throw new \LogicException(sprintf(
                'Product "%s" is not enabled on channel "%s".',
                (string) $product->getCode(),
                (string) $channel->getCode(),
            ));
        }

        $defaultLocale = $channel->getDefaultLocale();
        Assert::notNull($defaultLocale, 'Channel must have a default locale.');
        $localeCode = $defaultLocale->getCode();
        Assert::notNull($localeCode);

        $translation = $product->getTranslation($localeCode);
        $slug = $translation->getSlug();
        Assert::notNull($slug, 'Product translation must have a slug for the channel default locale.');

        return Uri::new($this->urlGenerator->generate(
            'sylius_shop_product_show',
            [
                'slug' => $slug,
                '_locale' => $localeCode,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }
}
