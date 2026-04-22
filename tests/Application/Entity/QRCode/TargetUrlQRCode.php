<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Application\Entity\QRCode;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode as BaseTargetUrlQRCode;

#[ORM\Entity]
class TargetUrlQRCode extends BaseTargetUrlQRCode
{
}
