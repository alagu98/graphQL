# Creating a custom schema

The best way to start making a new schema is to take the test schema provided by the GraphQL module in `src/Plugin/GraphQL/Schema/SdlSchemaTest.php` and add it to a custom module of your own. By doing this you can then start adapting the schema to your needs including your own Content types and making them available in the schema.

 The code with all the demo queries and mutations in these docs can be found in [this repository](https://github.com/joaogarin/mydrupalgql).

## Clone the SdlSchemaTest

Head to the graphql module folder and copy `src/Plugin/GraphQL/Schema/SdlSchemaTest.php` to your own module which we will call `mydrupalgql`. First make sure you have a .info file inside the module to make sure drupal will know about this module (for more info see [Custom modules in drupal](https://www.drupal.org/docs/8/creating-custom-modules)) Inside `modules/mydrupalgql` create a similar file for your custom schema `src/Plugin/GraphQL/Schema/SdlSchemaMyDrupalGql.php`. Make sure to adapt the namespaces on the top of the file, in the end should look something like this (some parts of the schema are marked with `...` for simplicity here. Just copy the whole thing in your own module): 

```php 

namespace Drupal\mydrupalgql\Plugin\GraphQL\Schema;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\Plugin\GraphQL\Schema\SdlSchemaPluginBase;

/**
 * @Schema(
 *   id = "mydrupalgql",
 *   name = "My Drupal Graphql schema"
 * )
 * @codeCoverageIgnore
 */
class SdlSchemaMyDrupalGql extends SdlSchemaPluginBase {

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

      ...

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

    ...

    return $registry;
  }
}
```

## Enable the custom schema

Go again to the server page in `/admin/config/graphql` to create a new server now for the newly created schema. When creating the server choose the "My Drupal Graphql schema" as the schema. After saving click "Explorer" and this should take you to the "Graphiql" page, but now already for you own custom schema.

