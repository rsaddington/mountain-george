<?php

namespace Drupal\gd_crm;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\gd_regions\RegionManager;
use Traversable;

/**
 * PluginManagerCrmIntegration service.
 */
class PluginManagerCrmIntegration extends DefaultPluginManager {

  /**
   * @var RegionManager
   */
  protected $gdRegionsRegionManager;

  /**
   * @var LanguageManagerInterface
   */
  protected $languageManager;


  /**
   * @param Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param RegionManager $gd_regions_region_manager
   * @param LanguageManagerInterface $language_manager
   */
  public function __construct(
    Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    RegionManager $gd_regions_region_manager,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct(
      'Plugin/CrmConnection',
      $namespaces,
      $module_handler,
      'Drupal\gd_crm\CrmConnectionInterface',
      'Drupal\gd_crm\Annotation\CrmConnection'
    );

    $this->alterInfo('crm_connection_info');
    $this->setCacheBackend($cache_backend, 'crm_connection_info_plugins');
    $this->gdRegionsRegionManager = $gd_regions_region_manager;
    $this->languageManager = $language_manager;
  }


}
