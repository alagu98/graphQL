<?php

namespace Drupal\graphql_core\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\graphql_core\GraphQLSchemaManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create GraphQL context fields based on available Drupal contexts.
 */
class ContextDeriver extends DeriverBase implements ContainerDeriverInterface {
  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * A schema manager instance to identify graphql return types.
   *
   * @var \Drupal\graphql_core\GraphQLSchemaManagerInterface
   */
  protected $schemaManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('graphql.context_repository'), $container->get('graphql.schema_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ContextRepositoryInterface $contextRepository, GraphQLSchemaManagerInterface $schemaManager) {
    $this->schemaManager = $schemaManager;
    $this->contextRepository = $contextRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (empty($this->derivatives)) {
      foreach ($this->contextRepository->getAvailableContexts() as $id => $context) {

        $dataType = $context->getContextDefinition()->getDataType();

        $this->derivatives[$id] = [
          'name' => graphql_core_propcase($id) . 'Context',
          'context_id' => $id,
          'nullable' => TRUE,
          'multi' => FALSE,
          'dataType' => $dataType,
        ] + $base_plugin_definition;
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
