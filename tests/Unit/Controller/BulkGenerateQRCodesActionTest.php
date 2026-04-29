<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusQRCodePlugin\Controller\BulkGenerateQRCodesAction;
use Setono\SyliusQRCodePlugin\Factory\ProductRelatedQRCodeFactoryInterface;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCode;
use Setono\SyliusQRCodePlugin\Model\ProductRelatedQRCodeInterface;
use Setono\SyliusQRCodePlugin\Repository\QRCodeRepositoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BulkGenerateQRCodesActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_creates_a_qr_code_per_selected_product_skipping_slug_collisions(): void
    {
        $p1 = $this->product(1, 'Apples', 'apples');
        $p2 = $this->product(2, 'Bananas', 'bananas');
        $p3 = $this->product(3, 'Cherries', 'cherries');

        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $productRepository->find(1)->willReturn($p1);
        $productRepository->find(2)->willReturn($p2);
        $productRepository->find(3)->willReturn($p3);

        $qrCodeRepository = $this->prophesize(QRCodeRepositoryInterface::class);
        $qrCodeRepository->findOneBySlug('apples')->willReturn(null);
        $qrCodeRepository->findOneBySlug('bananas')->willReturn(new ProductRelatedQRCode()); // collision
        $qrCodeRepository->findOneBySlug('cherries')->willReturn(null);

        $factory = $this->prophesize(ProductRelatedQRCodeFactoryInterface::class);
        $factory->createNew()->will(fn () => new ProductRelatedQRCode());

        $em = $this->prophesize(EntityManagerInterface::class);
        $em->persist(Argument::type(ProductRelatedQRCodeInterface::class))->shouldBeCalledTimes(2);
        $em->flush()->shouldBeCalledOnce();

        $registry = $this->prophesize(ManagerRegistry::class);
        $registry->getManagerForClass(Argument::any())->willReturn($em->reveal());

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('sylius_admin_product_index', Argument::cetera())->willReturn('/admin/products/');

        $action = new BulkGenerateQRCodesAction(
            $productRepository->reveal(),
            $qrCodeRepository->reveal(),
            $factory->reveal(),
            $urlGenerator->reveal(),
            $registry->reveal(),
        );

        $response = $action($this->buildRequestWithIds([1, 2, 3]));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/products/', $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function it_skips_products_that_do_not_exist(): void
    {
        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $productRepository->find(42)->willReturn(null);

        $factory = $this->prophesize(ProductRelatedQRCodeFactoryInterface::class);
        $factory->createNew()->shouldNotBeCalled();

        $em = $this->prophesize(EntityManagerInterface::class);
        $em->flush()->shouldNotBeCalled();

        $registry = $this->prophesize(ManagerRegistry::class);
        $registry->getManagerForClass(Argument::any())->willReturn($em->reveal());

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('sylius_admin_product_index', Argument::cetera())->willReturn('/admin/products/');

        $action = new BulkGenerateQRCodesAction(
            $productRepository->reveal(),
            $this->prophesize(QRCodeRepositoryInterface::class)->reveal(),
            $factory->reveal(),
            $urlGenerator->reveal(),
            $registry->reveal(),
        );

        $response = $action($this->buildRequestWithIds([42]));

        self::assertSame(302, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_skips_products_without_a_slug(): void
    {
        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $productRepository->find(1)->willReturn($this->product(1, 'No slug', ''));

        $factory = $this->prophesize(ProductRelatedQRCodeFactoryInterface::class);
        $factory->createNew()->shouldNotBeCalled();

        $em = $this->prophesize(EntityManagerInterface::class);
        $em->flush()->shouldNotBeCalled();

        $registry = $this->prophesize(ManagerRegistry::class);
        $registry->getManagerForClass(Argument::any())->willReturn($em->reveal());

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate(Argument::cetera())->willReturn('/admin/products/');

        $action = new BulkGenerateQRCodesAction(
            $productRepository->reveal(),
            $this->prophesize(QRCodeRepositoryInterface::class)->reveal(),
            $factory->reveal(),
            $urlGenerator->reveal(),
            $registry->reveal(),
        );

        $action($this->buildRequestWithIds([1]));
    }

    /**
     * @test
     */
    public function it_400s_when_the_ids_list_is_empty(): void
    {
        $action = new BulkGenerateQRCodesAction(
            $this->prophesize(ProductRepositoryInterface::class)->reveal(),
            $this->prophesize(QRCodeRepositoryInterface::class)->reveal(),
            $this->prophesize(ProductRelatedQRCodeFactoryInterface::class)->reveal(),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
            $this->prophesize(ManagerRegistry::class)->reveal(),
        );

        $this->expectException(BadRequestHttpException::class);
        $action($this->buildRequestWithIds([]));
    }

    private function product(int $id, string $name, string $slug): ProductInterface
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->getId()->willReturn($id);
        $product->getName()->willReturn($name);
        $product->getSlug()->willReturn($slug);

        return $product->reveal();
    }

    /**
     * @param list<int> $ids
     */
    private function buildRequestWithIds(array $ids): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $session->registerBag(new FlashBag());

        $request = new Request(request: ['ids' => $ids]);
        $request->setSession($session);

        return $request;
    }
}
