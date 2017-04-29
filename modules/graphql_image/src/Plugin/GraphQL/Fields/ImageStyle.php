<?php

namespace Drupal\graphql_image\Plugin\GraphQL\Fields;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\graphql_core\GraphQL\FieldPluginBase;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Youshido\GraphQL\Execution\ResolveInfo;
use Drupal\image\Entity\ImageStyle as ImageStylePlugin;

/**
 * GraphQL field override for image field.
 *
 * @GraphQLField(
 *   id = "image_style",
 *   type = "ImageStyle",
 *   types = {"Image"},
 *   nullable = true,
 *   deriver="Drupal\graphql_image\Plugin\Deriver\ImageStyleDeriver"
 * )
 */
class ImageStyle extends FieldPluginBase implements ContainerFactoryPluginInterface {
  use DependencySerializationTrait;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The image style this field is supposed to generate.
   *
   * @var string
   */
  protected $imageStyle;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ImageFactory $imageFactory) {
    $this->imageFactory = $imageFactory;
    $this->imageStyle = $plugin_definition['image_style'];
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('image.factory'));
  }

  /**
   * {@inheritdoc}
   */
  public function resolveValues($value, array $args, ResolveInfo $info) {
    if ($value instanceof ImageItem && $file = $value->entity) {
      if ($file instanceof FileInterface) {

        $original = $this->imageFactory->get($file->getFileUri());
        $style = NULL;

        // Bail out if the original is not a valid image.
        if (!$original->isValid()) {
          return;
        }

        if ($style = ImageStylePlugin::load($this->imageStyle)) {
          $styleUri = $style->buildUri($file->getFileUri());
          if (!file_exists($styleUri)) {
            $style->createDerivative($file->getFileUri(), $styleUri);
          }
          $derivative = $this->imageFactory->get($styleUri);
        }
        else {
          $derivative = $original;
        }

        // Return null if derivative generation didn't succeed.
        if ($derivative->isValid()) {
          yield [
            'uri' => $style ? $style->buildUri($file->getFileUri()) : $file->getFileUri(),
            'mimeType' => $derivative->getMimeType(),
            'width' => $derivative->getWidth(),
            'height' => $derivative->getHeight(),
            'fileSize' => $derivative->getFileSize(),
          ];
        }

      }
    }
  }

}
