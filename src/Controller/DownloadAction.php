<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Controller;

use Setono\SyliusQRCodePlugin\Channel\DefaultChannelResolverInterface;
use Setono\SyliusQRCodePlugin\Generator\QRCodeGeneratorInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

/**
 * Admin endpoint at /admin/qr-codes/{id}/download/{format}/{channel}. Produces the QR code image
 * for the given channel's hostname in PNG/SVG/PDF. When the channel segment is absent, the
 * configured DefaultChannelResolverInterface implementation picks one. Responses carry an ETag so the browser cache can
 * short-circuit subsequent downloads; the ETag changes whenever the entity is updated or a
 * different channel is used.
 */
final class DownloadAction
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly QRCodeRepositoryInterface $qrCodeRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly DefaultChannelResolverInterface $defaultChannelResolver,
        private readonly QRCodeGeneratorInterface $qrCodeGenerator,
    ) {
    }

    public function __invoke(Request $request, int $id, string $format = QRCodeGeneratorInterface::FORMAT_PNG, ?string $channel = null): Response
    {
        Assert::oneOf($format, QRCodeGeneratorInterface::FORMATS);

        $qrCode = $this->qrCodeRepository->find($id);
        if (null === $qrCode) {
            throw new NotFoundHttpException(sprintf('No QR code with id %d.', $id));
        }
        Assert::isInstanceOf($qrCode, QRCodeInterface::class);

        $resolvedChannel = $this->resolveChannel($channel);

        $etag = $this->computeEtag((int) $qrCode->getId(), $qrCode->getUpdatedAt(), $format, $resolvedChannel);

        $response = new Response();
        $response->setEtag($etag);
        $response->setPrivate();
        $response->setMaxAge(86400);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $result = $this->qrCodeGenerator->generate($qrCode, $resolvedChannel, $format);

        // In-memory / generated content pattern from the Symfony HttpFoundation "Serving Files"
        // docs: populate a regular Response with the bytes, set Content-Type, and build the
        // Content-Disposition via HeaderUtils::makeDisposition so non-ASCII filenames get the
        // correct `filename=` + `filename*=utf-8''...` pair for free.
        $response->setContent($result->getString());
        $response->headers->set('Content-Type', $result->getMimeType());
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->buildFilename(
                (string) $qrCode->getSlug(),
                $format,
                null === $channel ? null : (string) $resolvedChannel->getCode(),
            ),
        ));

        return $response;
    }

    private function resolveChannel(?string $channelCode): ChannelInterface
    {
        if (null === $channelCode) {
            return $this->defaultChannelResolver->getDefaultChannel();
        }

        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (null === $channel || !$channel->isEnabled()) {
            throw new NotFoundHttpException(sprintf('No enabled channel with code "%s".', $channelCode));
        }

        return $channel;
    }

    private function computeEtag(
        int $id,
        ?\DateTimeInterface $updatedAt,
        string $format,
        ChannelInterface $channel,
    ): string {
        return hash('xxh128', sprintf(
            '%d|%s|%s|%s',
            $id,
            null === $updatedAt ? '' : $updatedAt->format(\DateTimeInterface::ATOM),
            $format,
            (string) $channel->getCode(),
        ));
    }

    private function buildFilename(string $slug, string $format, ?string $channelCode): string
    {
        return null === $channelCode
            ? sprintf('%s.%s', $slug, $format)
            : sprintf('%s-%s.%s', $slug, $channelCode, $format);
    }
}
