<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Functional\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\QRCodeScanInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCode;
use Setono\SyliusQRCodePlugin\Repository\QRCodeScanRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Webmozart\Assert\Assert;

final class RedirectActionTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->catchExceptions(false);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    /**
     * @test
     */
    public function it_redirects_to_the_target_url_with_utm_parameters_appended(): void
    {
        $this->persistQRCode(
            slug: 'spring',
            enabled: true,
            targetUrl: 'https://example.com/landing',
            utmSource: 'flyer',
            utmMedium: 'print',
            utmCampaign: 'spring-2026',
        );

        $this->client->request('GET', '/qr/spring');

        $response = $this->client->getResponse();

        // The redirect status code is plugin-wide config (`setono_sylius_qr_code.redirect_type`,
        // default 302). Verifying the body of the redirect — target URL and UTM appending — is
        // what's interesting at this layer; the status-code wiring is asserted separately.
        self::assertSame(302, $response->getStatusCode());

        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('https://example.com/landing', $location);
        self::assertStringContainsString('utm_source=flyer', $location);
        self::assertStringContainsString('utm_medium=print', $location);
        self::assertStringContainsString('utm_campaign=spring-2026', $location);
    }

    /**
     * @test
     */
    public function it_uses_the_configured_redirect_status_code(): void
    {
        $this->persistQRCode(
            slug: 'configured',
            enabled: true,
            targetUrl: 'https://example.com/configured',
        );

        $this->client->request('GET', '/qr/configured');

        // Test app does not override `setono_sylius_qr_code.redirect_type`, so we get the
        // plugin's documented default (302).
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @test
     */
    public function it_returns_404_when_no_qr_code_matches_the_slug(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->client->request('GET', '/qr/does-not-exist');
    }

    /**
     * @test
     */
    public function it_returns_404_when_the_qr_code_is_disabled(): void
    {
        $this->persistQRCode(
            slug: 'archived',
            enabled: false,
            targetUrl: 'https://example.com/archived',
        );

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->client->request('GET', '/qr/archived');
    }

    /**
     * @test
     */
    public function it_records_a_scan_when_redirecting(): void
    {
        $qrCode = $this->persistQRCode(
            slug: 'tracked',
            enabled: true,
            targetUrl: 'https://example.com/tracked',
        );

        $this->client->request('GET', '/qr/tracked', server: [
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_USER_AGENT' => 'PHPUnit/Functional',
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $scanRepository = self::getContainer()->get('setono_sylius_qr_code.repository.qr_code_scan');
        Assert::isInstanceOf($scanRepository, QRCodeScanRepositoryInterface::class);

        self::assertSame(1, $scanRepository->countForQrCode($qrCode));

        /** @var list<QRCodeScanInterface> $scans */
        $scans = $scanRepository->findBy(['qrCode' => $qrCode]);
        self::assertCount(1, $scans);
        self::assertSame('203.0.113.7', $scans[0]->getIpAddress());
        self::assertSame('PHPUnit/Functional', $scans[0]->getUserAgent());
    }

    private function persistQRCode(
        string $slug,
        bool $enabled,
        string $targetUrl,
        ?string $utmSource = null,
        ?string $utmMedium = null,
        ?string $utmCampaign = null,
    ): QRCodeInterface {
        $qrCode = new TargetUrlQRCode();
        $qrCode->setName($slug);
        $qrCode->setSlug($slug);
        $qrCode->setEnabled($enabled);
        $qrCode->setErrorCorrectionLevel(QRCodeInterface::ERROR_CORRECTION_LEVEL_MEDIUM);
        $qrCode->setTargetUrl($targetUrl);
        $qrCode->setUtmSource($utmSource);
        $qrCode->setUtmMedium($utmMedium);
        $qrCode->setUtmCampaign($utmCampaign);

        $this->entityManager->persist($qrCode);
        $this->entityManager->flush();

        return $qrCode;
    }
}
