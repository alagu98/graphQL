<?php

namespace Drupal\graphql_content\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\graphql\Utility\StringHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\graphql_content\ContentEntitySchemaConfig;

/**
 * Configuration form to define GraphQL schema content entity types and fields.
 */
class ContentEntitySchemaConfigForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $invalidator;

  /**
   * The schema configuration service.
   *
   * @var \Drupal\graphql_content\ContentEntitySchemaConfig
   */
  protected $schemaConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeBundleInfoInterface $bundleInfo,
    CacheTagsInvalidatorInterface $invalidator,
    ContentEntitySchemaConfig $schemaConfig
  ) {
    parent::__construct($configFactory);
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->invalidator = $invalidator;
    $this->schemaConfig = $schemaConfig;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('cache_tags.invalidator'),
      $container->get('graphql_content.schema_config')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_entity_schema_configuration';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['graphql_content.schema'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Configure which content entity types, bundles and fields will be added to the GraphQL schema.'),
    ];

    $form['types'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Add interfaces and types'),
        $this->t('Attach fields from view mode'),
      ],
    ];

    /** @var EntityViewMode[] $modes */
    $modes = EntityViewMode::loadMultiple();

    foreach ($this->entityTypeManager->getDefinitions() as $type) {
      if ($type instanceof ContentEntityTypeInterface) {

        $entityType = $type->id();

        $form['types'][$entityType]['exposed'] = [
          '#type' => 'checkbox',
          '#default_value' => $this->schemaConfig->isEntityTypeExposed($entityType),
          '#title' => '<strong>' . $type->getLabel() . '</strong>',
          '#description' => $this->t('Add the <strong>%interface</strong> interface to the schema.', [
            '%interface' => StringHelper::camelCase($entityType),
          ]),
          '#wrapper_attributes' => ['colspan' => 2, 'class' => ['highlight']],
        ];

        foreach ($this->bundleInfo->getBundleInfo($entityType) as $bundle => $info) {
          $key = $entityType . '__' . $bundle;

          $isEntityBundleExposed = $this->schemaConfig->isEntityBundleExposed($entityType, $bundle);
          $form['types'][$key]['exposed'] = [
            '#type' => 'checkbox',
            '#parents' => ['types', $entityType, 'bundles', $bundle, 'exposed'],
            '#default_value' => $isEntityBundleExposed,
            '#states' => [
              'enabled' => [
                ':input[name="types[' . $entityType . '][exposed]"]' => ['checked' => TRUE],
              ],
            ],
            '#title' => $info['label'],
            '#description' => $this->t('Add the <strong>%type</strong> type to the schema.', [
              '%type' => StringHelper::camelCase([$entityType, $bundle]),
            ]),
          ];

          $options = [
            '__none__' => $this->t("Don't attach fields."),
            $entityType . '.default' => $this->t('Default'),
          ];

          foreach ($modes as $mode) {
            /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
            if ($mode->getTargetType() == $entityType) {
              $options[$mode->id()] = $mode->label();
            }
          }

          $defaultViewMode = $this->schemaConfig->getExposedViewMode($entityType, $bundle);
          if (!$isEntityBundleExposed && (empty($defaultViewMode)) || $defaultViewMode == '__none__') {
            // Use graphql view mode as default.
            $graphqlViewMode = $entityType . '.graphql';
            if (isset($options[$graphqlViewMode])) {
              $defaultViewMode = 'graphql';
            }
          }
          $form['types'][$key]['view_mode'] = [
            '#type' => 'select',
            '#parents' => [
              'types', $entityType, 'bundles', $bundle, 'view_mode',
            ],
            '#default_value' => $entityType . '.' . $defaultViewMode,
            '#options' => $options,
            '#attributes' => [
              'width' => '100%',
            ],
            '#states' => [
              'enabled' => [
                ':input[name="types[' . $entityType . '][exposed]"]' => ['checked' => TRUE],
                ':input[name="types[' . $entityType . '][bundles][' . $bundle . '][exposed]"]' => ['checked' => TRUE],
              ],
            ],
          ];
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = $form_state->getValue('types');

    // Sanitize boolean values.
    foreach (array_keys($types) as $entityType) {
      $exposed = (bool) $types[$entityType]['exposed'];
      $exposed ? $this->schemaConfig->exposeEntity($entityType) : $this->schemaConfig->unexposeEntity($entityType);

      if (!empty($types[$entityType]['bundles'])) {
        $bundles = array_keys($types[$entityType]['bundles']);
        foreach ($bundles as $bundle) {
          $bundle_config = $types[$entityType]['bundles'][$bundle];
          $exposed = (bool) $bundle_config['exposed'];
          $view_mode = $bundle_config['view_mode'];

          if ($exposed) {
            $this->schemaConfig->exposeEntityBundle($entityType, $bundle, $view_mode);
          }
          else {
            $this->schemaConfig->unexposeEntityBundle($entityType, $bundle);
          }
        }
      }
    }

    $this->invalidator->invalidateTags(['graphql_schema', 'graphql_request']);
    parent::submitForm($form, $form_state);
  }

}
