<?php

namespace Drupal\graphql_core\Plugin\GraphQL\InputTypes\EntityQuery;

use Drupal\graphql\Plugin\GraphQL\InputTypes\InputTypePluginBase;

/**
 * @GraphQLInputType(
 *   id = "entity_query_filter_condition_input",
 *   name = "EntityQueryFilterConditionInput",
 *   fields = {
 *     "field" = "String!",
 *     "value" = "[String]",
 *     "operator" = "QueryOperator",
 *     "language" = "LanguageId",
 *     "skip" = false
 *   }
 * )
 */
class EntityQueryFilterConditionInput extends InputTypePluginBase {

}
