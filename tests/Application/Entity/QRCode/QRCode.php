<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Application\Entity\QRCode;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusQRCodePlugin\Model\QRCode as BaseQRCode;

#[ORM\Entity]
#[ORM\Table(name: 'setono_sylius_qr_code__qr_code')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 20)]
#[ORM\DiscriminatorMap([
    'product' => ProductRelatedQRCode::class,
    'target_url' => TargetUrlQRCode::class,
])]
class QRCode extends BaseQRCode
{
}
