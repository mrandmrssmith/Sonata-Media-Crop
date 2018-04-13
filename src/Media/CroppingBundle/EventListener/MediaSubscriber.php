<?php

namespace Media\CroppingBundle\EventListener;

use Doctrine\ORM\{
    EntityManager, Events
};
use Application\Sonata\MediaBundle\Entity\Media;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Media\CroppingBundle\Entity\MediaCropping;
use Media\CroppingBundle\Repository\MediaCroppingRepository;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use DateTime;
use Exception;

/**
 * Class MediaSubscriber.
 *
 * @package SmithOakMediaBundle\EventListener
 */
class MediaSubscriber implements EventSubscriber
{
    /** @var Container $container */
    protected $container;

    /** @var EntityManager $em */
    protected $em;

    /** @var array $mediaCroppingConfig */
    protected $mediaCroppingConfig;

    /**
     * MediaSubscriber constructor.
     *
     * @param Container $container
     * @param EntityManager $em
     * @param array $mediaCroppingConfig
     */
    public function __construct(Container $container, EntityManager $em, array $mediaCroppingConfig)
    {
        $this->container = $container;
        $this->em = $em;
        $this->mediaCroppingConfig = $mediaCroppingConfig;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postFlush
        ];
    }

    /**
     * Create the cropping size if the uploaded image is a GIF
     *
     * @param PostFlushEventArgs $args
     *
     * @return void
     *
     * @throws Exception
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        /** @var Media[] $medias */
        $medias = $this->em
            ->createQueryBuilder()
            ->select('m')
            ->from(Media::class, 'm')
            ->where('m.contentType LIKE :type')
            ->setParameter('type', 'image/gif')
            ->andWhere('m.updatedAt > :lastMins')
            ->setParameter('lastMins', new DateTime('-2 minutes'))
            ->getQuery()
            ->getResult()
        ;

        if (empty($medias)) {
            return;
        }

        $this->disableEvent();

        foreach ($medias as $media) {
            /** @var MediaCroppingRepository $mediaCroppingRepository */
            $mediaCroppingRepository = $this->em->getRepository(MediaCropping::class);
            $cropSizes = $this->getCropSizes($media, $this->mediaCroppingConfig);

            foreach ($cropSizes as $cropSize) {
                /** @var MediaCropping[] $crops */
                $existingCrops = $mediaCroppingRepository->findBy([
                    'media' => $media,
                    'sizeKey' => $cropSize['key']
                ]);

                if (empty($existingCrops)) {
                    $src = $this->container
                        ->get('sonata.media.twig.extension')
                        ->path($media, 'reference');

                    $srcArray = array_filter(explode('/', $src));
                    array_pop($srcArray);
                    $parsed_url = parse_url($src);
                    $srcArray = array_filter(explode('/', $parsed_url['path']));
                    array_pop($srcArray);
                    array_shift($srcArray);
                    $mediaPath = join('/', $srcArray);

                    $newImagePath = "$mediaPath/{$media->getName()}";

                    $new_crop = $this->container
                        ->get('sonata.media.provider.image')
                        ->getFileSystem()
                        ->get($newImagePath, true);

                    $content = file_get_contents($src);

                    $new_crop->setContent($content, ['storage' => 'STANDARD', 'ACL' => 'public-read']);

                    $mediaCropping = new MediaCropping();
                    $mediaCropping
                        ->setCreatedAt(new DateTime('now'))
                        ->setUpdatedAt($mediaCropping->getCreatedAt())
                        ->setName($media->getName())
                        ->setPath($newImagePath)
                        ->setEntity($media->getId())
                        ->setEntityType('ApplicationSonataMediaBundle:Media')
                        ->setMedia($media)
                        ->setSizeKey($cropSize['key'])
                        ->setMeta($media->getName())
                    ;

                    $this->em->persist($mediaCropping);
                }
            }
        }

        $this->em->flush();
    }

    /**
     * @author Daniele Rostellato <daniele.rostellato@smithhotels.com>
     *
     * @return void
     */
    private function disableEvent(): void
    {
        foreach ($this->em->getEventManager()->getListeners() as $event => $listeners) {
            foreach ($listeners as $key => $listener) {
                if ($listener instanceof $this) {
                    $this->em
                        ->getEventManager()
                        ->removeEventListener(
                            [Events::postFlush],
                            $listener
                        );

                    break;
                }
            }
        }
    }

    /**
     * @param Media $media
     * @param array $config
     *
     * @return array
     */
    public static function getCropSizes(Media $media, array $config): array
    {
        $crops  = [];

        if ( ! empty( $config['sizes'] ) ) {
            foreach ( $config['sizes'] as $context => $sizes ) {
                if ($context === $media->getContext()) {
                    foreach ( $sizes as $key => $val ) {
                        $crops[] = [
                            'key' => $key,
                            'width' => $val['width'],
                            'height' => $val['height']
                        ];
                    }
                }
            }
        }

        return $crops;
    }
}
