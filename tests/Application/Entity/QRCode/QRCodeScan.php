<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Application\Entity\QRCode;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusQRCodePlugin\Model\QRCodeScan as BaseQRCodeScan;

#[ORM\Entity]
#[ORM\Table(name: 'setono_sylius_qr_code__qr_code_scan')]
class QRCodeScan extends BaseQRCodeScan
{
}
