<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Functional\Controller;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Tests\Functional\AdminWebTestCase;

/**
 * Covers the shared-grid Edit dispatcher: GET /admin/qr-codes/{id}/edit redirects to the
 * subtype-specific update route based on the QR code's runtime class.
 */
final class QRCodeControllerTest extends AdminWebTestCase
{
    /**
     * @test
     */
    public function it_redirects_to_the_target_url_subtype_update_route(): void
    {
        $qrCode = $this->persistTargetUrlQRCode('redirect-target');

        $this->client->request('GET', sprintf('/admin/qr-codes/%d/edit', $qrCode->getId()));

        $response = $this->client->getResponse();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(
            sprintf('/admin/target-url-qr-codes/%d/edit', $qrCode->getId()),
            (string) $response->headers->get('Location'),
        );
    }

    private function persistTargetUrlQRCode(string $slug): QRCodeInterface
    {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setName($slug);
        $qrCode->setSlug($slug);
        $qrCode->setEnabled(true);
        $qrCode->setErrorCorrectionLevel(QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM);
        $qrCode->setTargetUrl('https://example.com/' . $slug);

        $this->entityManager->persist($qrCode);
        $this->entityManager->flush();

        return $qrCode;
    }
}
