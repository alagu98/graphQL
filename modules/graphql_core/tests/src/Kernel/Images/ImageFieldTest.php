<?php

namespace Drupal\Tests\graphql_core\Kernel\Images;

use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\graphql_core\Kernel\GraphQLContentTestBase;

/**
 * Test file attachments.
 *
 * @group graphql_image
 */
class ImageFieldTest extends GraphQLContentTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'file',
    'image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig('image');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('file');
    $this->addField('image', 'image');
  }

  /**
   * Test a simple file field.
   */
  public function testImageField() {
    $a = $this->createNode([
      'title' => 'Node A',
      'type' => 'test',
    ]);

    $a->image->generateSampleItems(1);

    $a->save();


    $style = ImageStyle::load('thumbnail');

    $dimensions = [
      'width' => $a->image[0]->width,
      'height' => $a->image[0]->height,
    ];

    $style->transformDimensions($dimensions, $a->image[0]->entity->getFileUri());

    // TODO: Check cache metadata.
    $metadata = $this->defaultCacheMetaData();
    $metadata->addCacheTags([
      'config:field.storage.node.image',
      'entity_bundles',
      'entity_field_info',
      'entity_types',
      'file:1',
      'node:1',
      // TODO: missing image style config cache tags?
    ]);

    $this->assertResults($this->getQueryFromFile('image.gql'), [
      'path' => '/node/' . $a->id(),
    ], [
      'route' => [
        'node' => [
          'image' => [[
            'alt' => $a->image->alt,
            'title' => $a->image->title,
            'entity' => ['url' => $a->image->entity->url()],
            'width' => $a->image[0]->width,
            'height' => $a->image[0]->height,
            'thumbnailImage' => [
              'url' => $style->buildUrl($a->image->entity->uri->value),
              'width' => $dimensions['width'],
              'height' => $dimensions['height'],
            ],
          ]],
        ],
      ],
    ], $metadata);
  }

}
