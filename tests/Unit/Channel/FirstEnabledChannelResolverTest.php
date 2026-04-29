<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Channel;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Channel\FirstEnabledChannelResolver;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class FirstEnabledChannelResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_returns_the_first_channel_surfaced_by_the_repository(): void
    {
        $first = $this->prophesize(ChannelInterface::class);
        $first->getCode()->willReturn('US');

        $second = $this->prophesize(ChannelInterface::class);
        $second->getCode()->willReturn('DK');

        $repository = $this->prophesize(ChannelRepositoryInterface::class);
        $repository->findBy(['enabled' => true], null, 1)->willReturn([$first->reveal(), $second->reveal()]);

        $resolved = (new FirstEnabledChannelResolver($repository->reveal()))->getDefaultChannel();

        self::assertSame('US', $resolved->getCode());
    }

    /**
     * @test
     */
    public function it_throws_a_runtime_exception_when_no_enabled_channels_exist(): void
    {
        $repository = $this->prophesize(ChannelRepositoryInterface::class);
        $repository->findBy(['enabled' => true], null, 1)->willReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no enabled channels/');

        (new FirstEnabledChannelResolver($repository->reveal()))->getDefaultChannel();
    }
}
