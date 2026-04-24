<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Channel;

use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * Returns a channel to use when no explicit channel is available in the request context
 * (e.g. an admin downloading a QR PNG without picking a channel). The shipped implementation
 * (FirstEnabledChannelResolver) returns the first enabled channel surfaced by the channel
 * repository; adopting applications can bind a different implementation to this interface.
 */
interface DefaultChannelResolverInterface
{
    /**
     * @throws \RuntimeException when no enabled channel is available
     */
    public function getDefaultChannel(): ChannelInterface;
}
