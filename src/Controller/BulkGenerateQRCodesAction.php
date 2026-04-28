<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusQRCodePlugin\Factory\ProductRelatedQRCodeFactoryInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Admin POST endpoint at /admin/qr-codes/bulk-generate. Receives a list of product ids from the
 * Sylius admin product grid's bulk action, creates a ProductRelatedQRCode for each product whose
 * slug is not already in use, and flashes a summary of created vs. skipped counts.
 *
 * Options (embedLogo, enabled) are hardcoded to sensible defaults in this slice — a modal that
 * collects them per invocation is a follow-up (see the spec's "Bulk Generation of Product QR
 * Codes" requirement).
 */
final class BulkGenerateQRCodesAction
{
    use ORMTrait;

    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     * @param string|null $logoPath the configured `setono_sylius_qr_code.logo.path` — when set
     *                              (non-null + non-empty) every bulk-generated QR opts into the
     *                              logo embed; when absent the QR is generated without a logo.
     *                              Callers that want a different policy should override this
     *                              action.
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QRCodeRepositoryInterface $qrCodeRepository,
        private readonly ProductRelatedQRCodeFactoryInterface $qrCodeFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?string $logoPath,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        /** @var list<mixed> $rawIds */
        $rawIds = $request->request->all('ids');
        if ([] === $rawIds) {
            throw new BadRequestHttpException('No product ids supplied.');
        }

        $created = 0;
        $skipped = 0;

        $manager = null;

        foreach ($rawIds as $rawId) {
            if (!is_scalar($rawId)) {
                continue;
            }

            $product = $this->productRepository->find((int) $rawId);
            if (!$product instanceof ProductInterface) {
                ++$skipped;

                continue;
            }

            $slug = $this->deriveSlug($product);
            if (null === $slug || null !== $this->qrCodeRepository->findOneBySlug($slug)) {
                ++$skipped;

                continue;
            }

            $qrCode = $this->qrCodeFactory->createNew();
            $qrCode->setName(sprintf('QR: %s', (string) $product->getName()));
            $qrCode->setSlug($slug);
            $qrCode->setProduct($product);
            $qrCode->setEnabled(true);
            $qrCode->setEmbedLogo(null !== $this->logoPath && '' !== $this->logoPath);

            $manager = $this->getManager($qrCode);
            $manager->persist($qrCode);

            ++$created;
        }

        if ($created > 0 && null !== $manager) {
            $manager->flush();
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', [
                'message' => 'setono_sylius_qr_code.bulk_generate.summary',
                'parameters' => ['%created%' => $created, '%skipped%' => $skipped],
            ]);
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_admin_product_index'));
    }

    /**
     * Uses the product's base slug (the `$product->getSlug()` virtual accessor that Sylius
     * surfaces on `ProductInterface` via its translation). A locale-specific variant is a
     * customisation knob — adopters can override this action if they want something else.
     */
    private function deriveSlug(ProductInterface $product): ?string
    {
        $slug = (string) $product->getSlug();

        return '' === $slug ? null : $slug;
    }
}
