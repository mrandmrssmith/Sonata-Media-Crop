<?php

namespace Media\CroppingBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Media\CroppingBundle\Entity\MediaCropping;
use Media\CroppingBundle\Repository\MediaCroppingRepository;#
use Symfony\Component\DependencyInjection\Container;
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
     * @param MediaCroppingRepository $mediaCroppingRepository
     */
    public function __construct(Container $container, MediaCroppingRepository $mediaCroppingRepository)
    {
        // WFT?????????????? Why is not working
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->mediaCroppingRepository = $mediaCroppingRepository;
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
                ->mediaCroppingRepository
                ->findByEntityAndSize($media);

            foreach ($mediaCroppingResizes as $mediaCroppingResize) {
                $this->em->remove($mediaCroppingResize);
            }

            $this->em->flush();
        }
    }
}
