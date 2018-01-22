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
     * @return array
     */
    public function findByEntityAndSize(MediaCropping $mediaCropping)
    {
        return $this
            ->createQueryBuilder('mediaCropping')
            ->where('mediaCropping.media = :media')
            ->setParameter('media', $mediaCropping->getMedia())
            ->andWhere('mediaCropping.sizeKey LIKE :sizeKey')
            ->setParameter('sizeKey', "%{$mediaCropping->getSizeKey()}%")
            ->getQuery()
            ->getResult();
    }
}
