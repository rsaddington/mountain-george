<?php

namespace Drupal\gd_regions\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\gd_regions\RegionManager;

/**
 * Defines the RegionsCacheContext service, for "per region" caching.
 */
class RegionsCacheContext implements CalculatedCacheContextInterface {

  /**
   * The Region manager.
   *
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * Constructs a new LanguagesCacheContext service.
   *
   * @param RegionManager $region_manager
   *   The language manager.
   */
  public function __construct(RegionManager $region_manager) {
    $this->regionManager = $region_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Region');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($type = NULL) {
    $region = $this->regionManager->getCurrentRegion();

    return $region->field_url_prefix->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($type = NULL) {
    return new CacheableMetadata();
  }

}
