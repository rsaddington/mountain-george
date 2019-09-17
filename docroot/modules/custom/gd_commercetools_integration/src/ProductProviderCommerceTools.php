<?php

namespace Drupal\gd_commercetools_integration;

use Commercetools\Core\Error\ApiException;
use Commercetools\Core\Error\InvalidTokenException;
use Commercetools\Core\Model\Category\Category;
use Commercetools\Core\Model\Product\FacetResult;
use Commercetools\Core\Model\Product\FacetResultCollection;
use Commercetools\Core\Model\Product\FacetTerm;
use Commercetools\Core\Model\Product\ProductProjection;
use Commercetools\Core\Model\Product\Search\Facet;
use Commercetools\Core\Model\Product\Search\Filter;
use Commercetools\Core\Model\Product\Search\FilterRange;
use Commercetools\Core\Model\Product\Search\FilterRangeCollection;
use Commercetools\Core\Model\ProductType\ProductType;
use Commercetools\Core\Response\PagedQueryResponse;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Session\AccountInterface;
use Commercetools\Core\Builder\Request\RequestBuilder;
use Commercetools\Core\Client;
use Commercetools\Core\Config;
use Commercetools\Core\Model\Common\Context;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Connector service.
 *
 * @todo: make sure responces are correct.
 */
class ProductProviderCommerceTools implements ProductProviderInterface {

  use StringTranslationTrait;

  const MAX_PRODUCTS = 500;

  /**
   * The current user.
   *
   * @var AccountInterface
   */
  protected $currentUser;

  /**
   * Commercetools config set.
   *
   * @var ImmutableConfig
   */
  protected $commercetoolsConfig;

  /**
   * @var Client
   */
  protected $client = NULL;

  /**
   * @var CacheBackendInterface
   */
  private $cache;

  /**
   * Constructs a Connector object.
   *
   * @param AccountInterface $current_user
   *   The current user.
   * @param ConfigFactoryInterface $config_factory
   * @param CacheBackendInterface $cache_backend
   */
  public function __construct(
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    CacheBackendInterface $cache_backend
  ) {
    $this->commercetoolsConfig = $config_factory->get('gd_commercetools_integration.settings');
    $this->currentUser = $current_user;
    $this->cache = $cache_backend;
  }

  /**
   * Retrieves commercetools connection client.
   *
   * @return Client
   */
  protected function getClient() {
    if (!$this->client) {
      $this->connect();
    }

    return $this->client;
  }

  /**
   * Establishes connection to commercetools.
   */
  public function connect() {
    $config = [
      'client_id' => $this->commercetoolsConfig->get('client_id'),
      'client_secret' => $this->commercetoolsConfig->get('client_secret'),
      'project' => $this->commercetoolsConfig->get('project'),
    ];

    $context = Context::of()->setGraceful(true);
    $config = Config::fromArray($config)->setContext($context);

    $this->client = Client::ofConfig($config);
  }

  /**
   * Returns all the products from commercetools.
   *
   * @return ProductProjection[]
   * @throws ApiException
   * @throws InvalidTokenException
   */
  public function getProductsAll() {
    $client = $this->getClient();

    $query = RequestBuilder::of()->productProjections()->query()->limit(self::MAX_PRODUCTS);


    /* @var $res PagedQueryResponse */
    $res = $client->execute($query);

    $all = [];
    $all[] = $res->toObject();

    $total = $res->getTotal();

    if ($total > self::MAX_PRODUCTS) {
      for ($i = self::MAX_PRODUCTS, $left = $total - self::MAX_PRODUCTS; $i <= $res->getTotal() + self::MAX_PRODUCTS; $i += self::MAX_PRODUCTS, $left -= self::MAX_PRODUCTS) {
        $query = RequestBuilder::of()->productProjections()->query(TRUE)->limit(self::MAX_PRODUCTS)->offset($i);
        $all[] = $client->execute($query)->toObject();
      }
    }

    $result = [];
    foreach ($all as $products) {
      foreach ($products as $product) {
        $result[] = new ProductCommerceTools($product, $this->getLanguageMapping());
      }
    }

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function getProductsByIds($ids) {
    if (empty($ids)) {
      return NULL;
    }

    $client = $this->getClient();

    $query = RequestBuilder::of()->productProjections()->query()->where('key in("' . implode('", "',  $ids) . '")');
    $products = $client->execute($query)->toObject();

    $result = [];
    foreach ($products as $product) {
      $result[] = new ProductCommerceTools($product, $this->getLanguageMapping());
    }

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function getProductCategoriesByIds($ids) {
    if (empty($ids)) {
      return NULL;
    }

    $client = $this->getClient();
    $query = RequestBuilder::of()->categories()->query()->limit(500)->where('id in("' . implode('", "',  $ids) . '")');
    $categories = $client->execute($query)->toObject();

    $result = [];
    foreach ($categories as $category) {
      $result[] = new CategoryCommerceTools($category, $this->getLanguageMapping());
    }

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function getLanguageMapping() {
    return $this->commercetoolsConfig->get('language_mapping');
  }

  /**
   * Created for testing purposes.
   *
   * @todo: make sure to delete on release.
   */
  public function sandbox() {
    $client = $this->getClient();
    $facets = Facet::ofName('variants.attributes.model-number.en-US')->countingProducts(TRUE);
    $query = RequestBuilder::of()->productProjections()->search(TRUE)->addFacet($facets);

    $res = $query->executeWithClient($client);
    $res->getBody();
//    dump((string)$res->getRequest()); die;
    dump($res->getFacets()); die;
  }

  /**
   * {@inheritDoc}
   */
  public function getProducts($filters, $limit = 20, $offset = 0) {
    $attribute_definitions = $this->getAttributeDefinitions();
    $categories = $this->getProductCategories();

    $client = $this->getClient();
    $converted_products = [];
    $language_mapping = $this->getLanguageMapping();


    $query = RequestBuilder::of()->productProjections()->search()
      ->limit($limit)
      ->offset($offset);

    if (isset($filters['region_id'])) {
      if (!preg_match('/^[a-z\-0-9]*$/', $filters['region_id'])) {
        return NULL;
      }

      $filterRangeCollection = FilterRangeCollection::of()
        ->add(FilterRange::ofFromAndTo(0, NULL));

      $region_filter = Filter::ofName('variants.availability.channels.' . $filters['region_id'] . '.availableQuantity');
      $region_filter->setValue($filterRangeCollection);

      $query->addFilter($region_filter);
      $query->addFilterFacets($region_filter);
    }

    // Limiting by product type.
    if (isset($filters['limiters']['product_type'])) {
      $product_type_filter = Filter::ofName('productType.id');
      $product_type_filter->setValue($filters['limiters']['product_type']);

      $query->addFilter($product_type_filter);
      $query->addFilterFacets($product_type_filter);
    }

    // Limiting by category.
    $categories_to_filter = [];
    if (isset($filters['limiters']['category'])) {
      $categories_to_filter = $filters['limiters']['category'];
    }

    if (isset($filters['user_input']['categories'])) {
      if ($categories_to_filter) {
        $categories_to_filter = array_intersect($categories_to_filter, $filters['user_input']['categories']);
      }
      else {
        $categories_to_filter = $filters['user_input']['categories'];
      }
    }

    if ($categories_to_filter) {
      $categories_filter = Filter::ofName('categories.id');
      $categories_filter->setValue($categories_to_filter);
      $query->addFilter($categories_filter);
      $query->addFilterFacets($categories_filter);
    }

    // Adding user input as filters.
    if (isset($filters['user_input'])) {
      foreach ($filters['user_input'] as $attribute_id => $filter_values) {
        if (!isset($filters['available_filters']['attributes'][$attribute_id])) {
          continue;
        }

        switch ($attribute_definitions[$attribute_id]->getType()) {
          case 'ltext':
            if (!isset($filters['language'])) {
              continue 2;
            }

            $user_input_filter = Filter::ofName('variants.attributes.' . $attribute_id . '.' . $language_mapping[$filters['language']]);
            $user_input_filter->setValue($filter_values);
            break;

          case 'lenum':
          case 'enum':
            $user_input_filter = Filter::ofName('variants.attributes.' . $attribute_id . '.key');
            $user_input_filter->setValue($filter_values);
            break;

          case 'text':
            $user_input_filter = Filter::ofName('variants.attributes.' . $attribute_id);
            $user_input_filter->setValue($filter_values);
            break;


          default:
            continue 2;
        }

        $query->addFilter($user_input_filter);
        $query->addFilterFacets($user_input_filter);
      }
    }

    // Adding category filter.
    if (isset($filters['available_filters']['categories'])) {
      $categories_facet = Facet::ofName('categories.id');

      $categories_facet->setAlias('categories');
      $categories_facet->countingProducts(TRUE);
      $query->addFacet($categories_facet);
    }

    // Building attribute filters.
    if (isset($filters['available_filters']['attributes'])) {
      foreach ($filters['available_filters']['attributes'] as $attribute_id => $attribute_enabled) {
        switch ($attribute_definitions[$attribute_id]->getType()) {
          case 'ltext':
            if (!isset($filters['language'])) {
              continue 2;
            }

            $facet = Facet::ofName('variants.attributes.' . $attribute_id . '.' . $language_mapping[$filters['language']]);
            break;

          case 'lenum':
          case 'enum':
            $facet = Facet::ofName('variants.attributes.' . $attribute_id . '.key');
            break;

          case 'text':
            $facet = Facet::ofName('variants.attributes.' . $attribute_id);
            break;


          default:
            continue 2;
        }

        $facet->setAlias($attribute_id);
        $facet->countingProducts(TRUE);
        $query->addFacet($facet);
      }
    }

    $res = $query->executeWithClient($client);
    if (!$res instanceof PagedQueryResponse) {
      return [];
    }

    $facets = $res->getFacets();
    $processed_facets = $this->processFacets($facets, $attribute_definitions, $categories);
    $products = $res->toObject();

    foreach ($products as $product) {
      $converted_products[] = new ProductCommerceTools($product, $this->getLanguageMapping());
    }

    return [
      'pager' => [
        'count' => $res->getCount(),
        'offset' => $res->getOffset(),
        'total' => $res->getTotal(),
      ],
      'products' => $converted_products,
      'facets' => $processed_facets,
    ];
  }

  private function processFacets(FacetResultCollection $facets, $attribute_definitions, $categories) {
    $processed_facets = [];
    foreach ($facets as $facet_id => $facet) {

      if ($facet_id == 'categories') {
        $processed_facet = new FacetCategoryCommerceTools($facet_id, $facet, $categories);
      }
      else {
        $processed_facet = new FacetAttributeCommerceTools($facet_id, $facet, $attribute_definitions[$facet_id]);
      }

      $processed_facets[$processed_facet->getId()] = $processed_facet;
    }

    return $processed_facets;
  }

  /**
   * {@inheritDoc}
   */
  public function getProductTypes() {
    $converted_product_types = [];

    $client = $this->getClient();

    $query = RequestBuilder::of()->productTypes()->query()->limit(500);
    $product_types = $query->executeWithClient($client)->toObject();

    foreach ($product_types as $product_type) {
      $pt = new ProductTypeCommerceTools($product_type, $this->getLanguageMapping());
      $converted_product_types[$pt->getId()] = $pt;
    }

    return $converted_product_types;
  }

  /**
   * {@inheritDoc}
   */
  public function getProductCategories() {
    if ($cache = $this->cache->get('gd_commercetools_integration.ct.categories')) {
      $converted_product_categories = $cache->data;
    }
    else {
      $converted_product_categories = [];

      $client = $this->getClient();

      $query = RequestBuilder::of()->categories()->query()->limit(500);
      $product_categories = $query->executeWithClient($client)->toObject();

      foreach ($product_categories as $product_category) {
        $converted_product_category = new CategoryCommerceTools($product_category, $this->getLanguageMapping());
        $converted_product_categories[$converted_product_category->getId()] = $converted_product_category;
      }

      $this->cache->set('gd_commercetools_integration.ct.categories', $converted_product_categories);
    }

    return $converted_product_categories;
  }

  /**
   * {@inheritDoc}
   */
  public function getAttributeDefinitions() {
    if ($cache = $this->cache->get('gd_commercetools_integration.ct.attributes_definition')) {
      return $cache->data;
    }
    else {
      $product_types = $this->getProductTypes();
      $merged_attributes_definitions = $this->mergeProductTypeAttributeDefinitions($product_types);

      $this->cache->set('gd_commercetools_integration.ct.attributes_definition', $merged_attributes_definitions);
    }

    return $merged_attributes_definitions;
  }

  /**
   * {@inheritDoc}
   */
  public function mergeProductTypeAttributeDefinitions($product_types) {
    $invalid_attributes = [];
    $merged_attributes = [];

    foreach ($product_types as $id => $product_type) {
      foreach ($product_type->getAttributeDefinitions() as $attribute) {
        if (isset($invalid_attributes[$attribute->getId()])) {
          continue;
        }

        // Mismatch on product attribute between selected types.
        if (isset($merged_attributes[$attribute->getId()]) && $merged_attributes[$attribute->getId()]->getType() != $attribute->getType()) {
          $invalid_attributes[$attribute->getId()] = TRUE;
          unset($merged_attributes[$attribute->getId()]);
          continue;
        }

        $merged_attributes[$attribute->getId()] = $attribute;
      }
    }

    // Unsetting unwanted attribute types.
    foreach ($merged_attributes as $attribute_id =>  $merged_attribute) {
      if (!in_array($merged_attribute->getType(), ['enum', 'lenum', 'text', 'ltext'])) {
        unset($merged_attributes[$attribute_id]);
      }
    }

    return $merged_attributes;
  }
}
