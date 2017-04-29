<?php

namespace Drupal\graphql_plugin_test\Plugin\GraphQL\Mutations;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql_core\GraphQLSchemaManagerInterface;
use Drupal\graphql_core\GraphQL\MutationPluginBase;
use Drupal\graphql_plugin_test\GarageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Youshido\GraphQL\Execution\ResolveInfo;

/**
 * A test mutation.
 *
 * @GraphQLMutation(
 *   name="buyCar",
 *   type="Car",
 *   arguments = {
 *     "car" = "CarInput"
 *   }
 * )
 */
class BuyCar extends MutationPluginBase implements ContainerFactoryPluginInterface {
  use DependencySerializationTrait;

  /**
   * The plugin manager.
   *
   * @var \Drupal\graphql_core\GraphQLSchemaManagerInterface
   */
  protected $pluginManager;

  /**
   * The garage.
   *
   * @var \Drupal\graphql_plugin_test\GarageInterface
   */
  protected $garage;

  /**
   * {@inheritdoc}
   */
  public function resolve($value, array $args, ResolveInfo $info) {
    return $this->garage->insertVehicle($args['car']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('graphql.schema_manager'), $container->get('graphql_test.garage'));
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GraphQLSchemaManagerInterface $schemaManager, GarageInterface $garage) {
    $this->schemaManager = $schemaManager;
    $this->garage = $garage;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

}
