<?php

namespace Drupal\gd_product\Plugin\Block;

use Commercetools\Core\Model\Product\ProductProjection;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\gd_commercetools_integration\FacetInterface;
use Drupal\gd_commercetools_integration\ProductInterface;
use Drupal\gd_commercetools_integration\ProductProviderCommerceTools;
use Drupal\gd_commercetools_integration\ProductProviderInterface;
use Drupal\gd_product\ProductManager;
use Drupal\gd_regions\RegionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a (dynamic) pl block.
 *
 * @Block(
 *   id = "gd_product_product_listing",
 *   admin_label = @Translation("(DYNAMIC) PL"),
 *   category = @Translation("Dynamic (Do not use)")
 * )
 */
class ProductListingBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * @var Connection
   */
  protected $connection;

  /**
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var ProductManager
   */
  protected $productManager;


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
   * @param ProductManager $product_manager
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
    ProductManager $product_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->productProvider = $product_provider;
    $this->regionManager = $gd_regions_region_manager;
    $this->connection = $connection;
    $this->languageManager = $language_manager;
    $this->productManager = $product_manager;
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
    $config = $this->getConfiguration()['catalog_config'];
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $catalog_data = $this->getCatalogData($config);

    $products = $this->buildProducts($catalog_data['products']);
    $filters = $this->buildFilters($config['available_filters'], $this->getConfiguration()['catalog_config']['user_input'], $catalog_data['facets'], $langcode);
    $pager = $this->buildPager($catalog_data['pager']);


    $content = [
      '#theme' => 'gd_product_listing',
      '#filters' => $filters,
      '#products' => $products,
      '#pager' => $pager,
    ];

    $build['content'] = [
      'content' => $content,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

  public function loadMore() {
    $config = $this->getConfiguration()['catalog_config'];

    $catalog_data = $this->getCatalogData($config);
    $products = $this->buildProducts($catalog_data['products']);
    $load_more = $this->buildPager($catalog_data['pager']);

    $content = [
      'content' => $products,
      'load_more' => $load_more,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $content;
  }

  private function getCatalogData($filters) {
    $region = $this->regionManager->getCurrentRegion();

    $offset = $filters['offset'] ?? 0;
    $limit = $filters['limit'] ?? 12;
    $filters['region_id'] = $region->field_channel_id->value;

    $result = $this->productProvider->getProducts($filters, $limit, $offset);
    return $result;
  }

  /**
   *
   *
   * @param ProductInterface[] $products
   * @return array
   */
  private function buildProducts($products) {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Retrieving internal products data.
    $ids = [];
    foreach ($products as $product) {
      $ids[] = $product->getId();
    }

    $internal_products = $this->productManager->getInternalProductsData($ids, 'key');

    $render_array_products = [];
    foreach ($products as $product) {
      if (!isset($internal_products[$product->getId()])) {
        continue;
      }

      $images = $product->getImages();
      $image_url = $images[0]['url'] ?? '';
      $data = array_keys($internal_products[$product->getId()]);
      $nid = reset($data);

      $options = ['absolute' => TRUE];
      $product_url = Url::fromRoute('entity.node.canonical', ['node' => $nid], $options)->toString();

      $render_array_products[] = [
        '#theme' => 'gd_product_product_preview',
        '#image_url' => $image_url,
        '#product_url' => $product_url,
        '#title' => $product->getTitle($langcode),
        '#description' => $product->getDescription($langcode),
      ];
    }

    return [
      'products' => $render_array_products,
    ];
  }

  /**
   * @param $filters
   * @param $customer_input
   * @param FacetInterface[] $facets
   * @param $langcode
   * @return array
   */
  private function buildFilters($filters, $customer_input, $facets, $langcode) {
    $processed_filters = [];

    // Filtering customer categories input to not filter by unavailable category.
    if (isset($customer_input['categories'])) {
      foreach ($customer_input['categories'] as  $value) {
        if (!isset($filters['categories'][$value])) {
          unset($customer_input['categories'][$value]);
        }
      }
    }

    // Building filters based on facets.
    foreach ($facets as $facet) {
      $options = [];
      $values = [];
      $total_count = 0;

      foreach ($facet->getItems($langcode) as $key => $data) {
        $options[$key] = $data['name'] . ' <span class="count">(' . $data['count'] . ')</span>';
        $total_count += $data['count'];
      }

      if (isset($customer_input[$facet->getId()])) {
        foreach ($customer_input[$facet->getId()] as $key) {
          $values[$key] = $key;
        }
      }

      if (!$options) {
        continue;
      }

      $processed_filters[$facet->getId()] = [
        'title' => $facet->getTitle($langcode) . ' <span class="count">(' . $total_count . ')</span>',
        'options' => $options,
        'values' => $values,
      ];
    }

    $filters = [];

    foreach ($processed_filters as $id => $processed_filter) {
      $filters['filter_' . $id] = [
        '#type' => 'selectboxes',
        '#title' => $processed_filter['title'],
      ];

      foreach ($processed_filter['options'] as $key => $title) {
        $filters['filter_' . $id]['#options'][$key] = [
          'title' => $title,
          'value' => $key,
          'checked' => $processed_filter['values'][$key] ?? FALSE,
          'attributes' => [
            'id' => 'product-filter-' . $key,
            'data-filter-id' => $id,
            'data-filter-value' => $key,
            'class' => [
              'product-listing-filter',
            ],
          ]
        ];
      }
    }

    return $filters;
  }

  private function buildPager($pager_data) {
    $load_more = [];
    if ($pager_data['offset'] + $pager_data['count'] < $pager_data['total']) {
      $load_more = [
        '#title' => $this->t('Load More'),
        '#type' => 'link',
        '#attributes' => [
          'data-offset' => $pager_data['offset'] + $pager_data['count'],
          'class' => 'product-listing-load-more',
        ],
        '#url' => Url::fromRoute('gd_product.product_listing_load_more'),
      ];
    }

    return $load_more;
  }

}
