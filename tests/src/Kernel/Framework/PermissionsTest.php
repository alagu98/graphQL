<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\graphql\GraphQL\QueryProvider\QueryProviderInterface;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use PhpParser\Node\Arg;
use Prophecy\Argument;
use Drupal\graphql\GraphQL\ResolverBuilder;

/**
 * Test if query handling respects permissions properly.
 *
 * @group graphql
 */
class PermissionsTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  protected function userPermissions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $gql_schema = <<<GQL
      schema {
        query: Query
      }
      type Query {
        root: String
      }
GQL;
    $this->setUpSchema($gql_schema, $this->getDefaultSchema());
    $builder = new ResolverBuilder();

    $this->mockField('root', [
      'name' => 'root',
      'type' => 'String',
      'parent' => 'Query',
    ], $builder->fromValue('test'));
  }

  /**
   * Test if a user without permissions doesn't have access to any query.
   */
  public function testNoPermissions() {
    $this->accountProphecy->hasPermission(Argument::any())->willReturn(FALSE);

    // Any query should fail.
    $this->assertEquals(403, $this->query('query')->getStatusCode());

    $batched = $this->batchedQueries([
      ['query' => '{ root }'],
      ['query' => '{ root }'],
    ]);

    // If all batched queries fail, 403 is returned.
    $this->assertEquals(403, $batched->getStatusCode());
  }

  /**
   * Test access to arbitrary queries.
   *
   * The user is allowed to post any queries.
   */
  public function testFullQueryAccess() {
    $this->accountProphecy->hasPermission(Argument::is('execute graphql requests'))->willReturn(TRUE);
    $this->accountProphecy->hasPermission(Argument::not('execute graphql requests'))->willReturn(FALSE);

    // All queries should work.
    $this->assertEquals(200, $this->query('{ root }')->getStatusCode());

    $batched = $this->batchedQueries([
      ['query' => '{ root }'],
      ['query' => '{ root }'],
    ]);

    $this->assertEquals(200, $batched->getStatusCode());
    $data = [
      'data' => [
        'root' => 'test',
      ],
    ];
    $this->assertEquals([$data, $data], json_decode($batched->getContent(), TRUE));
  }

}
