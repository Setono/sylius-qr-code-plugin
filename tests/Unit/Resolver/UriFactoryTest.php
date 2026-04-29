<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Setono\SyliusQRCodePlugin\Resolver\UriFactory;

final class UriFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_parses_the_components_of_a_well_formed_url(): void
    {
        $uri = UriFactory::fromString('https://example.com/path?q=1');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('q=1', $uri->getQuery());
    }

    /**
     * @test
     */
    public function it_round_trips_the_input_to_string(): void
    {
        $url = 'https://example.com/winter?utm_source=qr';

        self::assertSame($url, (string) UriFactory::fromString($url));
    }
}
