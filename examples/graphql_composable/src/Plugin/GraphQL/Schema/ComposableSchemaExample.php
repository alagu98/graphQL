<?php

namespace Drupal\graphql_composable\Plugin\GraphQL\Schema;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\Plugin\GraphQL\Schema\ComposableSchema;

/**
 * @Schema(
 *   id = "composable",
 *   name = "Composable Example schema",
 *   extensions = "composable",
 * )
 */
class ComposableSchemaExample extends ComposableSchema {

  /**
   * {@inheritdoc}
   */
  public function getResolverRegistry() {
    // Initialize registry which will register all the field and type resolvers
    // we'd need. Initialize it with default field resolver which is used as
    // a fallback option to resolve the generic fields or fields following
    // some specific rules. Can be initialized also with default type resolver
    // (second argument to ResolverRegistry constructor).
    $registry = new ResolverRegistry([
      __CLASS__,
      'defaultFieldResolver',
    ]);

    return $registry;
  }

  /**
   * The default field resolver.
   *
   * Used if no field resolver was explicitly registered.
   *
   * @param array|\ArrayAccess|object $source
   *   The source (parent) value.
   * @param array $args
   *   An array of arguments.
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   *   The context object.
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *   The resolve info object.
   *
   * @return \Drupal\graphql\GraphQL\Resolver\ResolverInterface|\Closure|array
   *   The result for the field.
   */
  public static function defaultFieldResolver($source, array $args, ResolveContext $context, ResolveInfo $info) {
    $fieldName = $info->fieldName;
    $property = NULL;

    // Resolve violations which are stored under "errors" key.
    if ($source instanceof ResponseInterface && $fieldName == 'errors') {
      return $source->getViolations();
    }

    return $property;
  }
}
