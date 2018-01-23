<?php

namespace Media\CroppingBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Media\CroppingBundle\Entity\MediaCropping;
use Media\CroppingBundle\Repository\MediaCroppingRepository;
use DateTime;
use Exception;

/**
 * @package SmithOakMediaBundle\EventListener
 */
class MediaCroppingSubscriber implements EventSubscriber
{
    /** @var EntityManager $em */
    protected $em;

    /** @var MediaCroppingRepository $mediaCroppingRepository */
    protected $mediaCroppingRepository;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            'postUpdate'
        ];
    }

    /**
     * After a crop update delete custom resizes
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        /** @var MediaCropping $entity */
        $entity = $args->getObject();

        if ($entity instanceof Media) {
            $mediaCroppingResizes = $this
                ->em
                ->getRepository(MediaCropping::class)
                ->findByEntityAndSize($media);

            foreach ($mediaCroppingResizes as $mediaCroppingResize) {
                $this->em->remove($mediaCroppingResize);
            }

            $this->em->flush();
        }
    }
}
