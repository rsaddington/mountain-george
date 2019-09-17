<?php

namespace Drupal\gd_product\Plugin\Block;

use Drupal\Component\Utility\Random;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\gd_commercetools_integration\ProductProviderCommerceTools;
use Drupal\gd_commercetools_integration\ProductProviderInterface;
use Drupal\gd_product\CatalogManager;
use Drupal\gd_regions\RegionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a product listing block.
 *
 * @Block(
 *   id = "gd_product_product_listing_placeholder",
 *   admin_label = @Translation("Product Listing"),
 *   category = @Translation("Product")
 * )
 */
class ProductListingPlaceholderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The gd_commercetools_integration.product_provider_commercetools service.
   *
   * @var ProductProviderInterface
   */
  protected $productProvider;

  /**
   * The gd_regions.region_manager service.
   *
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * The database connection.
   *
   * @var Connection
   */
  protected $connection;

  /**
   * @var LanguageManagerInterface
   */
  private $languageManager;

  /**
   * @var \Drupal\Core\Config\Config
   */
  private $productListingConfig;

  /**
   * @var CatalogManager
   */
  private $catalogManager;

  /**
   * Constructs a new ProductListingBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param ProductProviderInterface $product_provider
   *   The gd_commercetools_integration.product_provider_commercetools service.
   * @param RegionManager $gd_regions_region_manager
   *   The gd_regions.region_manager service.
   * @param Connection $connection
   *   The database connection.
   * @param LanguageManagerInterface $language_manager
   * @param ConfigFactoryInterface $config_factory
   * @param CatalogManager $catalog_manager
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ProductProviderInterface $product_provider,
    RegionManager $gd_regions_region_manager,
    Connection $connection,
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config_factory,
    CatalogManager $catalog_manager
  ) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->productProvider = $product_provider;
    $this->regionManager = $gd_regions_region_manager;
    $this->connection = $connection;
    $this->languageManager = $language_manager;
    $this->productListingConfig = $config_factory->getEditable('gd_product.pl_filters');
    $this->catalogManager = $catalog_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('gd_commercetools_integration.product_provider_commercetools'),
      $container->get('gd_regions.region_manager'),
      $container->get('database'),
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('gd_product.catalog_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $product_types = $this->productProvider->getProductTypes();
    $product_categories = $this->productProvider->getProductCategories();
    $cur_langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Since we somehow need to send those settings over to real product listing block, without
    // exposing those to frontend, we creating unique key that we'll later use to retrieve those settings.
    $form['block_settings_identifier'] = [
      '#type' => 'hidden',
      '#value' => $this->configuration['block_settings_identifier'] ?? (new Random())->name(24),
    ];

    $form['catalog_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Catalog Settings'),
    ];

    $form['catalog_settings']['product_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Product Types'),
      '#description' => $this->t('Define which product types will be available on this catalog.'),
      '#options' => [],
      '#default_value' => $this->configuration['product_types'] ?? FALSE,
      '#ajax' => [
        'wrapper' => 'catalog-available-filters',
        'callback' => 'Drupal\gd_product\Plugin\Block\ProductListingPlaceholderBlock::updateAttributeFilters'
      ],
    ];

    foreach ($product_types as $product_type) {
      $form['catalog_settings']['product_types']['#options'][$product_type->getId()] = $product_type->getName($cur_langcode);
    }

    $form['catalog_settings']['product_categories'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available Categories'),
      '#description' => $this->t('Define which categories will be available on this catalog.'),
      '#options' => [],
      '#default_value' => $this->configuration['product_categories'] ?? [],
    ];

    foreach ($product_categories as $product_category) {
      $form['catalog_settings']['product_categories']['#options'][$product_category->getId()] = $product_category->getName($this->languageManager->getCurrentLanguage()->getId());
    }

    $form['catalog_settings']['show_categories_as_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show categories as filter.'),
      '#default_value' => $this->configuration['show_categories_as_filter'] ?? FALSE,
      '#description' => $this->t('If selected customer will be able to filter by selected categories.'),
    ];


    $form['catalog_settings']['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Available Attribute Filters'),
      '#attributes' => [
        'id' => 'catalog-available-filters',
      ],
      '#collapsible' => TRUE,
    ];

    // We're using ::getUserInput() instead of ::getValues() since https://www.drupal.org/project/drupal/issues/2798261
    $input = $form_state->getUserInput();


    $available_product_types = [];
    if (isset($input['settings']['catalog_settings']['product_types'])) {
      $available_product_types = $input['settings']['catalog_settings']['product_types'];
    }
    else if (isset($this->configuration['product_types'])) {
      $available_product_types = $this->configuration['product_types'];
    }

    // This is how we prevent "illegal choice" errors.
    $attributes_field_id = 'attribute_filters_' . random_int(1, 123456789);
    $form['catalog_settings']['filters'][$attributes_field_id] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Attributes to be used as Filters'),
      '#options' => [],
    ];

    // Properly combining attributes per various product types.
    if ($available_product_types) {
      $selected_product_types = [];
      foreach ($available_product_types as $id => $available_product_type_is_selected) {
        if ($available_product_type_is_selected && isset($product_types[$id])) {
          $selected_product_types[] = $product_types[$id];
        }
      }

      $merged_attributes = $this->productProvider->mergeProductTypeAttributeDefinitions($selected_product_types);
      foreach ($merged_attributes as $attribute_id => $attribute) {
        if ($attribute->isSearchable()) {
          $form['catalog_settings']['filters'][$attributes_field_id]['#options'][$attribute_id] = $attribute->getName($cur_langcode);
        }
      }
    }

    $default_value = [];
    foreach ($this->configuration['attribute_filters'] as $selected_attribute_id => $selected_attribute) {
      if (isset($merged_attributes[$selected_attribute_id])) {
        $default_value[] = $selected_attribute_id;
      }
    }

    $form['catalog_settings']['filters'][$attributes_field_id]['#default_value'] = $default_value;

    return $form;
  }

  public function updateAttributeFilters($form, FormStateInterface $form_state) {
    return $form['settings']['catalog_settings']['filters'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getUserInput();
    $values = $values['settings'];

    // We cannot rely on DI, since after ajax submit this function will be used without block
    // initialisation.
    $catalog_manager = \Drupal::service('gd_product.catalog_manager');

    $catalog_settings = [
      'product_types' => array_filter($values['catalog_settings']['product_types']),
      'product_categories' => array_filter($values['catalog_settings']['product_categories']),
      'show_categories_as_filter' => $values['catalog_settings']['show_categories_as_filter'],
      'attribute_filters' => array_filter(reset($values['catalog_settings']['filters'])),
      'block_settings_identifier' => $values['block_settings_identifier'],
    ];

    $this->configuration = array_merge($this->configuration, $catalog_settings);

    $catalog_config = $catalog_manager->assembleCatalogData($catalog_settings);

    $config = \Drupal::configFactory()->getEditable('gd_product.pl_filters');
    $config->set($values['block_settings_identifier'], ['catalog_config' => $catalog_config]);
    $config->save();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $region = $this->regionManager->getCurrentRegion();

    $content = [
      '#theme' => 'gd_product_listing_placeholder',
      '#region' => $region->field_url_prefix->value,
      '#token' => $this->configuration['block_settings_identifier'] ?? '',
    ];

    $build['content'] = [
      'content' => $content,
      '#attached' => [
        'library' => [
          'gd_product/gd_product.product',
        ]
      ],
      '#cache' => [
        'max-age' => 0,
//        'contexts' => [
//          'region',
//          'url.path',
//        ],
      ],
    ];

    return $build;
  }

}
