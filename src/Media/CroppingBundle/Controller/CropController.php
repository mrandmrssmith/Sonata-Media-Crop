<?php

namespace Media\CroppingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Application\Sonata\MediaBundle\Entity\Media; 

use Media\CroppingBundle\Entity\MediaCropping;
use Imagine\Image\Point;
use Imagine\Image\Box;

class CropController extends Controller {

	public function indexAction( $id ) {
		if ( empty( $id ) ) {
			return new JsonResponse( array( 'success' => false, 'message' => 'Media not found' ) );
		}
		$DM        = $this->getDoctrine()->getManager();
		$mediaRepo = $DM->getRepository( 'Application\Sonata\MediaBundle\Entity\Media' );
		$media     = $mediaRepo->find( $id );
		if ( empty( $media ) ) {
			return new JsonResponse( array( 'success' => false, 'message' => 'Media not found' ) );
		}
		$mediaThumbsRepo = $DM->getRepository( 'Media\CroppingBundle\Entity\MediaCropping' );
		$mediaThumbs     = $mediaThumbsRepo->findby( array( 'media' => $media ) );
		$thumbs          = array();
		if ( ! empty( $mediaThumbs ) ) {
            $filesystem = $this->get('sonata.media.provider.image')->getFileSystem();


			foreach ( $mediaThumbs as $key => $val ) {
				$thumbs[] = array(
					'id'         => $val->getId(),
					'name'       => $val->getName(),
					'path'       => $filesystem->getAdapter()->getUrl($val->getPath()),
					'meta'       => $val->getMeta(),
					'entityType' => $val->getEntityType(),
					'entity'     => $val->getEntity(),
					'sizeKey'    => $val->getSizeKey(),
					'createdAt'  => $val->getCreatedAt(),
					'updatedAt'  => $val->getUpdatedAt(),
				);
			}
		}
		$response['id']        = $media->getId();
		$response['title']     = $media->getName();
		$provider              = $this->container->get( $media->getProviderName() );
		$response['reference'] = $provider->generatePublicUrl( $media, 'reference' );
		$response['root_path'] = $this->container->get( 'sonata.media.twig.extension' )->path( $media, 'reference' );
		$response['config']    = $this->container->getParameter( 'media_cropping' );
		$response['sizes']     = $this->getCropSizes($media);
		$response['thumbs']    = $thumbs;

		return new JsonResponse( array( 'success' => true, 'message' => 'Media found', 'data' => $response ) );
	}

	public function getCropSizes(Media $media) {
		$config = $this->container->getParameter( 'media_cropping' );
		$crops  = [];
		if ( ! empty( $config['sizes'] ) ) {
			foreach ( $config['sizes'] as $context => $sizes ) {
                if($context === $media->getContext()){
                    foreach ( $sizes as $key => $val ) {
                        $crops[] = array( 'key' => $key, 'width' => $val['width'], 'height' => $val['height'] );
                    }
                }
            }
		}
		return $crops;
	}

	public function saveAction(Request $request, $id ) {

		if ( empty( $id ) ) {
			return new JsonResponse( array(
					'success' => false,
					'message' => 'Media not found',
					'data'    => '',
					'key'     => ''
				) );
		}
		$DM        = $this->getDoctrine()->getManager();
		$mediaRepo = $DM->getRepository( 'Application\Sonata\MediaBundle\Entity\Media' );
		$media     = $mediaRepo->find( $id );
		if ( empty( $media ) ) {
			return new JsonResponse( array(
					'success' => false,
					'message' => 'Media not found',
					'data'    => '',
					'key'     => ''
				) );
		}
		$mediaThumbsRepo = $DM->getRepository( 'Media\CroppingBundle\Entity\MediaCropping' );
		$mediaThumbs     = $mediaThumbsRepo->findby( array( 'media' => $media ) );
		if ( ! empty( $mediaThumbs ) ) {

		}
		$response = $this->cropMedia( $request, $media );

		return new JsonResponse( $response );
	}

	public function cropMedia(Request $request, $media ) {

		$requestData = $request->attributes->all();
		$data        = $request->query->all();
		$key         = $data['key'];
		$exist       = $data['exist'];
		$x           = $data['x'];
		$y           = $data['y'];
		$w           = $data['w'];
		$h           = (int) $data['h'];
        
        $crop_size = null;
        foreach($this->getCropSizes($media) as $size){
            if($size['key'] === $key){
                $crop_size = $size;
            }
        }

		if ( !is_array($crop_size)) {
			return array( 'success' => false, 'message' => 'Invalid Size/Dimensions', 'data' => '', 'key' => '' );
		}

		$src          = $this->container->get( 'sonata.media.twig.extension' )->path( $media, 'reference' );
		$srcArray     = array_filter( explode( '/', $src ) );
		array_pop( $srcArray );

        $parsed_url = parse_url($src);
		$srcArray  = array_filter( explode( '/', $parsed_url['path'] ) );

		array_pop( $srcArray );
		array_shift( $srcArray );

		$mediaPath       = join( '/', $srcArray );
		$crop_media_name = $key . '_crop_' . $media->getId() . '_' . $crop_size['width'] . 'x' . $crop_size['height'];
        $image_service = $this->get('sonata.media.adapter.image.default');
        $image = $image_service->load(file_get_contents($src));

        $dest = imagecreatetruecolor($crop_size['width'], $crop_size['height']);
        if (function_exists('imageantialias')) {
            imageantialias($dest, true);
        }

        $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
        imagefill($dest, 0, 0, $transparent);
        imagecolortransparent($dest, $transparent);

        imagecopyresampled($dest, $image->getGdResource(), 0, 0, round($x), round($y), $crop_size['width'], $crop_size['height'], round($w), round($h) );
		imagejpeg( $dest, '/tmp/' . $crop_media_name . '.jpeg', 100 );
		imagedestroy( $dest );

        $new_crop = $this->get('sonata.media.provider.image')->getFileSystem()->get($mediaPath . '/' .$crop_media_name . '.jpeg', true);
        $content = file_get_contents('/tmp/' . $crop_media_name . '.jpeg');
        // @todo these settings need to come from the config. I dont understand why they are not alreayd set the service already
        $new_crop->setContent($content, ['storage' => 'STANDARD', 'ACL' => 'public-read']);

		$DM              = $this->getDoctrine()->getManager();
		$mediaThumbsRepo = $DM->getRepository( 'Media\CroppingBundle\Entity\MediaCropping' );
		$mediaThumb      = $mediaThumbsRepo->findOneBy( array(
            'media'      => $media,
				'entity'     => $requestData['entity'],
				'entityType' => $requestData['entityType'],
				'sizeKey'    => $key,
			)
		);

		if ($mediaThumb instanceof MediaCropping) {
            $message = 'Media Updated';
			$MediaCropping = $mediaThumb;
			$entity        = $DM->getRepository( $mediaThumb->getEntityType() )
			                    ->find( $mediaThumb->getEntity() );
			if ( ! empty( $entity ) ) {
				$MediaCropping->setMeta( $entity->__toString() );
			}
			$MediaCropping->setUpdatedAt( new \DateTime( 'now' ) );
			$MediaCropping->setPath( $mediaPath . '/' . $crop_media_name . '.jpeg' );
			$MediaCropping->setName( $crop_media_name . '.jpeg' );
		} else {
            $message = 'Media Created';

			$MediaCropping = new MediaCropping();
			$MediaCropping->setUpdatedAt( new \DateTime( 'now' ) );
			$MediaCropping->setCreatedAt( new \DateTime( 'now' ) );
			$MediaCropping->setName( $crop_media_name . '.jpeg' );
			$MediaCropping->setPath( $mediaPath . '/' . $crop_media_name . '.jpeg' );
			$MediaCropping->setEntity( $requestData['entity'] );
			$MediaCropping->setEntityType( $requestData['entityType'] );
			$MediaCropping->setMedia( $media );
			$MediaCropping->setSizeKey( $key );
			$entity = $DM->getRepository( $requestData['entityType'] )
			             ->find( $requestData['entity'] );
			if ( ! empty( $entity ) ) {
				$MediaCropping->setMeta( $entity->__toString() );
			}
		}

		$validator = $this->get( 'validator' );
		$errors    = $validator->validate( $MediaCropping );
		if ( count( $errors ) > 0 ) {
			$errorsString = array();
			foreach ( $errors as $key => $val ) {
				$errorsString[] = $val->getMessage();
			}
			$errorsString = '<ul><li>' . join( '</li><li>', $errorsString ) . '</li></ul>';

			return array(
				'success' => false,
				'message' => 'Media Already Exist',
				'data'    => $errorsString,
				'key'     => 'exist'
			);
		}
		$DM->persist( $MediaCropping );
		$DM->flush();

		return array( 'success' => true, 'message' => $message, 'data' => '', 'key' => '' );
	}
}
