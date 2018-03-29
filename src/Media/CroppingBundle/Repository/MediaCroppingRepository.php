<?php

namespace Media\CroppingBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Media\CroppingBundle\Entity\MediaCropping;

class MediaCroppingRepository extends EntityRepository
{
    /**
     * @author Daniele Rostellato <daniele.rostellato@smithhotels.com>
     *
     * @param MediaCropping $mediaCropping
     *
     * @return MediaCropping[]
     */
    public function findByEntityAndSize(MediaCropping $mediaCropping): array
    {
        return $this
            ->createQueryBuilder('mediaCropping')
            ->where('mediaCropping.media = :media')
            ->setParameter('media', $mediaCropping->getMedia())
            ->andWhere('mediaCropping.sizeKey LIKE :sizeKey')
            ->setParameter('sizeKey', "%{$mediaCropping->getSizeKey()}%")
            ->andWhere('mediaCropping.id <> :id')
            ->setParameter('id', $mediaCropping->getId())
            ->getQuery()
            ->getResult();
    }
}
