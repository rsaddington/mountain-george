<?php

namespace Drupal\gd_product;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\gd_commercetools_integration\AttributeDefinitionInterface;
use Drupal\gd_commercetools_integration\CategoryInterface;
use Drupal\gd_commercetools_integration\ProductProviderInterface;
use Drupal\gd_commercetools_integration\ProductTypeInterface;

/**
 * CatalogManager service.
 */
class CatalogManager {

  /**
   * The language manager.
   *
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The gd_commercetools_integration.product_provider_commercetools service.
   *
   * @var ProductProviderInterface
   */
  protected $productProvider;

  /**
   * Constructs a CatalogManager object.
   *
   * @param LanguageManagerInterface $language_manager
   *   The language manager.
   * @param ProductProviderInterface $product_provider
   *   The gd_commercetools_integration.product_provider_commercetools service.
   */
  public function __construct(LanguageManagerInterface $language_manager, ProductProviderInterface $product_provider) {
    $this->languageManager = $language_manager;
    $this->productProvider = $product_provider;
  }

  /**
   * Method description.
   */
  public function buildCatalogConfig() {
    // @DCG place your code here.
  }

  /**
   * @param $catalog_settings
   * @return array
   */
  public function assembleCatalogData($catalog_settings) {
    $full_catalog_config = [
      'limiters' => [
        'product_type' => $catalog_settings['product_types'],
      ],
    ];

    if (!$catalog_settings['show_categories_as_filter']) {
      $full_catalog_config['limiters']['category'] = $catalog_settings['product_categories'];
    }

    $full_catalog_config['available_filters']['attributes'] = $catalog_settings['attribute_filters'];

    if ($catalog_settings['show_categories_as_filter']) {
      $full_catalog_config['available_filters']['categories']= $catalog_settings['product_categories'];
    }

    return $full_catalog_config;
  }
}

