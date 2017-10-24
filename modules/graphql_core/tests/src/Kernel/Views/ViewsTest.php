<?php

namespace Drupal\Tests\graphql_core\Kernel\Views;

/**
 * Test views support in GraphQL.
 *
 * @group graphql_core
 */
class ViewsTest extends ViewsTestBase {

  /**
   * Test that the view returns both nodes.
   */
  public function testSimpleView() {

    $result = $this->executeQueryFile('Views/simple.gql');

    $this->assertEquals([
      [
        'entityLabel' => 'Node A',
      ], [
        'entityLabel' => 'Node B',
      ], [
        'entityLabel' => 'Node C',
      ],
    ], $result['data']['graphqlTestSimpleView']['results']);
  }

  /**
   * Test paging support.
   */
  public function testPagedView() {
    $result = $this->executeQueryFile('Views/paged.gql');
    $this->assertEquals([
      'page_one' => [
        'count' => count($this->letters),
        'results' => [
          ['entityLabel' => 'Node A'],
          ['entityLabel' => 'Node B'],
        ],
      ],
      'page_two' => [
        'count' => count($this->letters),
        'results' => [
          ['entityLabel' => 'Node C'],
          ['entityLabel' => 'Node A'],
        ],
      ],
      'page_three' => [
        'count' => count($this->letters),
        'results' => [
          ['entityLabel' => 'Node A'],
          ['entityLabel' => 'Node B'],
          ['entityLabel' => 'Node C'],
        ],
      ],
      'page_four' => [
        'count' => count($this->letters),
        'results' => [
          ['entityLabel' => 'Node C'],
        ],
      ],
    ], $result['data'], 'Paged views return the correct results.');
  }

  /**
   * Test sorting behavior.
   */
  public function testSortedView() {
    $result = $this->executeQueryFile('Views/sorted.gql');
    $this->assertEquals([
      'default' => [
        'results' => [
          ['entityLabel' => 'Node A'],
          ['entityLabel' => 'Node B'],
          ['entityLabel' => 'Node C'],
        ],
      ],
      'asc' => [
        'results' => [
          ['entityLabel' => 'Node A'],
          ['entityLabel' => 'Node A'],
          ['entityLabel' => 'Node A'],
        ],
      ],
      'desc' => [
        'results' => [
          ['entityLabel' => 'Node C'],
          ['entityLabel' => 'Node C'],
          ['entityLabel' => 'Node C'],
        ],
      ],
      'asc_nid' => [
        'results' => [
          ['entityLabel' => 'Node A'],
          ['entityLabel' => 'Node B'],
          ['entityLabel' => 'Node C'],
        ],
      ],
      'desc_nid' => [
        'results' => [
          ['entityLabel' => 'Node C'],
          ['entityLabel' => 'Node B'],
          ['entityLabel' => 'Node A'],
        ],
      ],
    ], $result['data'], 'Sorting works as expected.');
  }

  /**
   * Test filter behavior.
   */
  public function testFilteredView() {
    $result = $this->executeQueryFile('Views/filtered.gql');
    $this->assertEquals([
      ['entityLabel' => 'Node A'],
      ['entityLabel' => 'Node A'],
      ['entityLabel' => 'Node A'],
    ], $result['data']['default']['results'], 'Filtering works as expected.');
  }

  /**
   * Test filter behavior.
   */
  public function testMultiValueFilteredView() {
    $result = $this->executeQueryFile('Views/filtered.gql');
    $this->assertEquals([
      ['entityLabel' => 'Node A'],
      ['entityLabel' => 'Node B'],
      ['entityLabel' => 'Node A'],
      ['entityLabel' => 'Node B'],
      ['entityLabel' => 'Node A'],
      ['entityLabel' => 'Node B'],
    ], $result['data']['multi']['results'], 'Filtering works as expected.');
  }

  /**
   * Test complex filters.
   */
  public function testComplexFilteredView() {
    $result = $this->executeQueryFile('Views/filtered.gql');
    $this->assertEquals([
      ['entityLabel' => 'Node A'],
      ['entityLabel' => 'Node B'],
      ['entityLabel' => 'Node C'],
      ['entityLabel' => 'Node A'],
      ['entityLabel' => 'Node B'],
      ['entityLabel' => 'Node C'],
      ['entityLabel' => 'Node A'],
      ['entityLabel' => 'Node B'],
      ['entityLabel' => 'Node C'],
    ], $result['data']['complex']['results'], 'Filtering works as expected.');
  }

  /**
   * Test the result type for views with (and without) a single-value bundle filter.
   */
  public function testSingleValueBundleFilterView() {
    $result = $this->executeQueryFile('Views/single_bundle_filter.gql');
    $this->assertEquals('NodeTest', $result['data']['withSingleBundleFilter']['results'][0]['__typename'], 'View result types work as expected.');
  }

}
