<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Factory\TargetUrlQRCodeFactory;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Sylius\Resource\Factory\FactoryInterface;

final class TargetUrlQRCodeFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_delegates_to_the_decorated_factory_and_applies_defaults(): void
    {
        $entity = new TargetUrlQRCode();

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $decoratedFactory->createNew()->shouldBeCalledOnce()->willReturn($entity);

        $factory = new TargetUrlQRCodeFactory(
            $decoratedFactory->reveal(),
            defaultUtmSource: 'qr',
            defaultUtmMedium: 'qrcode',
        );

        $result = $factory->createNew();

        self::assertSame($entity, $result);
        self::assertSame('qr', $result->getUtmSource());
        self::assertSame('qrcode', $result->getUtmMedium());
    }

    /**
     * @test
     */
    public function it_leaves_utm_campaign_untouched(): void
    {
        $entity = new TargetUrlQRCode();

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $decoratedFactory->createNew()->willReturn($entity);

        $factory = new TargetUrlQRCodeFactory(
            $decoratedFactory->reveal(),
            defaultUtmSource: 'qr',
            defaultUtmMedium: 'qrcode',
        );

        self::assertNull($factory->createNew()->getUtmCampaign());
    }

    /**
     * @test
     */
    public function it_propagates_null_utm_defaults(): void
    {
        $entity = new TargetUrlQRCode();

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $decoratedFactory->createNew()->willReturn($entity);

        $factory = new TargetUrlQRCodeFactory(
            $decoratedFactory->reveal(),
            defaultUtmSource: null,
            defaultUtmMedium: null,
        );

        $result = $factory->createNew();

        self::assertNull($result->getUtmSource());
        self::assertNull($result->getUtmMedium());
    }

    /**
     * @test
     */
    public function it_throws_when_the_decorated_factory_returns_an_unexpected_type(): void
    {
        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $decoratedFactory->createNew()->willReturn(new \stdClass());

        $factory = new TargetUrlQRCodeFactory(
            $decoratedFactory->reveal(),
            defaultUtmSource: null,
            defaultUtmMedium: null,
        );

        $this->expectException(\InvalidArgumentException::class);

        $factory->createNew();
    }
}
