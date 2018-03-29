<?php

namespace Media\CroppingBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Media\CroppingBundle\Entity\MediaCropping;
use Media\CroppingBundle\Repository\MediaCroppingRepository;
use Gaufrette\Filesystem;
use Sonata\MediaBundle\Provider\ImageProvider;
use DateTime;
use Exception;

/**
 * @package SmithOakMediaBundle\EventListener
 */
class MediaCroppingSubscriber implements EventSubscriber
{
    /** @var EntityManager $em */
    protected $em;

    /** @var FileSystem $fileSystem */
    protected $fileSystem;

    /** @var ImageProvider $imageProvider */
    protected $imageProvider;

    /**
     * @param EntityManager $em
     * @param FileSystem $fileSystem
     */
    public function __construct(EntityManager $em, FileSystem $fileSystem, ImageProvider $imageProvider)
    {
        $this->em = $em;
        $this->fileSystem = $fileSystem;
        $this->imageProvider = $imageProvider;
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

        if ($entity instanceof MediaCropping) {
            $mediaCroppingResizes = $this
                ->em
                ->getRepository(MediaCropping::class)
                ->findByEntityAndSize($entity);

            foreach ($mediaCroppingResizes as $mediaCroppingResize) {
                if ($this->fileSystem->has($mediaCroppingResize->getPath())) {
                    $this->fileSystem->delete($mediaCroppingResize->getPath());
                }

                $this->em->remove($mediaCroppingResize);
            }

            $this->em->flush();
        }
    }
}
