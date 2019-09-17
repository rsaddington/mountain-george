<?php

namespace Drupal\gd_product;

use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\example\ExampleInterface;
use Drupal\gd_commercetools_integration\ProductProviderInterface;
use Drupal\gd_regions\RegionManager;
use Drupal\node\Entity\Node;

/**
 * ProductManager service.
 */
class ProductManager {
  /**
   * @var ProductProviderInterface
   */
  private $productProvider;

  /**
   * @var Connection
   */
  private $connection;

  /**
   * @var LanguageManagerInterface
   */
  private $languageManager;

  /**
   * @var RegionManager
   */
  private $regionManager;


  /**
   * Constructs a ProductManager object.
   *
   * @param ProductProviderInterface $product_provider
   *   The gd_commercetools_integration.product_provider_commercetools service.
   * @param Connection $connection
   *   The database connection.
   * @param LanguageManagerInterface $language_manager
   *   The language manager.
   * @param RegionManager $region_manager
   */
  public function __construct(
    ProductProviderInterface $product_provider,
    Connection $connection,
    LanguageManagerInterface $language_manager,
    RegionManager $region_manager
  ) {
    $this->productProvider = $product_provider;
    $this->connection = $connection;
    $this->languageManager = $language_manager;
    $this->regionManager = $region_manager;
  }

  public function getInternalProduct($key, $region = null, $langcode = null) {
    if ($region == null) {
      $region = $this->regionManager->getCurrentRegion();
    }

    if ($langcode == null) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    $id = $region->field_channel_id->value;

    $data = $this->getInternalProductsData([$key])[$id];

    $nid = array_shift($data)['nid'];

    $product = Node::load($nid);
    $product = $product->hasTranslation($langcode) ? $product->getTranslation($langcode) : NULL;

    return $product;
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
  public function getInternalProductsData($keys = NULL, $grouping = 'channel') {
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

}
