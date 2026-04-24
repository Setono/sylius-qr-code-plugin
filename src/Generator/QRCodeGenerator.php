<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Generator;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Builder\BuilderInterface;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PdfWriter;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class QRCodeGenerator implements QRCodeGeneratorInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    /**
     * Maps the entity's stored error-correction letter to the endroid enum. The entity's
     * "Auto" value is resolved to H/M at form-submit time, so it never reaches the generator.
     */
    private const ERROR_CORRECTION_LEVELS = [
        QRCodeInterface::ERROR_CORRECTION_LEVEL_LOW => ErrorCorrectionLevel::Low,
        QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM => ErrorCorrectionLevel::Medium,
        QRCodeInterface::ERROR_CORRECTION_LEVEL_QUARTILE => ErrorCorrectionLevel::Quartile,
        QRCodeInterface::ERROR_CORRECTION_LEVEL_HIGH => ErrorCorrectionLevel::High,
    ];

    /**
     * @param positive-int $defaultSize default edge length in pixels (applies to raster formats)
     * @param int<0, 100> $logoSizePercentage logo width as percentage of QR edge length
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly int $defaultSize,
        private readonly ?string $logoPath,
        private readonly int $logoSizePercentage,
    ) {
        // Seeded so the generator is safe to use before setLogger() fires (tests, manual calls).
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function generate(
        QRCodeInterface $qrCode,
        ChannelInterface $channel,
        string $format = self::FORMAT_PNG,
        ?int $size = null,
    ): ResultInterface {
        Assert::oneOf($format, self::FORMATS);

        $effectiveSize = $size ?? $this->defaultSize;

        $builder = Builder::create()
            ->writer($this->createWriter($format))
            ->data($this->buildRedirectUrl($qrCode, $channel))
            ->errorCorrectionLevel($this->resolveErrorCorrectionLevel($qrCode))
            ->size($effectiveSize)
            ->margin(10)
        ;

        if ($qrCode->isEmbedLogo()) {
            $this->applyLogo($builder, $effectiveSize);
        }

        return $builder->build();
    }

    private function createWriter(string $format): WriterInterface
    {
        return match ($format) {
            self::FORMAT_PNG => new PngWriter(),
            self::FORMAT_SVG => new SvgWriter(),
            self::FORMAT_PDF => new PdfWriter(),
            default => throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $format)),
        };
    }

    private function buildRedirectUrl(QRCodeInterface $qrCode, ChannelInterface $channel): string
    {
        Assert::notNull($channel->getHostname(), sprintf(
            'Channel "%s" has no hostname — cannot encode a redirect URL.',
            (string) $channel->getCode(),
        ));
        Assert::notNull($qrCode->getSlug(), 'QR code has no slug — cannot encode a redirect URL.');

        // Generate the redirect route as a path and prepend the channel's hostname. Going via
        // UrlGeneratorInterface::ABSOLUTE_URL would require swapping the router's request context
        // host (which reflects the admin host), so we stay off the context entirely and only
        // rely on the path portion that the router already builds correctly.
        $path = $this->urlGenerator->generate(
            'setono_sylius_qr_code_redirect',
            ['slug' => $qrCode->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        return sprintf('https://%s%s', $channel->getHostname(), $path);
    }

    private function resolveErrorCorrectionLevel(QRCodeInterface $qrCode): ErrorCorrectionLevel
    {
        $level = $qrCode->getErrorCorrectionLevel();

        // Defensive fallback: if an adopting app surfaces an unexpected letter, don't crash the
        // download — use Medium (the entity default) and log so the issue surfaces in monitoring.
        if (!isset(self::ERROR_CORRECTION_LEVELS[$level])) {
            $this->logger->warning(
                'Unknown error correction level "{level}" on QR code {id}; falling back to Medium.',
                ['level' => $level, 'id' => $qrCode->getId()],
            );

            return ErrorCorrectionLevel::Medium;
        }

        return self::ERROR_CORRECTION_LEVELS[$level];
    }

    private function applyLogo(BuilderInterface $builder, int $qrSize): void
    {
        if (null === $this->logoPath) {
            $this->logger->warning(
                'QR code requests a logo but setono_sylius_qr_code.logo.path is not configured.',
            );

            return;
        }

        if (!is_file($this->logoPath)) {
            $this->logger->warning(
                'Configured logo path "{path}" does not exist; generating QR without logo.',
                ['path' => $this->logoPath],
            );

            return;
        }

        $logoWidth = (int) round($qrSize * $this->logoSizePercentage / 100);

        if ($logoWidth < 1) {
            return;
        }

        $builder
            ->logoPath($this->logoPath)
            ->logoResizeToWidth($logoWidth)
            ->logoPunchoutBackground(true)
        ;
    }
}
