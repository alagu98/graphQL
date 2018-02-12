<?php

namespace Drupal\graphql_core\Plugin\GraphQL\Fields\Routing;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\graphql\GraphQL\Cache\CacheableValue;
use Drupal\graphql\Plugin\GraphQL\Fields\FieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Youshido\GraphQL\Execution\ResolveInfo;

/**
 * @GraphQLField(
 *   id = "url_path",
 *   secure = true,
 *   name = "path",
 *   description = @Translation("The processed url path."),
 *   type = "String",
 *   parents = {"Url"},
 *   arguments = {
 *     "language" = "LanguageId"
 *   }
 * )
 */
class Path extends FieldPluginBase implements ContainerFactoryPluginInterface {
  use DependencySerializationTrait;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Path constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    LanguageManagerInterface $languageManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolveValues($value, array $args, ResolveInfo $info) {
    if ($value instanceof Url) {
      $language = isset($args['language']) ? $this->languageManager->getLanguage($args['language']) : NULL;
      if (!empty($language)) {
        // Copy the url object and set the language.
        $value = (clone $value)->setOption('language', $language);
      }

      $url = $value->toString(TRUE);
      yield new CacheableValue($url->getGeneratedUrl(), [$url]);
    }
  }

}
