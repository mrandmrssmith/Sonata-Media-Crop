<?php

namespace Media\CroppingBundle\EventListener;

use Application\Sonata\MediaBundle\Entity\Media;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
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
            'postPersist'
        ];
    }

    /**
     * Create the cropping size if the uploaded image is a GIF
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     *
     * @throws Exception
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        /** @var Media $entity */
        $entity = $args->getObject();

        if ($entity instanceof Media && $entity->getContentType() === 'image/gif') {
            /** @var MediaCroppingRepository $mediaCroppingRepository */
            $mediaCroppingRepository = $this->em->getRepository(MediaCropping::class);
            $cropSizes = $this->getCropSizes($entity, $this->mediaCroppingConfig);

            foreach ($cropSizes as $cropSize) {
                /** @var MediaCropping[] $crops */
                $existingCrops = $mediaCroppingRepository->findBy([
                    'media' => $entity,
                    'sizeKey' => $cropSize['key']
                ]);

                if (empty($existingCrops)) {
                    $src = $this->container
                        ->get('sonata.media.twig.extension')
                        ->path($entity, 'reference');

                    $srcArray = array_filter(explode('/', $src));
                    array_pop($srcArray);
                    $parsed_url = parse_url($src);
                    $srcArray = array_filter(explode('/', $parsed_url['path']));
                    array_pop($srcArray);
                    array_shift($srcArray);
                    $mediaPath = join('/', $srcArray);

                    $newImagePath = "$mediaPath/{$entity->getName()}";

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
                        ->setName($entity->getName())
                        ->setPath($newImagePath)
                        ->setEntity($entity->getId())
                        ->setEntityType('ApplicationSonataMediaBundle:Media')
                        ->setMedia($entity)
                        ->setSizeKey($cropSize['key'])
                        ->setMeta($entity->getName())
                    ;

                    $this->em->persist($mediaCropping);
                }
            }

            $this->em->flush();
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
