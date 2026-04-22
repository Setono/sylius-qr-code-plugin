<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Controller;

use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Setono\SyliusQRCodePlugin\Model\TargetUrlQRCodeInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The base QR code resource uses this controller so that the shared grid
 * "Edit" action dispatches to the correct subtype-specific update route.
 *
 * The two subtype resources (`product_related_qr_code` and `target_url_qr_code`)
 * handle the actual update forms; this controller only does the dispatch.
 */
class QRCodeController extends ResourceController
{
    public function updateAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);
        $resource = $this->findOr404($configuration);

        if ($resource instanceof ProductRelatedQRCodeInterface) {
            return new RedirectResponse($this->generateUrl(
                'setono_sylius_qr_code_admin_product_related_qr_code_update',
                ['id' => $resource->getId()],
            ));
        }

        if ($resource instanceof TargetUrlQRCodeInterface) {
            return new RedirectResponse($this->generateUrl(
                'setono_sylius_qr_code_admin_target_url_qr_code_update',
                ['id' => $resource->getId()],
            ));
        }

        throw new \LogicException(sprintf('Unsupported QR code type: %s', $resource::class));
    }
}
