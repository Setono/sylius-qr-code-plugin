<?php

declare(strict_types=1);

namespace Setono\SyliusQRCodePlugin\Repository;

use Setono\SyliusQRCodePlugin\Model\QRCodeInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class QRCodeRepository extends EntityRepository implements QRCodeRepositoryInterface
{
    public function findOneBySlug(string $slug): ?QRCodeInterface
    {
        /** @var QRCodeInterface|null $qrCode */
        $qrCode = $this->createQueryBuilder('o')
            ->andWhere('o.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $qrCode;
    }

    public function findOneEnabledBySlug(string $slug): ?QRCodeInterface
    {
        /** @var QRCodeInterface|null $qrCode */
        $qrCode = $this->createQueryBuilder('o')
            ->andWhere('o.slug = :slug')
            ->andWhere('o.enabled = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $qrCode;
    }

    public function getScansCount(QRCodeInterface $qrCode): int
    {
        /** @var int|string $count */
        $count = $this->getEntityManager()
            ->createQuery('SELECT COUNT(s.id) FROM ' . $this->getScanEntityName() . ' s WHERE s.qrCode = :qrCode')
            ->setParameter('qrCode', $qrCode)
            ->getSingleScalarResult()
        ;

        return (int) $count;
    }

    private function getScanEntityName(): string
    {
        return $this->getEntityManager()
            ->getClassMetadata($this->getClassName())
            ->getAssociationMapping('scans')['targetEntity']
        ;
    }
}
