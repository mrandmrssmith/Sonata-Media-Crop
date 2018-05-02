<?php

namespace Media\CroppingBundle\Services;

use Doctrine\ORM\EntityManager;
use Media\CroppingBundle\Entity\MediaCropping;
use Sonata\MediaBundle\Provider\ImageProvider;
use Application\Sonata\MediaBundle\Entity\Media;
use Imagick;
use Exception;
use DateTime;

/**
 * Class MediaResizingHelper
 *
 * @package Media\CroppingBundle\Services
 */
class MediaResizingHelper
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    /** @var ImageProvider $imageProvider */
    protected $imageProvider;

    /**
     * MediaResizingHelper constructor.
     *
     * @param EntityManager $entityManager
     * @param ImageProvider $imageProvider
     */
    public function __construct(EntityManager $entityManager, ImageProvider $imageProvider)
    {
        $this
            ->setEntityManager($entityManager)
            ->setImageProvider($imageProvider)
        ;
    }

    /**
     * @author Daniele Rostellato <daniele.rostellato@smithhotels.com>
     *
     * @param MediaCropping $mediaCropping
     * @param int $width
     * @param int $position
     *
     * @return MediaCropping
     *
     * @throws Exception
     */
    public function resizeCrop(MediaCropping $mediaCropping, int $width, int $position): MediaCropping
    {
        /** @var string $cropPath */
        $cropPath = $this->getImageProvider()->getCdnPath($mediaCropping->getPath(), false);
        $imagick = new Imagick($cropPath);

        if ($imagick->getImageWidth() === $width) {
            return $mediaCropping;
        }

        $imagick->resizeImage(
            $width,
            $width / ($imagick->getImageWidth() / $imagick->getImageHeight()),
            Imagick::FILTER_UNDEFINED,
            1
        );

        $srcArray = array_filter(explode('/', $cropPath));
        array_pop($srcArray);
        $parsedUrl = parse_url($cropPath);
        $srcArray = array_filter(explode('/', $parsedUrl['path']));
        array_pop($srcArray);
        array_shift($srcArray);
        $mediaPath = join('/', $srcArray);

        $cropPathinfo = pathinfo($cropPath);
        $newImageName = "{$cropPathinfo['filename']}-w$width-p$position.{$cropPathinfo['extension']}";
        $newImagePath = "$mediaPath/$newImageName";

        $newImage = $this->getImageProvider()->getFileSystem()->get($newImagePath, true);

        $newImage->setContent($imagick->getImageBlob(), ['storage' => 'STANDARD', 'ACL' => 'public-read']);

        $newMediaCropping = new MediaCropping();
        $newMediaCropping
            ->setCreatedAt(new DateTime('now'))
            ->setUpdatedAt($newMediaCropping->getCreatedAt())
            ->setName($newImageName)
            ->setPath($newImagePath)
            ->setEntity($mediaCropping->getEntity())
            ->setEntityType('ApplicationSonataMediaBundle:Media')
            ->setMedia($mediaCropping->getMedia())
            ->setSizeKey("{$mediaCropping->getSizeKey()}-w$width-p$position")
            ->setMeta($mediaCropping->getMeta())
        ;

        $this->getEntityManager()->persist($newMediaCropping);
        $this->getEntityManager()->flush($newMediaCropping);

        return $newMediaCropping;
    }

    /**
     * @param EntityManager $entityManager
     *
     * @return self
     */
    protected function setEntityManager(EntityManager $entityManager): self
    {
        $this->entityManager = $entityManager;

        return $this;
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    /**
     * @param ImageProvider $imageProvider
     *
     * @return self
     */
    public function setImageProvider(ImageProvider $imageProvider): self
    {
        $this->imageProvider = $imageProvider;

        return $this;
    }

    /**
     * @return ImageProvider
     */
    public function getImageProvider(): ImageProvider
    {
        return $this->imageProvider;
    }
}
