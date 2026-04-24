<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Generator;

use Endroid\QrCode\Writer\Result\ResultInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

interface QRCodeGeneratorInterface
{
    public const FORMAT_PNG = 'png';

    public const FORMAT_SVG = 'svg';

    public const FORMAT_PDF = 'pdf';

    public const FORMATS = [
        self::FORMAT_PNG,
        self::FORMAT_SVG,
        self::FORMAT_PDF,
    ];

    /**
     * $format MUST be one of self::FORMATS; passing anything else raises InvalidArgumentException
     * (runtime-checked, not type-enforced, so callers building format strings from route params
     * or user input don't have to pre-narrow the type).
     *
     * @param positive-int|null $size optional override; falls back to the configured default
     *
     * @throws \InvalidArgumentException when $format is not one of self::FORMATS
     */
    public function generate(
        QRCodeInterface $qrCode,
        ChannelInterface $channel,
        string $format = self::FORMAT_PNG,
        ?int $size = null,
    ): ResultInterface;
}
