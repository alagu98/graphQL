<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Drupal\graphql\Entity\Server;

/**
 * Test disabled result cache.
 *
 * @group graphql
 */
class DisabledResultCacheTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $schema = <<<GQL
      type Query {
        root: String
      }
GQL;

    $this->setUpSchema($schema);
  }

  /**
   * Test if disabling the result cache has the desired effect.
   */
  public function testDisabledCache() {
    $object = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->setMethods(['id'])
      ->getMock();

    $object->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');

    $this->mockResolver('Query', 'root', function () use ($object) {
      return $object->id();
    });

    // The first request that is not supposed to be cached.
    $this->query('{ root }');

    // This should invoke the processor a second time.
    $this->query('{ root }');
  }

}
