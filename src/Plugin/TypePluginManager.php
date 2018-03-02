<?php

namespace Drupal\graphql\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class TypePluginManager extends DefaultPluginManager {

  /**
   * Static cache of plugin instances.
   *
   * @var \Drupal\graphql\Plugin\TypePluginInterface[]
   */
  protected $instances;

  /**
   * TypePluginManager constructor.
   *
   * @param bool|string $pluginSubdirectory
   *   The plugin's subdirectory.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param string|null $pluginInterface
   *   The interface each plugin should implement.
   * @param string $pluginAnnotationName
   *   The name of the annotation that contains the plugin definition.
   * @param string $pluginType
   *   The plugin type.
   */
  public function __construct(
    $pluginSubdirectory,
    \Traversable $namespaces,
    ModuleHandlerInterface $moduleHandler,
    $pluginInterface,
    $pluginAnnotationName,
    $pluginType
  ) {
    parent::__construct(
      $pluginSubdirectory,
      $namespaces,
      $moduleHandler,
      $pluginInterface,
      $pluginAnnotationName
    );

    $this->alterInfo("graphql_{$pluginType}");
    $this->useCaches(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    if (!isset($this->instances[$options['id']])) {
      $this->instances[$options['id']] = $this->createInstance($options['id']);
    }

    return $this->instances[$options['id']];
  }

}
