<?php

namespace Drupal\graphql\Plugin\GraphQL;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Traversable;

class SchemaPluginManager extends DefaultPluginManager {

  /**
   * Static cache for plugin instances.
   *
   * @var object[]
   */
  protected $instances = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $pluginSubdirectory,
    Traversable $namespaces,
    ModuleHandlerInterface $moduleHandler,
    $pluginInterface,
    $pluginAnnotationName,
    $alterInfo
  ) {
    $this->alterInfo($alterInfo);

    parent::__construct(
      $pluginSubdirectory,
      $namespaces,
      $moduleHandler,
      $pluginInterface,
      $pluginAnnotationName
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($pluginId, array $configuration = []) {
    if (!array_key_exists($pluginId, $this->instances)) {
      $this->instances[$pluginId] = parent::createInstance($pluginId);
      if (!$this->instances[$pluginId] instanceof SchemaPluginInterface) {
        throw new \LogicException(sprintf('Plugin %s does not implement \Drupal\graphql\Plugin\GraphQL\SchemaPluginInterface.', $pluginId));
      }
    }

    return $this->instances[$pluginId];
  }

}
