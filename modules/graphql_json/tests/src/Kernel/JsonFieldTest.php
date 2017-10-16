<?php

namespace Drupal\Tests\graphql_json\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\simpletest\NodeCreationTrait;
use Drupal\Tests\graphql_core\Kernel\GraphQLFileTestBase;
use Drupal\user\Entity\Role;

/**
 * Test json graphql fields.
 *
 * @group graphql_xml
 */
class JsonFieldTest extends GraphQLFileTestBase {
  use NodeCreationTrait;

  public static $modules = [
    'system',
    'path',
    'field',
    'text',
    'filter',
    'file',
    'graphql_file',
    'node',
    'user',
    'graphql',
    'graphql_core',
    'graphql_content',
    'graphql_file',
    'graphql_json',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig('user');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('file');

    Role::load('anonymous')
      ->grantPermission('access content')
      ->save();

    NodeType::create([
      'name' => 'graphql',
      'type' => 'graphql',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'json',
      'type' => 'text_long',
      'entity_type' => 'node',
    ])->save();

    FieldConfig::create([
      'field_name' => 'json',
      'entity_type' => 'node',
      'bundle' => 'graphql',
      'label' => 'Json',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'file',
      'type' => 'file',
      'entity_type' => 'node',
    ])->save();

    FieldConfig::create([
      'field_name' => 'file',
      'entity_type' => 'node',
      'bundle' => 'graphql',
      'label' => 'File',
    ])->save();

    EntityViewMode::create([
      'targetEntityType' => 'node',
      'id' => "node.graphql",
    ])->save();

    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'graphql',
      'mode' => 'graphql',
      'status' => TRUE,
    ])
      ->setComponent('json', ['type' => 'graphql_json'])
      ->setComponent('file', ['type' => 'graphql_file'])
      ->save();

    $this->schemaConfig->exposeEntityBundle('node', 'graphql', 'node.graphql');
    $this->schemaConfig->exposeEntityBundle('file', 'file');
  }

  /**
   * Test json text fields.
   */
  public function testJsonTextField() {
    $entity = Node::create([
      'type' => 'graphql',
      'title' => 'Json text field test',
      'json' => '{"test":"test"}',
    ]);
    $entity->save();

    $result = $this->executeQueryFile('field_text.gql', [
      'path' => '/node/' . $entity->id(),
    ], TRUE, TRUE);

    $this->assertEquals([
      'path' => [
        'value' => 'test',
      ],
    ], $result['data']['route']['entity']['json']);
  }

  /**
   * Test json file fields.
   */
  public function testJsonFileField() {
    $file = File::create([
      'uri' => drupal_get_path('module', 'graphql_json') . '/tests/files/test.json',
    ]);
    $file->save();
    $entity = Node::create([
      'type' => 'graphql',
      'title' => 'Json text field test',
      'file' => $file,
    ]);
    $entity->save();

    $result = $this->executeQueryFile('field_file.gql', [
      'path' => '/node/' . $entity->id(),
    ], TRUE, TRUE);

    $this->assertEquals([
      'path' => [
        'value' => 'test',
      ],
    ], $result['data']['route']['entity']['file']['json']);
  }

}
