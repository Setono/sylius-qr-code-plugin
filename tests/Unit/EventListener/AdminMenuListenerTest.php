<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\EventListener;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusQRCodePlugin\EventListener\AdminMenuListener;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_adds_a_qr_codes_entry_under_the_marketing_section(): void
    {
        $qrCodeChild = $this->prophesize(ItemInterface::class);
        $qrCodeChild->setLabel('setono_sylius_qr_code.ui.qr_codes')->shouldBeCalledOnce()->willReturn($qrCodeChild);
        $qrCodeChild->setLabelAttribute('icon', 'qrcode')->shouldBeCalledOnce()->willReturn($qrCodeChild);

        $marketing = $this->prophesize(ItemInterface::class);
        $marketing
            ->addChild('setono_sylius_qr_code', [
                'route' => 'setono_sylius_qr_code_admin_qr_code_index',
            ])
            ->shouldBeCalledOnce()
            ->willReturn($qrCodeChild)
        ;

        $event = $this->buildEvent($marketing);

        (new AdminMenuListener())($event);
    }

    /**
     * @test
     */
    public function it_is_a_no_op_when_the_marketing_section_is_absent(): void
    {
        $root = $this->prophesize(ItemInterface::class);
        $root->getChild('marketing')->shouldBeCalledOnce()->willReturn(null);

        $factory = $this->prophesize(FactoryInterface::class);
        $event = new MenuBuilderEvent($factory->reveal(), $root->reveal());

        (new AdminMenuListener())($event);

        // Test succeeds if the listener doesn't touch any child when marketing is missing —
        // no addChild call was expected on $root.
        $this->addToAssertionCount(1);
    }

    /**
     * @param ObjectProphecy<ItemInterface> $marketing
     */
    private function buildEvent(ObjectProphecy $marketing): MenuBuilderEvent
    {
        $root = $this->prophesize(ItemInterface::class);
        $root->getChild('marketing')->shouldBeCalledOnce()->willReturn($marketing->reveal());

        $factory = $this->prophesize(FactoryInterface::class);

        return new MenuBuilderEvent($factory->reveal(), $root->reveal());
    }
}
