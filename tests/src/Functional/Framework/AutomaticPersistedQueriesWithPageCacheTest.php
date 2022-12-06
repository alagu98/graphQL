<?php

namespace Drupal\Tests\graphql\Functional\Framework;

use Drupal\Component\Serialization\Json;
use Drupal\graphql\Entity\Server;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\graphql\Functional\GraphQLFunctionalTestBase;
use Drupal\user\Entity\Role;

class AutomaticPersistedQueriesWithPageCacheTest extends GraphQLFunctionalTestBase {

  /**
   * The GraphQL server.
   *
   * @var \Drupal\graphql\Entity\Server $server
   */
  protected Server $server;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'page_cache',
    'dynamic_page_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Node Type used to create test articles.
    NodeType::create([
      'type' => 'article',
    ])->save();

    // Create some test articles.
    Node::create([
      'nid' => 1,
      'type' => 'article',
      'title' => 'Test Article 1',
    ])->save();

    Node::create([
      'nid' => 2,
      'type' => 'article',
      'title' => 'Test Article 2',
    ])->save();

    $config = [
      'schema' => 'testing',
      'name' => 'testing',
      'endpoint' => '/graphql-testing',
      'persisted_queries_settings' => [
        'automatic_persisted_query' => [
          'weight' => 0,
        ],
      ],
    ];

    $this->server = Server::create($config);
    $this->server->save();
    \Drupal::service('router.builder')->rebuild();

    $anonymousRole = Role::load(Role::ANONYMOUS_ID);
    $this->grantPermissions($anonymousRole, [
        'execute ' . $this->server->id() . ' persisted graphql requests',
        'execute ' . $this->server->id() . ' arbitrary graphql requests'
      ]
    );
  }

  /**
   * Test that dynamic page cache correctly add cache context to queries
   * with the same query hash, but different variables.
   */
  public function testPageCacheWithDifferentVariables() {
    $query = $this->getQueryFromFile('article.gql');
    $variables1 = '{"id": 1}';
    $variables2 = '{"id": 2}';

    // Test that requests with different variables but same query hash return
    // different responses. Requesting in both instances with query first,
    // to make sure the query is registered.
    $this->apqRequest($this->server->endpoint, $query, $variables1, TRUE);
    $response = $this->apqRequest($this->server->endpoint, $query, $variables1);
    $this->assertEquals('Test Article 1', $response['data']['article']['title']);

    $this->apqRequest($this->server->endpoint, $query, $variables2, TRUE);
    $response = $this->apqRequest($this->server->endpoint, $query, $variables2);
    $this->assertEquals('Test Article 2', $response['data']['article']['title']);
  }

  /**
   * Test PersistedQueryNotFound error is not read from page cache after the
   * persisted query was created.
   */
  public function testPersistedQueryNotFoundNotCached() {
    $query = $this->getQueryFromFile('article.gql');
    $variables = '{"id": 1}';

    // The first request should return an PersistedQueryNotFound error.
    $this->assertPersistedQueryNotFound($query, $variables);

    // Retry with the query included.
    $response = $this->apqRequest($this->server->endpoint, $query, $variables, TRUE);
    $this->assertEquals('Test Article 1', $response['data']['article']['title']);

    // Finally a request without the query should return the correct data.
    $response = $this->apqRequest($this->server->endpoint, $query, $variables);
    $this->assertEquals('Test Article 1', $response['data']['article']['title']);
  }

}