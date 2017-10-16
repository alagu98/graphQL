<?php

namespace Drupal\Tests\graphql_content_mutation\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\Tests\graphql_core\Kernel\GraphQLFileTestBase;
use Drupal\user\Entity\Role;
use Drupal\graphql_content_mutation\ContentEntityMutationSchemaConfig;

/**
 * Test entity creation.
 *
 * @group graphql_content_mutation
 */
class CreateEntityTest extends GraphQLFileTestBase {
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'field',
    'text',
    'filter',
    'graphql_core',
    'graphql_content',
    'graphql_mutation',
    'graphql_content_mutation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig('node');
    $this->installConfig('filter');
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    $this->createContentType(['type' => 'test']);

    Role::load('anonymous')
      ->grantPermission('access content')
      ->grantPermission('create test content')
      ->save();


    // TODO: is this the right way to do it?
    $this->schemaConfig = new ContentEntityMutationSchemaConfig(\Drupal::configFactory());
    $this->schemaConfig->exposeEntityBundleMutations('node', 'test', ['create']);
  }

  /**
   * Test creation with a simple text field.
   */
  public function testCreationWithBodyField() {
    $values = [
      'title' => 'Test node',
      'body' => [
        'value' => 'I am fine with my body.',
      ],
    ];

    $result = $this->executeQueryFile('create.gql', ['input' => $values]);
    $entity = $result['data']['createNodeTest']['entity'];
    $errors = $result['data']['createNodeTest']['errors'];
    $violations = $result['data']['createNodeTest']['violations'];

    $this->assertEquals($values['title'], $entity['entityLabel'], 'Created entity with correct title.');
    $this->assertEquals($values['body']['value'], $entity['body']['value'], 'Created entity with correct body value.');
    $this->assertEmpty($errors, 'Entity creation succeeded without any errors.');
    $this->assertEmpty($violations, 'Entity creation succeeded without any constraint violations.');
  }

  /**
   * Test creation with missing values for a required field.
   */
  public function testCreationWithViolations() {
    $result = $this->executeQueryFile('create.gql', ['input' => []]);
    $errors = $result['data']['createNodeTest']['errors'];
    $violations = $result['data']['createNodeTest']['violations'];

    $this->assertEmpty($errors, 'No errors were thrown.');
    $this->assertNotEmpty($violations, 'Entity creation failed due to constraint violations.');
    $this->assertEquals([[
      'path' => 'title',
      'message' => 'This value should not be null.',
    ]], $violations, 'Entity creation failed due to constraint violations.');
  }

  /**
   * Test creation without the necessary permissions
   */
  public function testCreationWithoutPermission() {
    Role::load('anonymous')
      ->revokePermission('create test content')
      ->save();

    $result = $this->executeQueryFile('create.gql', ['input' => []]);
    $errors = $result['data']['createNodeTest']['errors'];

    $this->assertNotEmpty($errors, 'Failed to create entity.');
    $this->assertEquals(['You do not have the necessary permissions to create entities of this type.'], $errors, 'Failed to create entity due to missing permissions.');
  }

}
