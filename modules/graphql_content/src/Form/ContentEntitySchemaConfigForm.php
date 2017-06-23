<?php

namespace Drupal\graphql_content\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeBundleInfoInterface $bundleInfo,
    CacheTagsInvalidatorInterface $invalidator
  ) {
    parent::__construct($configFactory);
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->invalidator = $invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('cache_tags.invalidator')
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
    $defaults = [];
    $config = $this->config('graphql_content.schema');
    if ($config) {
      $defaults = $config->get('types');
    }

    $form['types'] = [
      '#type' => 'table',
      '#header' => [
        '',
        '',
        $this->t('Expose fields from view mode'),
      ],
    ];

    /** @var EntityViewMode[] $modes */
    $modes = EntityViewMode::loadMultiple();

    foreach ($this->entityTypeManager->getDefinitions() as $type) {
      if ($type instanceof ContentEntityTypeInterface) {

        $form['types'][$type->id()]['exposed'] = [
          '#type' => 'checkbox',
          '#default_value' => isset($defaults[$type->id()]['exposed']) ? $defaults[$type->id()]['exposed'] : 0,
        ];

        $form['types'][$type->id()]['label'] = [
          '#type' => 'html_tag',
          '#tag' => 'strong',
          '#value' => $type->getLabel(),
          '#wrapper_attributes' => ['colspan' => 2],
        ];

        foreach ($this->bundleInfo->getBundleInfo($type->id()) as $bundle => $info) {
          $key = $type->id() . '__' . $bundle;

          $form['types'][$key]['exposed'] = [
            '#type' => 'checkbox',
            '#parents' => ['types', $type->id(), 'bundles', $bundle, 'exposed'],
            '#default_value' => isset($defaults[$type->id()][$bundle]['exposed']) ? $defaults[$type->id()][$bundle]['exposed'] : 0,
            '#states' => [
              'enabled' => [
                ':input[name="types[' . $type->id() . '][exposed]"]' => ['checked' => TRUE],
              ],
            ],
          ];

          $form['types'][$key]['label'] = [
            '#markup' => $info['label'],
          ];

          $options = [
            '__none__' => $this->t("Don't expose fields."),
            $type->id() . 'default' => $this->t('Default'),
          ];

          foreach ($modes as $mode) {
            /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
            if ($mode->getTargetType() == $type->id()) {
              $options[$mode->id()] = $mode->label();
            }
          }

          $form['types'][$key]['view_mode'] = [
            '#type' => 'select',
            '#parents' => [
              'types', $type->id(), 'bundles', $bundle, 'view_mode',
            ],
            '#default_value' => isset($defaults[$type->id()][$bundle]['view_mode']) ? $defaults[$type->id()][$bundle]['view_mode'] : 0,
            '#options' => $options,
            '#attributes' => [
              'width' => '100%',
            ],
            '#states' => [
              'enabled' => [
                ':input[name="types[' . $type->id() . '][exposed]"]' => ['checked' => TRUE],
                ':input[name="types[' . $type->id() . '][bundles][' . $bundle . '][exposed]"]' => ['checked' => TRUE],
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
    $this->config('graphql_content.schema')
      ->set('types', $form_state->getValue('types'))
      ->save();
    $this->invalidator->invalidateTags(['graphql_schema', 'graphql_request']);
    parent::submitForm($form, $form_state);
  }

}
