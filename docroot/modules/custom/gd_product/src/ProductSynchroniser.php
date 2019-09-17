<?php

namespace Drupal\gd_product;

use Commercetools\Core\Model\Product\ProductProjection;
use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\gd_commercetools_integration\ProductProviderCommerceTools;
use Drupal\Core\Database\Connection;
use Drupal\node\Entity\Node;
use Drupal\gd_regions\RegionManager;
use Drupal\taxonomy\Entity\Term;
use Exception;
use Drupal\gd_commercetools_integration\ProductProviderInterface;
use Drupal\gd_commercetools_integration\ProductInterface;


/**
 * ProductSynchroniser service.
 *
 * This class is responsible for queuing and synchronising all the products within a system.
 */
class ProductSynchroniser {
  const PRODUCTS_PER_QUEUE_ITEM = 20;

  /**
   * The gd_commercetools_integration.product_provider_commercetools service.
   *
   * @var ProductProviderInterface
   */
  protected $productProvider;

  /**
   * The current user.
   *
   * @var AccountInterface
   */
  protected $currentUser;

  /**
   * Some utility configs.
   *
   * @var ImmutableConfig
   */
  protected $configs;

  /**
   * The database connection.
   *
   * @var Connection
   */
  protected $connection;

  /**
   * @var QueueFactory
   */
  protected $queue;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * @var ProductManager
   */
  protected $productManager;

  /**
   * Constructs a ProductSynchroniser object.
   *
   * @param ProductProviderInterface $product_provider
   *   The gd_commercetools_integration.product_provider_commercetools service.
   * @param AccountInterface $current_user
   *   The current user.
   * @param QueueFactory $queue
   * @param ConfigFactoryInterface $config_factory
   * @param Connection $connection
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param RegionManager $region_manager
   * @param ProductManager $product_manager
   */
  public function __construct(
    ProductProviderInterface $product_provider,
    AccountInterface $current_user,
    QueueFactory $queue,
    ConfigFactoryInterface $config_factory,
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    RegionManager $region_manager,
    ProductManager $product_manager
  ) {
    $this->productProvider = $product_provider;
    $this->currentUser = $current_user;
    $this->queue = $queue;
    $this->configs = $config_factory->get('gd_product.settings');
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->regionManager = $region_manager;
    $this->productManager = $product_manager;
  }

  /**
   * Fetches products from commercetools and queues them for re-sync.
   */
  public function productsFetchAndQueueForResync() {
    $products = $this->productProvider->getProductsAll();

    $all_product_ids = [];

    $i = 0;
    $data['product_keys'] = [];

    // We'll process PRODUCTS_PER_QUEUE_ITEM products per queue worker run.
    foreach ($products as $product) {
      $all_product_ids[] = $product->getId();
      $data['product_ids'][] = $product->getId();

      if (++$i == self::PRODUCTS_PER_QUEUE_ITEM) {
        $queue = $this->queue->get('gd_product_product_synchronise_queue', FALSE);
        $queue->createItem($data);
        $i = 0;
        $data['product_ids'] = [];
      }
    }

    // Adding tail (if any).
    if ($i > 0) {
      $queue = $this->queue->get('gd_product_product_synchronise_queue', FALSE);
      $queue->createItem($data);
    }


    // Deleting products that don't exist in commercetools anymore.
    $this->syncDeletedProducts($all_product_ids);
  }

  /**
   * Performs product synchronisation between product stored on Drupal and
   * commercetools.
   *
   * @param $data
   *
   * @throws Exception
   */
  public function doSync($data) {
    $products = $this->productProvider->getProductsByIds($data['product_ids']);

    // Organising the products in hierarchy that is suits us the best.
    $regioned_external_products = [];
    $external_product_keys = [];
    foreach ($products as $product) {
      // We don't really care of master/other variants in this case, so just merging them together.
      $external_product_keys[] = $product->getId();
      $availability = $product->getAvailability();

      foreach ($availability as $channel_id => $variant) {
        $regioned_external_products[$channel_id][$product->getId()] = $product;
      }
    }

    // Receiving mirrored internal products data if any.
    $regioned_internal_products = $this->getInternalProducts($external_product_keys);

    $to_update = [];
    foreach ($regioned_internal_products as $region_id => $internal_products) {
      foreach ($internal_products as $ct_key => $internal_product) {
        if (!isset($regioned_external_products[$region_id][$ct_key])) {
          continue;
        }

        // If product has been updated - let's queue it for refresh.
        if ($internal_product['version'] != $regioned_external_products[$region_id][$ct_key]->getVersion()) {
          $to_update[$region_id][$internal_product['nid']] = $regioned_external_products[$region_id][$ct_key];
        }

        // Clearing original datasets.
        unset($regioned_external_products[$region_id][$ct_key], $regioned_internal_products[$region_id][$ct_key]);
      }
    }

    // Now since all sorted - performing actions for 3 categories: creating, updating, deleting.
    $this->doCreate($regioned_external_products);
//    $this->doUpdate($to_update);
    $this->doDelete($regioned_internal_products);
  }

  /**
   * Clears products that have been deleted from commercetools.
   *
   * @param $remote_product_ids
   *   All product IDs from commercetools.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityStorageException
   */
  public function syncDeletedProducts($remote_product_ids) {
    $internal_products = $this->getInternalProducts(NULL, 'key');

    // Looking for ones that are not exist in commercetools, but still exist in Drupal.
    foreach ($remote_product_ids as $remote_product_id) {
      unset($internal_products[$remote_product_id]);
    }

    // All good, no need to perform any actions.
    if (empty($internal_products)) {
      return;
    }

    $nids = [];
    foreach ($internal_products as $internal_product) {
      $nids = array_merge($nids, array_keys($internal_product));
    }

    $storage_handler = $this->entityTypeManager->getStorage("node");
    $entities = $storage_handler->loadMultiple($nids);
    $storage_handler->delete($entities);
  }

  /**
   * Returns some of the internal products data.
   *
   * @param null $keys
   *   If specified, will limit result set to a specific products only.
   *
   * @param string $grouping
   *   Defines on how products will be grouped - by channel or key.
   *
   * @return array
   */
  private function getInternalProducts($keys = NULL, $grouping = 'channel') {
    // Retrieving all the product IDs we have in the system.
    // We're using simple query since amount of data could be incredibly big.
    $query = $this->connection->select('node_field_data', 'node');
    $query->leftJoin('node__field_product_key', 'product_key', 'node.nid = product_key.entity_id');
    $query->leftJoin('node__field_version', 'version', 'node.nid = version.entity_id');
    $query->leftJoin('group_content_field_data', 'group_relation', 'node.nid = group_relation.entity_id AND group_relation.type = :type', [':type' => PRODUCT_GROUP_RELATION_TYPE]);
    $query->leftJoin('group__field_channel_id', 'channel', 'group_relation.gid = channel.entity_id');

    $query->addField('product_key', 'field_product_key_value', 'product_key');
    $query->addField('node', 'nid', 'nid');
    $query->addField('version', 'field_version_value', 'version');
    $query->addField('channel', 'field_channel_id_value', 'channel');

    $query->condition('node.type', PRODUCT_CONTENT_TYPE_MACHINE_NAME);
    $query->condition('node.status', Node::PUBLISHED);
    $query->distinct();

    if ($keys) {
      $query->condition('product_key.field_product_key_value', $keys, 'IN');
    }

    $results = $query->execute();

    $resultset = [];

    if ($grouping == 'channel') {
      while ($row = $results->fetch()) {
        $resultset[$row->channel][$row->product_key] = [
          'nid' => $row->nid,
          'version' => $row->version,
        ];
      }
    }
    else if ($grouping == 'key') {
      while ($row = $results->fetch()) {
        $resultset[$row->product_key][$row->nid] = [
          'version' => $row->version,
          'channel' => $row->channel,
        ];
      }
    }

    return $resultset;
  }


  /**
   * Crates new products based on commercetools products.
   *
   * @param array $regioned_products
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityStorageException
   */
  public function doCreate($regioned_products) {
    $language_mapping = $this->productProvider->getLanguageMapping();

    $regions = $this->regionManager->getRegions();
    $internal_categories = $this->getCategories();

    // Creating products within regions.
    foreach ($regions as $region) {
      $region_id = $region->field_channel_id->value;

      // Getting default language for product and languages that this product should
      // be translated to.
      $default_lang = $region->field_default_language->value;

      $translation_langs = array_flip(array_map(function($value) {return $value['value'];}, $region->field_available_languages->getValue()));
      unset($translation_langs[$default_lang]);

      // Creating product and translations.
      if (isset($regioned_products[$region_id])) {
        foreach ($regioned_products[$region_id] as $product) {
          /* @var $product ProductInterface */

          $product_categories = [];
          foreach ($product->getCategoryIds() as $category_id) {
            if (isset($internal_categories[$category_id])) {
              $product_categories[] = $internal_categories[$category_id]->id();
            }
          }

          $metadata = $product->getMetadata($default_lang);

          // Creating actual product.
          $product_data = [
            'title' => $product->getTitle($default_lang) ?: 'NO TITLE, PLEASE FIX.',
            'type' => PRODUCT_CONTENT_TYPE_MACHINE_NAME,
            'uid' => 1,
            'langcode' => $default_lang,
            'field_version' => $product->getVersion(),
            'field_product_key' => $product->getId(),
            'field_product_category' => $product_categories,
            'field_meta_tags' => serialize($metadata),
          ];

          // Creating translations.
          $product_node = Node::create($product_data);
          $product_node->save();

          // Adding translations.
          foreach ($translation_langs as $lang_code => $nothing) {
            $translation = $product_node->addTranslation($lang_code);

            $translation->title = $product->getTitle($lang_code) ?: $product_data['title'];
            $translation->uid = 1;
            $translation->set('field_meta_tags', serialize($product->getMetadata($lang_code)));
            $translation->save();
          }

          // Adding product to the region.
          $region->addContent($product_node, 'group_node:product');
        }
      }
    }
  }

  private function doUpdate($regioned_products  ) {
    $language_mapping = $this->productProvider->getLanguageMapping();

    $regions = $this->regionManager->getRegions();
//    $internal_categories = $this->getCategories();

    // Creating products within regions.
    foreach ($regions as $region) {
      $region_id = $region->field_channel_id->value;

      // Getting default language for product and languages that this product should
      // be translated to.
      $translation_langs = array_flip(array_map(function($value) {return $value['value'];}, $region->field_available_languages->getValue()));

      // Creating product and translations.
      if (isset($regioned_products[$region_id])) {
        foreach ($regioned_products[$region_id] as $product) {
          foreach ($translation_langs as $lang) {
            /* @var $product ProductInterface */
            $internal_product = $this->productManager->getInternalProduct($product->getId(), $lang);

            if (!$internal_product) {

            }
          }


//          $product_categories = [];
//          foreach ($product->getCategoryIds() as $category_id) {
//            if (isset($internal_categories[$category_id])) {
//              $product_categories[] = $internal_categories[$category_id]->id();
//            }
//          }

//          $metadata = $product->getMetadata($default_lang);

          // Creating actual product.
//          $product_data = [
//            'title' => $product->getTitle($default_lang) ?: 'NO TITLE, PLEASE FIX.',
//            'type' => PRODUCT_CONTENT_TYPE_MACHINE_NAME,
//            'uid' => 1,
//            'langcode' => $default_lang,
//            'field_version' => $product->getVersion(),
//            'field_product_key' => $product->getId(),
////            'field_product_category' => $product_categories,
//            'field_meta_tags' => serialize($metadata),
//          ];
//
//          // Creating translations.
//          $product_node = Node::create($product_data);
//          $product_node->save();
//
//          // Adding translations.
//          foreach ($translation_langs as $lang_code => $nothing) {
//            $translation = $product_node->addTranslation($lang_code);
//
//            $translation->title = $product->getTitle($default_lang) ?: $product_data['title'];
//            $translation->uid = 1;
//            $translation->set('field_meta_tags', serialize($product->getMetadata($lang_code)));
//            $translation->save();
//          }

          // Adding product to the region.
//          $region->addContent($product_node, 'group_node:product');
        }
      }
    }

  }

  private function doDelete($products) {

  }

  /**
   * Returns all available product categories.
   *
   * @return Term[]
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  private function getCategories() {
    $tids = Drupal::entityQuery('taxonomy_term')
      ->condition('vid',CategorySynchroniser::CATEGORY_VOCABULARY_MACHINE_NAME)
      ->accessCheck(FALSE)
      ->execute();

    // Amount of groups will be reasonably small, so we can just load them all at once.
    $storage_handler = $this->entityTypeManager->getStorage("taxonomy_term");
    $categories = $storage_handler->loadMultiple($tids);

    // Grouping categories by ID.
    $grouped_categories = [];
    foreach ($categories as $category) {
      $grouped_categories[$category->field_category_id->value] = $category;
    }

    return $grouped_categories;
  }
}
