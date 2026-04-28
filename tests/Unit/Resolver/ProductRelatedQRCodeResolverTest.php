<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Resolver;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Exception\UnsupportedQRCodeException;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Resolver\ProductRelatedQRCodeResolver;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductRelatedQRCodeResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_rejects_non_product_related_qr_codes_with_unsupported_exception(): void
    {
        $this->expectException(UnsupportedQRCodeException::class);

        $this->buildResolver()->resolve(new TargetUrlQRCode());
    }

    /**
     * @test
     */
    public function it_generates_the_shop_product_url_on_the_request_channel(): void
    {
        $locale = $this->prophesize(LocaleInterface::class);
        $locale->getCode()->willReturn('en_US');

        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getDefaultLocale()->willReturn($locale->reveal());
        $channel->getCode()->willReturn('default');

        $translation = $this->prophesize(ProductTranslationInterface::class);
        $translation->getSlug()->willReturn('summer-t-shirt');

        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(true);
        $product->getChannels()->willReturn(new ArrayCollection([$channel->reveal()]));
        $product->getTranslation('en_US')->willReturn($translation->reveal());

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel->reveal());

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate(
            'sylius_shop_product_show',
            ['slug' => 'summer-t-shirt', '_locale' => 'en_US'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        )->willReturn('https://shop.example.com/en_US/products/summer-t-shirt');

        $qrCode = new ProductRelatedQRCode();
        $qrCode->setProduct($product->reveal());

        $resolver = new ProductRelatedQRCodeResolver($channelContext->reveal(), $urlGenerator->reveal());

        self::assertSame(
            'https://shop.example.com/en_US/products/summer-t-shirt',
            (string) $resolver->resolve($qrCode),
        );
    }

    /**
     * @test
     */
    public function it_raises_logic_exception_when_the_qr_has_no_product(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/no product/');

        $this->buildResolver()->resolve(new ProductRelatedQRCode());
    }

    /**
     * @test
     */
    public function it_raises_logic_exception_when_the_product_is_disabled(): void
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(false);

        $qrCode = new ProductRelatedQRCode();
        $qrCode->setProduct($product->reveal());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/disabled/');

        $this->buildResolver()->resolve($qrCode);
    }

    /**
     * @test
     */
    public function it_raises_logic_exception_when_no_channel_is_in_context(): void
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(true);

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willThrow(new ChannelNotFoundException());

        $qrCode = new ProductRelatedQRCode();
        $qrCode->setProduct($product->reveal());

        $resolver = new ProductRelatedQRCodeResolver(
            $channelContext->reveal(),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/channel in the request context/');

        $resolver->resolve($qrCode);
    }

    /**
     * @test
     */
    public function it_raises_logic_exception_when_the_product_is_not_enabled_on_the_request_channel(): void
    {
        $resolvedChannel = $this->prophesize(ChannelInterface::class);
        $resolvedChannel->getCode()->willReturn('us');

        $otherChannel = $this->prophesize(ChannelInterface::class)->reveal();

        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(true);
        $product->getChannels()->willReturn(new ArrayCollection([$otherChannel]));
        $product->getCode()->willReturn('SHIRT-1');

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($resolvedChannel->reveal());

        $qrCode = new ProductRelatedQRCode();
        $qrCode->setProduct($product->reveal());

        $resolver = new ProductRelatedQRCodeResolver(
            $channelContext->reveal(),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/not enabled on channel "us"/');

        $resolver->resolve($qrCode);
    }

    private function buildResolver(): ProductRelatedQRCodeResolver
    {
        return new ProductRelatedQRCodeResolver(
            $this->prophesize(ChannelContextInterface::class)->reveal(),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
        );
    }
}
