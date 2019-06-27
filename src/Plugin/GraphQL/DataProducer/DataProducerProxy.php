<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\GraphQL\Resolver\ResolverInterface;
use Drupal\graphql\GraphQL\Utility\DeferredUtility;
use Drupal\graphql\Plugin\DataProducerPluginCachingInterface;
use Drupal\graphql\Plugin\DataProducerPluginInterface;
use Drupal\graphql\Plugin\DataProducerPluginManager;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Data producers proxy class.
 */
class DataProducerProxy implements ResolverInterface {

  /**
   * The plugin config.
   *
   * @var array
   */
  protected $config;

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $id;

  /**
   * The plugin manager.
   *
   * @var \Drupal\graphql\Plugin\DataProducerPluginManager
   */
  protected $pluginManager;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected $contextsManager;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * @var array
   */
  protected $mapping = [];

  /**
   * @var boolean
   */
  protected $cached = FALSE;

  /**
   * Construct DataProducerProxy object.
   *
   * @param string $id
   *   DataProducer plugin id.
   * @param array $mapping
   * @param array $config
   *   Plugin configuration.
   * @param \Drupal\graphql\Plugin\DataProducerPluginManager $pluginManager
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $contextsManager
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   */
  public function __construct(
    $id,
    array $mapping,
    array $config,
    DataProducerPluginManager $pluginManager,
    RequestStack $requestStack,
    CacheContextsManager $contextsManager,
    CacheBackendInterface $cacheBackend
  ) {
    $this->id = $id;
    $this->mapping = $mapping;
    $this->config = $config;
    $this->pluginManager = $pluginManager;
    $this->requestStack = $requestStack;
    $this->contextsManager = $contextsManager;
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * @param $id
   * @param array $mapping
   * @param array $config
   *
   * @return mixed
   */
  public static function create($id, array $mapping = [], array $config = []) {
    $manager = \Drupal::service('plugin.manager.graphql.data_producer');
    return $manager->proxy($id, $mapping, $config);
  }

  /**
   * @param $name
   * @param \Drupal\graphql\GraphQL\Resolver\ResolverInterface $mapping
   *
   * @return $this
   */
  public function map($name, ResolverInterface $mapping) {
    $this->mapping[$name] = $mapping;
    return $this;
  }

  /**
   * @param bool $cached
   *
   * @return $this
   */
  public function cached($cached = TRUE) {
    $this->cached = $cached;
    return $this;
  }

  /**
   * Resolve field value.
   *
   * @param $value
   * @param $args
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function resolve($value, $args, ResolveContext $context, ResolveInfo $info, FieldContext $field) {
    $plugin = $this->prepare($value, $args, $context, $info, $field);

    return DeferredUtility::returnFinally($plugin, function (DataProducerPluginInterface $plugin) use ($context, $field) {
      if ($this->cached && $plugin instanceof DataProducerPluginCachingInterface) {
        if (!!$context->getServer()->get('caching')) {
          return $this->resolveCached($plugin, $context, $field);
        }
      }

      return $this->resolveUncached($plugin, $context, $field);
    });
  }

  /**
   * @param $value
   * @param $args
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return \GraphQL\Deferred|mixed
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  protected function prepare($value, $args, ResolveContext $context, ResolveInfo $info, FieldContext $field) {
    /** @var DataProducerPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($this->id, $this->config);
    $contexts = [];

    foreach ($plugin->getContextDefinitions() as $name => $definition) {
      $mapper = $this->mapping[$name] ?? NULL;
      if (isset($mapper)) {
        if (!$mapper instanceof ResolverInterface) {
          throw new \Exception(sprintf('Invalid input mapper for argument %s.', $name));
        }

        $contexts[$name] = $mapper->resolve($value, $args, $context, $info, $field);
      }
    }

    $contexts = DeferredUtility::waitAll($contexts);
    return DeferredUtility::returnFinally($contexts, function ($contexts) use ($plugin) {
      foreach ($contexts as $name => $context) {
        $plugin->setContextValue($name, $context);
      }

      return $plugin;
    });
  }

  /**
   * @param \Drupal\graphql\Plugin\DataProducerPluginInterface $plugin
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return mixed
   */
  protected function resolveUncached(DataProducerPluginInterface $plugin, ResolveContext $context, FieldContext $field) {
    $output = $plugin->resolveField($field);
    return DeferredUtility::applyFinally($output, function () use ($context, $plugin, $field) {
      $field->addCacheableDependency($plugin);
    });
  }

  /**
   * @param \Drupal\graphql\Plugin\DataProducerPluginCachingInterface $plugin
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *
   * @return mixed
   */
  protected function resolveCached(DataProducerPluginCachingInterface $plugin, ResolveContext $context, FieldContext $field) {
    $prefix = $this->edgeCachePrefix($plugin);
    if ($cache = $this->cacheRead($prefix)) {
      list($value, $metadata) = $cache;
      $field->addCacheableDependency($metadata);
      return $value;
    }

    $output = $this->resolveUncached($plugin, $context, $field);
    return DeferredUtility::applyFinally($output, function ($value) use ($context, $field, $prefix) {
      if ($field->getCacheMaxAge() === 0) {
        return;
      }

      $this->cacheWrite($prefix, $value, $field);
    });
  }

  /**
   * @param \Drupal\graphql\Plugin\DataProducerPluginCachingInterface $plugin
   *
   * @return string
   */
  protected function edgeCachePrefix(DataProducerPluginCachingInterface $plugin) {
    $id = $plugin->getPluginId();
    $keys = $this->contextsManager->convertTokensToKeys($plugin->getCacheContexts())->getKeys();

    try {
      $vectors = $plugin->edgeCachePrefix();
    }
    catch (\Exception $e) {
      throw new \LogicException(sprintf('Failed to serialize edge cache vectors for plugin %s.', $id));
    }

    return md5(serialize([$id, $vectors, $keys]));
  }

  /**
   * @param $prefix
   *
   * @return array|null
   */
  protected function cacheRead($prefix) {
    if ($cache = $this->cacheBackend->get("$prefix:context")) {
      $keys = !empty($cache->data) ? $this->contextsManager->convertTokensToKeys($cache->data)->getKeys() : [];
      $keys = serialize($keys);

      if (($cache = $this->cacheBackend->get("$prefix:result:$keys")) && $data = $cache->data) {
        return $data;
      }
    }

    return NULL;
  }

  /**
   * @param $prefix
   * @param $value
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   */
  protected function cacheWrite($prefix, $value, FieldContext $field) {
    $expire = $this->maxAgeToExpire($field->getCacheMaxAge());
    $tags = $field->getCacheTags();
    $tokens = $field->getCacheContexts();

    $keys = !empty($tokens) ? $this->contextsManager->convertTokensToKeys($tokens)->getKeys() : [];
    $keys = serialize($keys);

    $metadata = new CacheableMetadata();
    $metadata->addCacheableDependency($field);

    $this->cacheBackend->setMultiple([
      "$prefix:context" => [
        'data' => $tokens,
        'expire' => $expire,
        'tags' => $tags,
      ],
      "$prefix:result:$keys" => [
        'data' => [$value, $metadata],
        'expire' => $expire,
        'tags' => $tags,
      ],
    ]);
  }

  /**
   * Maps a cache max age value to an "expire" value for the Cache API.
   *
   * @param int $maxAge
   *
   * @return int
   *   A corresponding "expire" value.
   *
   * @see \Drupal\Core\Cache\CacheBackendInterface::set()
   */
  protected function maxAgeToExpire($maxAge) {
    $time = $this->requestStack->getMasterRequest()->server->get('REQUEST_TIME');
    return ($maxAge === Cache::PERMANENT) ? Cache::PERMANENT : (int) $time + $maxAge;
  }

}
