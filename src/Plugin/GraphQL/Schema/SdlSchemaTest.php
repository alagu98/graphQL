<?php

namespace Drupal\graphql\Plugin\GraphQL\Schema;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;

/**
 * @Schema(
 *   id = "test",
 *   name = "Test schema"
 * )
 * @codeCoverageIgnore
 */
class SdlSchemaTest extends SdlSchemaPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function getSchemaDefinition() {
    return <<<GQL
      schema {
        query: Query
      }

      type Query {
        article(id: Int!): Article
        page(id: Int!): Page
        node(id: Int!): NodeInterface
        label(id: Int!): String
      }

      type Article implements NodeInterface {
        id: Int!
        uid: String
        title: String!
        render: String
      }

      type Page implements NodeInterface {
        id: Int!
        uid: String
        title: String
      }

      interface NodeInterface {
        id: Int!
      }
GQL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getResolverRegistry() {
    $builder = new ResolverBuilder();
    $registry = new ResolverRegistry([
      'Article' => ContextDefinition::create('entity:node')
        ->addConstraint('Bundle', 'article'),
      'Page' => ContextDefinition::create('entity:node')
        ->addConstraint('Bundle', 'page'),
    ]);

    $registry->addFieldResolver('Query', 'node',
      $builder->produce('entity_load', ['mapping' => [
        'entity_type' => $builder->fromValue('node'),
        'entity_id' => $builder->fromArgument('id'),
      ]])
    );

    $registry->addFieldResolver('Query', 'label',
      $builder->produce('entity_label', ['mapping' => [
        'entity' => $builder->produce('entity_load', ['mapping' => [
          'entity_type' => $builder->fromValue('node'),
          'entity_bundle' => $builder->fromValue(['article']),
          'entity_id' => $builder->fromArgument('id'),
        ]])
      ]])
    );

    $registry->addFieldResolver('Query', 'article',
      $builder->produce('entity_load', ['mapping' => [
        'type' => $builder->fromValue('node'),
        'bundles' => $builder->fromValue(['article']),
        'id' => $builder->fromArgument('id'),
      ]])
    );

    $registry->addFieldResolver('Query', 'page',
      $builder->produce('entity_load', ['mapping' => [
        'entity_type' => $builder->fromValue('node'),
        'entity_bundle' => $builder->fromValue(['page']),
        'entity_id' => $builder->fromArgument('id'),
      ]])
    );

    $registry->addFieldResolver('Article', 'id',
      $builder->produce('entity_id', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    $registry->addFieldResolver('Article', 'title', $builder->compose(
      $builder->produce('entity_label', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]]),
      $builder->produce('uppercase', ['mapping' => [
        'string' => $builder->fromParent(),
      ]])
    ));

    $registry->addFieldResolver('Article', 'render',
      $builder->produce('entity_rendered', ['mapping' => [
        'entity' => $builder->fromParent(),
        'mode' => $builder->fromValue('full')
      ]])
    );

    $registry->addFieldResolver('Page', 'id',
      $builder->produce('entity_id', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    return $registry;
  }
}
