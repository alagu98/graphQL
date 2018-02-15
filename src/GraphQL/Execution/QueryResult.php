<?php

namespace Drupal\graphql\GraphQL\Execution;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;

class QueryResult implements CacheableDependencyInterface {

  /**
   * The query result.
   *
   * @var mixed
   */
  protected $data;

  /**
   * The cache metadata from the response and the schema.
   *
   * @var \Drupal\Core\Cache\CacheableDependencyInterface
   */
  protected $metadata;

  /**
   * QueryResult constructor.
   *
   * @param $data
   *   Result data.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $metadata
   *   The cache metadata collected during query execution.
   */
  public function __construct($data, CacheableDependencyInterface $metadata) {
    $this->data = $data;
    $this->metadata = $metadata;
  }

  /**
   * Retrieve query result data.
   *
   * @return mixed
   *   The result data object.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->metadata->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->metadata->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->metadata->getCacheMaxAge();
  }

}