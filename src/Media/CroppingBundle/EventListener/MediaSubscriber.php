<?php
namespace Media\CroppingBundle\EventListener;

use Application\Sonata\MediaBundle\Entity\Media;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Media\CroppingBundle\Entity\MediaCropping;
use Media\CroppingBundle\Repository\MediaCroppingRepository;
use Symfony\Component\DependencyInjection\Container;
use \DateTime;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class MediaSubscriber.
 *
 * @package SmithOakMediaBundle\EventListener
 */
class MediaSubscriber implements EventSubscriber
{
    /** @var Container $em */
    private $container;

    /** @var EntityManager $em */
    private $em;

    /** @var array $mediaCroppingConfig */
    private $mediaCroppingConfig;

    public function __construct(Container $container, EntityManager $em, array $mediaCroppingConfig)
    {
        $this->container = $container;
        $this->em = $em;
        $this->mediaCroppingConfig = $mediaCroppingConfig;
    }

    public function getSubscribedEvents()
    {
        return [
            'postPersist'
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->index($args);
    }

    protected function index(LifecycleEventArgs $args)
    {
        /** @var Media $entity */
        $entity = $args->getObject();

        if ($entity instanceof Media
            && $entity->getContentType() === 'image/gif'
        ) {
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
                    // Start Mamma Mia!

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

                    /** @var UploadedFile $uploadedFile */
                    $uploadedFile = $entity->getBinaryContent();

                    $content = file_get_contents($uploadedFile->getPathname());

                    $new_crop->setContent($content, ['storage' => 'STANDARD', 'ACL' => 'public-read']);

                    // End Mammma Mia!

                    $mediaCropping = new MediaCropping();
                    $mediaCropping->setUpdatedAt(new DateTime('now'))
                        ->setCreatedAt(new DateTime('now'))
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
