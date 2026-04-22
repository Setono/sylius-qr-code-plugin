<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

interface QRCodeInterface extends ResourceInterface, ToggleableInterface
{
    public const ERROR_CORRECTION_LEVEL_LOW = 'L';

    public const ERROR_CORRECTION_LEVEL_MEDIUM = 'M';

    public const ERROR_CORRECTION_LEVEL_QUARTILE = 'Q';

    public const ERROR_CORRECTION_LEVEL_HIGH = 'H';

    public const ERROR_CORRECTION_LEVELS = [
        self::ERROR_CORRECTION_LEVEL_LOW,
        self::ERROR_CORRECTION_LEVEL_MEDIUM,
        self::ERROR_CORRECTION_LEVEL_QUARTILE,
        self::ERROR_CORRECTION_LEVEL_HIGH,
    ];

    public const REDIRECT_TYPES = [301, 302, 307];

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getRedirectType(): int;

    public function setRedirectType(int $redirectType): void;

    public function isEmbedLogo(): bool;

    public function setEmbedLogo(bool $embedLogo): void;

    public function getErrorCorrectionLevel(): string;

    public function setErrorCorrectionLevel(string $errorCorrectionLevel): void;

    public function getUtmSource(): ?string;

    public function setUtmSource(?string $utmSource): void;

    public function getUtmMedium(): ?string;

    public function setUtmMedium(?string $utmMedium): void;

    public function getUtmCampaign(): ?string;

    public function setUtmCampaign(?string $utmCampaign): void;

    public function getCreatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void;

    /**
     * @return Collection<array-key, QRCodeScanInterface>
     */
    public function getScans(): Collection;
}
