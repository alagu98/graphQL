<?php

namespace Drupal\graphql\Plugin\GraphQL\Schema;

use Drupal\Core\Cache\CacheBackendInterface;
use GraphQL\Language\Parser;
use GraphQL\Utils\SchemaExtender;

/**
 * Allows to extend the GraphQL schema.
 *
 * When GraphQL schema is distributed across multiple files it might be useful
 * to allow extension of certain types. This schema plugin allows that. See an
 * example of code to extend the schema with using webonyx/graphql-php library:
 * https://github.com/webonyx/graphql-php/issues/180#issuecomment-444407411
 */
abstract class SdlExtendedSchemaPluginBase extends SdlSchemaPluginBase {

  /**
   * Retrieves the parsed AST of the extended schema definition.
   *
   * @return \GraphQL\Language\AST\DocumentNode
   *   The parsed extended schema document.
   */
  protected function getExtendedSchemaDocument() {
    // Only use caching of the parsed document if we aren't in development mode.
    if (empty($this->inDevelopment) && $cache = $this->astCache->get($this->getPluginId())) {
      return $cache->data;
    }

    $ast = Parser::parse($this->getExtendedSchemaDefinition());
    if (!empty($this->inDevelopment)) {
      $this->astCache->set($this->getPluginId(), $ast, CacheBackendInterface::CACHE_PERMANENT, ['graphql']);
    }

    return $ast;
  }

  /**
   * {@inheritdoc}
   */
  public function getSchema() {
    return SchemaExtender::extend(parent::getSchema(), $this->getExtendedSchemaDocument());
  }

  /**
   * Retrieves the raw extended schema definition string.
   *
   * @return string
   *   The extended schema definition.
   */
  abstract protected function getExtendedSchemaDefinition();

}
