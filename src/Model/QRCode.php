<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Model\ToggleableTrait;

class QRCode implements QRCodeInterface
{
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?string $name = null;

    protected ?string $slug = null;

    protected int $redirectType = 307;

    protected bool $embedLogo = false;

    protected string $errorCorrectionLevel = self::ERROR_CORRECTION_LEVEL_MEDIUM;

    protected ?string $utmSource = null;

    protected ?string $utmMedium = null;

    protected ?string $utmCampaign = null;

    protected ?\DateTimeImmutable $createdAt = null;

    protected ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<array-key, QRCodeScanInterface> */
    protected Collection $scans;

    public function __construct()
    {
        $this->scans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getRedirectType(): int
    {
        return $this->redirectType;
    }

    public function setRedirectType(int $redirectType): void
    {
        $this->redirectType = $redirectType;
    }

    public function isEmbedLogo(): bool
    {
        return $this->embedLogo;
    }

    public function setEmbedLogo(bool $embedLogo): void
    {
        $this->embedLogo = $embedLogo;
    }

    public function getErrorCorrectionLevel(): string
    {
        return $this->errorCorrectionLevel;
    }

    public function setErrorCorrectionLevel(string $errorCorrectionLevel): void
    {
        $this->errorCorrectionLevel = $errorCorrectionLevel;
    }

    public function getUtmSource(): ?string
    {
        return $this->utmSource;
    }

    public function setUtmSource(?string $utmSource): void
    {
        $this->utmSource = $utmSource;
    }

    public function getUtmMedium(): ?string
    {
        return $this->utmMedium;
    }

    public function setUtmMedium(?string $utmMedium): void
    {
        $this->utmMedium = $utmMedium;
    }

    public function getUtmCampaign(): ?string
    {
        return $this->utmCampaign;
    }

    public function setUtmCampaign(?string $utmCampaign): void
    {
        $this->utmCampaign = $utmCampaign;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getScans(): Collection
    {
        return $this->scans;
    }

    public function getScansCount(): int
    {
        // The scans collection is mapped with fetch="EXTRA_LAZY", so count() runs a single
        // COUNT(*) query without hydrating the rows.
        return $this->scans->count();
    }

    public function getType(): string
    {
        // The base class is abstract by convention (Sylius resource bundle limitation); real
        // instances are always ProductRelatedQRCode or TargetUrlQRCode, both of which override
        // this method.
        throw new \LogicException(sprintf(
            'QR code type is not defined for %s. Subclasses must override getType().',
            static::class,
        ));
    }
}
