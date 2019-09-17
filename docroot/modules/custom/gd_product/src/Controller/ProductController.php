<?php

namespace Drupal\gd_product\Controller;

use Drupal;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\gd_product\Ajax\ProductDetailsInit;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\gd_commercetools_integration\ProductProviderCommerceTools;
use Drupal\gd_product\Ajax\ProductListingInit;
use Drupal\gd_regions\RegionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\gd_product\Ajax\ProductListingLoadMore;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for GD Product routes.
 */
class ProductController extends ControllerBase {

  /**
   * The gd_commercetools_integration.product_provider_commercetools service.
   *
   * @var ProductProviderCommerceTools
   */
  protected $ctConnector;

  /**
   * The gd_regions.region_manager service.
   *
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * The language manager.
   *
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory.
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var AccountInterface
   */
  protected $currentUser;

  /**
   * The request object.
   *
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * The controller constructor.
   *
   * @param ProductProviderCommerceTools $gd_commercetools_integration_connector
   *   The gd_commercetools_integration.product_provider_commercetools service.
   * @param RegionManager $gd_regions_region_manager
   *   The gd_regions.region_manager service.
   * @param LanguageManagerInterface $language_manager
   *   The language manager.
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param AccountInterface $current_user
   *   The current user.
   * @param RequestStack $request_stack
   */
  public function __construct(
    ProductProviderCommerceTools $gd_commercetools_integration_connector,
    RegionManager $gd_regions_region_manager,
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user,
    RequestStack $request_stack
  ) {
    $this->ctConnector = $gd_commercetools_integration_connector;
    $this->regionManager = $gd_regions_region_manager;
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gd_commercetools_integration.product_provider_commercetools'),
      $container->get('gd_regions.region_manager'),
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * Builds the response.
   */
  public function productListingInit() {
    $block = $this->getProductListingBlock();
    if (!$block) {
      return new Response();
    }

    $render = $block->build();

    $response = new AjaxResponse();
    $response->addCommand(new ProductListingInit($render));

    return $response;
  }

  /**
   * Builds the response.
   */
  public function productListingFilter() {
    $block = $this->getProductListingBlock();
    if (!$block) {
      return new Response();
    }

    $render = $block->build();

    $response = new AjaxResponse();
    $response->addCommand(new ProductListingInit($render));

    return $response;
  }

  /**
   * Builds the response.
   */
  public function productListingLoadMore() {
    $block = $this->getProductListingBlock();
    if (!$block) {
      return new Response();
    }

    $result = $block->loadMore();

    $response = new AjaxResponse();
    $response->addCommand(new ProductListingLoadMore($result['content'], $result['load_more']));

    return $response;
  }

  /**
   * Builds the response.
   */
  public function productDetailsInit(EntityInterface $node) {
    // Getting actual block.
    $block_manager = Drupal::service('plugin.manager.block');
    $config = [
      'product_key' => $node->field_product_key->value,
    ];

    $plugin_block = $block_manager->createInstance('gd_product_product_details', $config);
    $render = $plugin_block->build();

    $response = new AjaxResponse();
    $response->addCommand(new ProductDetailsInit($render['details'], $render['specs']));

    return $response;
  }

  private function getProductListingBlock() {
    $filters = $this->getFilters();
    $token = $filters['token'];
    if (!$token) {
      return NULL;
    }

    $config = $this->configFactory->get('gd_product.pl_filters')->get($token);
    if (!isset($config['catalog_config'])) {
      return NULL;
    }

    $config['catalog_config']['language'] = $this->languageManager->getCurrentLanguage()->getId();
    $config['catalog_config']['user_input'] = $filters['filters'] ?? [];
    $config['catalog_config']['offset'] = $filters['offset'] ?? 0;

    $block_manager = Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('gd_product_product_listing', $config);

    return $plugin_block;
  }

  private function getFilters() {
    $url = $this->requestStack->getCurrentRequest()->getUri();
    $parsed_url = UrlHelper::parse($url);
    $query = $parsed_url['query'];
    if (isset($query['_wrapper_format'])) {
      unset($query['_wrapper_format']);
    }

    $filters = $query;

    return $filters;
  }

}
