<?php

namespace Drupal\gd_product;

use Commercetools\Core\Model\Category\Category;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\gd_commercetools_integration\ProductProviderCommerceTools;
use Drupal\Core\Database\Connection;
use Drupal\gd_commercetools_integration\ProductProviderInterface;
use Drupal\gd_regions\RegionManager;
use Drupal\taxonomy\Entity\Term;
use Exception;
use PDO;


/**
 * CategorySynchroniser service.
 *
 * This class is responsible for queuing and synchronising all the product categories within a system.
 */
class CategorySynchroniser {

  const CATEGORIES_PER_QUEUE_ITEM = 20;
  const CATEGORY_VOCABULARY_MACHINE_NAME = 'product_category';

  /**
   * The gd_commercetools_integration.product_provider_commercetools service.
   *
   * @var ProductProviderCommerceTools
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
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var RegionManager
   */
  protected $regionManager;

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
   * @param LanguageManagerInterface $language_manager
   * @param RegionManager $region_manager
   */
  public function __construct(
    ProductProviderInterface $product_provider,
    AccountInterface $current_user,
    QueueFactory $queue,
    ConfigFactoryInterface $config_factory,
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    RegionManager $region_manager
  ) {
    $this->productProvider = $product_provider;
    $this->currentUser = $current_user;
    $this->queue = $queue;
    $this->configs = $config_factory->get('gd_product.settings');
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->regionManager = $region_manager;
  }

  /**
   * Fetches categories from commercetools and queues them for re-sync.
   */
  public function categoriesFetchAndQueueForResync() {
    $categories = $this->productProvider->getProductCategories();
    $all_product_ids = [];

    $i = 0;
    $data['category_ids'] = [];

    // We'll process CATEGORIES_PER_QUEUE_ITEM categories per queue worker run.
    foreach ($categories as $category) {
      $all_product_ids[] = $category->getId();
      $data['category_ids'][] = $category->getId();

      if (++$i == self::CATEGORIES_PER_QUEUE_ITEM) {
        $queue = $this->queue->get('gd_product_category_synchronise_queue', FALSE);
        $queue->createItem($data);
        $i = 0;
        $data['category_ids'] = [];
      }
    }

    // Adding tail (if any).
    if ($i > 0) {
      $queue = $this->queue->get('gd_product_category_synchronise_queue', FALSE);
      $queue->createItem($data);
    }

    // Deleting categories that don't exist in commercetools anymore.
    $this->syncDeletedCategories($all_product_ids);
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
    $categories = $this->productProvider->getProductCategoriesByIds($data['category_ids']);

    $received_category_keys = [];
    $external_categories = [];

    foreach ($categories as $category) {
      $external_categories[$category->getId()] = $category;
      $received_category_keys[] = $category->getId();
    }

    $internal_categories = $this->getInternalCategories($received_category_keys);

    $to_update = [];
    foreach ($internal_categories as $ct_key => $internal_category) {
      if ($internal_category['version'] != $external_categories[$ct_key]->getVersion()) {

        // We need to update this element.
        $to_update[$internal_category['tid']] = $external_categories[$ct_key];
        unset($external_categories[$ct_key]);
      }
      elseif ($internal_category['version'] == $external_categories[$ct_key]->getVersion()) {
        unset($external_categories[$ct_key]);
      }
    }

    $this->doUpdate($to_update);

    // What's left in this array - doesn't exist on drupal.
    $this->doCreate($external_categories);
  }

  /**
   * Clears categories that have been deleted from commercetools.
   *
   * @param $remote_category_keys
   *   All categories IDs from commercetools.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityStorageException
   */
  public function syncDeletedCategories($remote_category_keys) {
    // @todo: implement SEO protection.
    $internal_categories = $this->getInternalCategories();

    // Looking for ones that are not exist in commercetools, but still exist in Drupal.
    foreach ($remote_category_keys as $remote_product_id) {
      unset($internal_categories[$remote_product_id]);
    }

    // All good, no need to perform any actions.
    if (empty($internal_categories)) {
      return;
    }

    $nids = array_map(function ($element) {return $element['tid'];}, $internal_categories);

    $storage_handler = $this->entityTypeManager->getStorage("taxonomy_term");
    $entities = $storage_handler->loadMultiple($nids);
    $storage_handler->delete($entities);
  }

  /**
   * Returns some of the internal products data.
   *
   * @param null $keys
   *   If specified, will limit result set to a specific products only.
   *
   * @return array
   */
  private function getInternalCategories($keys = NULL) {
    // Retrieving all the category IDs we have in the system.
    $query = $this->connection->select('taxonomy_term_field_data', 'term');
    $query->leftJoin('taxonomy_term__field_category_id', 'category_id', 'term.tid = category_id.entity_id');
    $query->leftJoin('taxonomy_term__field_version', 'version', 'term.tid = version.entity_id');

    $query->addField('category_id', 'field_category_id_value', 'category_id');
    $query->addField('term', 'tid', 'tid');
    $query->addField('version', 'field_version_value', 'version');

    $query->condition('term.vid', self::CATEGORY_VOCABULARY_MACHINE_NAME);

    if ($keys) {
      $query->condition('category_id.field_category_id_value', $keys, 'IN');
    }

    return $query->execute()->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }

  private function doUpdate($products) {

  }

  /**
   * @param Category[] $categories
   *
   * @throws EntityStorageException
   */
  public function doCreate($categories) {
    $languages = $this->languageManager->getLanguages();
    $default_language = $this->languageManager->getDefaultLanguage()->getId();

    unset($languages[$default_language]);

    foreach ($categories as $category) {
      $term_data = [
        'name' => $category->getName($default_language),
        'langcode' => $default_language,
        'uid' => 1,
        'field_version' => $category->getVersion(),
        'field_category_id' => $category->getId(),
        'vid' => self::CATEGORY_VOCABULARY_MACHINE_NAME,
      ];

      $category_term = Term::create($term_data);
      $category_term->save();

      // Creating translations.
      foreach ($languages as $lang_code => $language) {
        $translation = $category_term->addTranslation($language->getId());

        $translation->name = $category->getName($lang_code) ?: $term_data['name'];
        $translation->uid = 1;
        $translation->save();
      }
    }
  }

}
