<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Resolver;

use League\Uri\Contracts\UriInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;

/**
 * Decorates the composite resolver (or any {@see TargetUrlResolverInterface}) and appends the
 * entity's snapshotted UTM parameters (source / medium / campaign) to the resolved URL.
 *
 * Merging rules:
 * - entity values override keys already present on the target URL,
 * - null entity fields are skipped — no empty query parameter added.
 */
final class UtmTargetUrlResolver implements TargetUrlResolverInterface
{
    public function __construct(
        private readonly TargetUrlResolverInterface $decoratedResolver,
    ) {
    }

    public function supports(QRCodeInterface $qrCode): bool
    {
        return $this->decoratedResolver->supports($qrCode);
    }

    public function resolve(QRCodeInterface $qrCode): UriInterface
    {
        $uri = $this->decoratedResolver->resolve($qrCode);

        $utm = array_filter([
            'utm_source' => $qrCode->getUtmSource(),
            'utm_medium' => $qrCode->getUtmMedium(),
            'utm_campaign' => $qrCode->getUtmCampaign(),
        ], static fn (?string $v): bool => null !== $v);

        if ([] === $utm) {
            return $uri;
        }

        $existing = [];
        $existingQuery = $uri->getQuery();
        if (null !== $existingQuery && '' !== $existingQuery) {
            parse_str($existingQuery, $existing);
        }

        // Entity values override existing keys; nested array-style query params (e.g.
        // `filters[type]=red`) round-trip via parse_str + http_build_query.
        $merged = array_merge($existing, $utm);
        $newQuery = http_build_query($merged, '', '&', \PHP_QUERY_RFC3986);

        return $uri->withQuery('' === $newQuery ? null : $newQuery);
    }
}
