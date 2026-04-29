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
        // Going through the Doctrine `findBy()` rather than Sylius's `findEnabled()`
        // because the latter is only declared on ChannelRepositoryInterface since
        // Sylius 1.11, and not every downstream wires a concrete repository that
        // implements it (issue: prod EntityRepository::__call → BadMethodCallException).
        $channels = $this->channelRepository->findBy(['enabled' => true], null, 1);
        foreach ($channels as $channel) {
            return $channel;
        }

        throw new \RuntimeException('Cannot resolve a default channel: no enabled channels exist.');
    }
}
