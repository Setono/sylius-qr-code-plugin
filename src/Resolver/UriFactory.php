<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Resolver;

use League\Uri\Contracts\UriInterface;
use League\Uri\Uri;

/**
 * Cross-version factory for parsing a string into a {@see UriInterface} that works on
 * both league/uri ^6.8 (which exposes `Uri::createFromString()`) and ^7.8 (which renamed
 * the same operation to `Uri::new()`). The variable-static-method syntax is what keeps
 * the call working on either matrix — neither literal symbol appears in the analysed
 * source. The PHPStan warnings about an always-true `method_exists()` check and a
 * dynamic static-method dispatch are silenced for this file in `phpstan.neon`.
 *
 * @internal
 */
final class UriFactory
{
    public static function fromString(string $url): UriInterface
    {
        $method = method_exists(Uri::class, 'new') ? 'new' : 'createFromString';

        return Uri::{$method}($url);
    }
}
