<?php

namespace Drupal\gd_product\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\gd_commercetools_integration\ProductInterface;
use Drupal\gd_commercetools_integration\ProductProviderCommerceTools;
use Drupal\gd_commercetools_integration\ProductProviderInterface;
use Drupal\gd_product\ProductManager;
use Drupal\gd_regions\RegionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a (dynamic) pd block.
 *
 * @Block(
 *   id = "gd_product_product_details",
 *   admin_label = @Translation("(DYNAMIC) PD"),
 *   category = @Translation("Dynamic (Do not use)")
 * )
 */
class ProductDetailsBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * The database connection.
   *
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var ProductManager
   */
  private $productManager;

  /**
   * @var ConfigFactoryInterface
   */
  private $configFactory;

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
   * @param ProductProviderCommerceTools $product_provider
   *   The gd_commercetools_integration.product_provider_commercetools service.
   * @param RegionManager $gd_regions_region_manager
   *   The gd_regions.region_manager service.
   * @param Connection $connection
   *   The database connection.
   * @param LanguageManagerInterface $language_manager
   * @param ProductManager $product_manager
   * @param ConfigFactoryInterface $config_factory
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ProductProviderCommerceTools $product_provider,
    RegionManager $gd_regions_region_manager,
    Connection $connection,
    LanguageManagerInterface $language_manager,
    ProductManager $product_manager,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->productProvider = $product_provider;
    $this->regionManager = $gd_regions_region_manager;
    $this->connection = $connection;
    $this->languageManager = $language_manager;
    $this->productManager = $product_manager;
    $this->configFactory = $config_factory;
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
      $container->get('gd_product.product_manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // @todo: user product decorator instead of 2 separate things.
    $external_product = $this->getProduct();
    $internal_product = $this->productManager->getInternalProduct($external_product->getId());

    $details = $this->buildProductDetails($external_product, $internal_product, $langcode);
    $specs = $this->buildProductSpecs($external_product, $internal_product, $langcode);
    $related_products = $this->buildRelatedProducts($external_product, $internal_product, $langcode);

    $build = [
      'details' => $details,
      'specs' => $specs,
      'related_products' => $specs,
    ];

    return $build;
  }

  /**
   * @param ProductInterface $product
   * @param $internal_product
   * @param $langcode
   * @return array
   */
  private function buildProductSpecs($product, $internal_product, $langcode) {
    $variants = $product->getVariants();
    $attributes_to_show = $this->configFactory->get('gd_product.settings')->get('attributes_to_show_on_specs_block_' . $product->getTypeId());
    $attributes_definitions = $this->productProvider->getAttributeDefinitions();


    $variants_data = [];
    foreach ($variants as $variant) {
      $specs = [];
      foreach ($variant->getAttributes($langcode) as $attribute_id => $attribute) {
        if (!isset($attributes_to_show[$attribute_id])) {
          continue;
        }

        $specs[] = [
          'name' => $attributes_definitions[$attribute_id]->getName($langcode),
          'value' => $attribute->getValue($langcode),
        ];
      }

      $variants_data[$variant->getId()] = [
        'specs' => $specs,
        'id' => $variant->getId(),
        'title' => $variant->getTitle(),
      ];
    }

    $cta = NULL;
    if ($internal_product->field_product_specs_cta->uri) {
      $cta = [
        'url' => Url::fromUri($internal_product->field_product_specs_cta->uri),
        'title' => $internal_product->field_product_specs_cta->title,
      ];
    }

    $cta_additional = NULL;
    if ($internal_product->field_product_specs_cta_addition->getValue()) {
      $cta_additional = [];
      foreach ($internal_product->field_product_specs_cta_addition->getValue() as $value) {
        $cta_additional[] = [
          'url' => Url::fromUri($value['uri']),
          'title' => $value['title'],
        ];
      }
    }

    $specs = [
      '#theme' => 'gd_product_specs',
      '#title' => $product->getTitle($langcode),
      '#variants' => $variants_data,
      '#cta' => $cta,
      '#cta_additional' => $cta_additional,
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];

    return $specs;
  }

  /**
   * @param ProductInterface $product
   * @param $internal_product
   * @param $langcode
   * @return array
   */
  private function buildProductDetails($product, $internal_product, $langcode) {
    $variants = $product->getVariants();
    $variants_data = [];
    // @todo: make this right.

    $cta = NULL;

    if ($internal_product->field_product_details_cta->uri) {
      $cta = [
        'url' => Url::fromUri($internal_product->field_product_details_cta->uri),
        'title' => $internal_product->field_product_details_cta->title,
      ];
    }


    foreach ($variants as $variant) {
      $attributes = $variant->getAttributes($langcode);

      $description = '';
      $features = [];
      if (isset($attributes['nested-Marketing'])) {
        foreach ($attributes['nested-Marketing']->getValue($langcode) as $key => $marketing_value) {
          if ($key == 'short-product-description') {
            $description = $marketing_value;
          }
          elseif (strpos($key, 'product-feature') === 0) {
            if (!$marketing_value) {
              continue;
            }

            $elems = explode('-', $key);
            $id = end($elems);

            $features[$id] = $marketing_value;
          }
        }

        ksort($features);
      }

      $variants_data[] = [
        'images' => $variant->getImages(),
        'id' => $variant->getId(),
        'title' => $variant->getTitle(),
        'features' => $features,
        'description' => $description,
      ];
    }

    $details = [
      '#theme' => 'gd_product_details',
      '#title' => $product->getTitle($langcode),
      '#variants' => $variants_data,
      '#add_to_compare_url' => 'https://google.com',
      '#find_retailer_url' => 'https://google.com',
      '#cta' => $cta,
      '#cache' => [
        'max-age' => 0,
      ],
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];

    return $details;
  }

  private function buildRelatedProducts($external_product, $internal_product, $langcode) {

  }

  /**
   * @return ProductInterface
   */
  private function getProduct() {
    $product_key = $this->getConfiguration()['product_key'];

    $products = $this->productProvider->getProductsByIds([$product_key]);

    return $products[0];
  }
}
