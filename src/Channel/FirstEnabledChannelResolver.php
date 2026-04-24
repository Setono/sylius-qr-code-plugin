<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Channel;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

final class FirstEnabledChannelResolver implements DefaultChannelResolverInterface
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
    }

    public function getDefaultChannel(): ChannelInterface
    {
        foreach ($this->channelRepository->findEnabled() as $channel) {
            return $channel;
        }

        throw new \RuntimeException('Cannot resolve a default channel: no enabled channels exist.');
    }
}
