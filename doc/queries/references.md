# Querying entity references

Continuing on our previous example lets add a new field that in this case is an entity reference for example for a taxonomy term.

Again we need to do similar steps to add the field to the schema and add its resolver.

## Add the schema declaration

The first step as seen in the introduction is to add the types and fields in the schema. We can do this directly in the schema string in your own schema implementation.

```
...

type Article implements NodeInterface {
    id: Int!
    title: String!
    creator: String
    tags: [TagTerm]
}

type TagTerm {
    id: Int
    name: String
}

...

```
Now we have an article that also has a custom entity reference field to a taxonomy category (the field name in Drupal is `field_tags`) and we make a new type `TagTerm` that has the necessary information about this term.

We will need to resolve not only the `tags` field but also the `id` and `name` of the term. 

## Adding resolvers

Again inside the `getResolverRegistry` method :

```php
/**
   * {@inheritdoc}
   */
  protected function getResolverRegistry() {
    
    ...
    
    $registry->addFieldResolver('Article', 'tags',
      $builder->produce('entity_reference', [
        'mapping' => [
          'entity' => $builder->fromParent(),
          'field' => $builder->fromValue('field_tags'),
        ],
      ])
    );

    $registry->addFieldResolver('TagTerm', 'id',
      $builder->produce('entity_id', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    $registry->addFieldResolver('TagTerm', 'name',
      $builder->produce('entity_label', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    ...

    return $registry;
  }
```

Notice how again we are using common Data producers provided by the module like `entity_id`, `entity_label` and also `entity_reference` in this case.
