<?php

namespace Drupal\gd_regions;

use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\gd_commercetools_integration\ProductProviderCommerceTools;
use Drupal\group\Entity\Group;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\smart_ip\SmartIp;

/**
 * RegionManager service.
 */
class RegionManager {
  const REGION_GROUP_TYPE = 'region';

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Commercetools config set.
   *
   * @var ImmutableConfig
   */
  protected $config;

  /**
   * The request object.
   *
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * The request object.
   *
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a RegionManager object.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param RequestStack $request_stack
   * @param ConfigFactoryInterface $config_factory
   * @param LanguageManagerInterface $language_manager
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $language_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->config = $config_factory->get('gd_regions.settings');
    $this->languageManager = $language_manager;
  }

  /**
   * Method description.
   */
  public function isMultiRegional() {
    $is_multiregional = &drupal_static(__FUNCTION__);

    if (!isset($is_multiregional)) {
      $is_multiregional = (bool) $this->config->get('multi_regional');
    }

    return $is_multiregional;
  }

  /**
   * @return Group[]
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getRegions() {
    $regions = &drupal_static(__FUNCTION__);
    if (isset($regions)) {
      return $regions;
    }

    $gids = Drupal::entityQuery('group')
      ->condition('type', self::getRegionGroupType())
      ->accessCheck(FALSE)
      ->execute();

    // Amount of regions will be reasonably small, so we can just load them all at once.
    $storage_handler = $this->entityTypeManager->getStorage("group");

    /* @var $raw_regions Group[] */
    $raw_regions = $storage_handler->loadMultiple($gids);

    $current_language = $this->languageManager->getCurrentLanguage();
    foreach ($raw_regions as $region) {
      $regions[] = $region->hasTranslation($current_language->getId()) ? $region->getTranslation($current_language->getId()) : $region;
    }

    return $regions;
  }

  public static function getRegionGroupType() {
    return self::REGION_GROUP_TYPE;
  }

  /**
   * Returns current region.
   *
   * @return Group|mixed
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getCurrentRegion() {
    $current_region = &drupal_static(__FUNCTION__);
    if (isset($current_region)) {
      return $current_region;
    }

    $request = $this->requestStack->getCurrentRequest();
    $request_path = urldecode(trim($request->getPathInfo(), '/'));
    $path_args = explode('/', $request_path);
    $prefix = array_shift($path_args);

    list($langcode, $region_code) = explode('-', $prefix);

    $current_region = $this->getRegionByUrlPrefix($region_code);

    // Making sure we got some results.
    $region = $current_region ? $current_region : $this->getDefaultRegion();
    $current_language = $this->languageManager->getCurrentLanguage();
    $current_region = $region->hasTranslation($current_language->getId()) ? $region->getTranslation($current_language->getId()) : $region;

    return $current_region;
  }

  public function getCurrentRegionCountryCode() {
    $region = $this->getCurrentRegion();

    return $region->field_iso_country_code->value;
  }

  public function isDefaultRegion() {
    // @todo: implement proper logic.
    return FALSE;
  }


  public function getDefaultRegion() {
    $default_region = &drupal_static(__FUNCTION__);

    if (isset($default_region)) {
      return $default_region;
    }

    $gids = Drupal::entityQuery('group')
      ->condition('type', self::getRegionGroupType())
      ->condition('field_is_default_region', TRUE)
      ->accessCheck(FALSE)
      ->execute();

    if (!$gids) {
      $gids = Drupal::entityQuery('group')
        ->condition('type', self::getRegionGroupType())
        ->accessCheck(FALSE)
        ->execute();
    }

    if (!$gids) {
      $group_data = [
        'type' => REGION_GROUP_TYPE,
        'label' => t('Default Group'),
        'field_available_languages' => [
          'en'
        ],
        'field_default_language' => 'en',
        'field_is_default_region' => TRUE,
        'field_url_prefix' => 'uk',
        'field_iso_country_code' => 'GB',
      ];

      $region = Group::create($group_data);
      $region->save();
    }
    else {
      $storage_handler = $this->entityTypeManager->getStorage("group");

      /* @var $region Group */
      $region = $storage_handler->load(reset($gids));
    }

    $current_language = $this->languageManager->getCurrentLanguage();
    $default_region = $region->hasTranslation($current_language->getId()) ? $region->getTranslation($current_language->getId()) : $region;

    return $default_region;
  }

  public function getDefaultRegionCode() {
    return $this->getDefaultRegion()->field_url_prefix->value;
  }

  /**
   * Returns region for specific URL prefix, if any.
   *
   * @param $region_code
   *
   * @return Group|null
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getRegionByUrlPrefix($region_code) {
    $regions = &drupal_static(__FUNCTION__);
    if (isset($regions[$region_code])) {
      return $regions[$region_code];
    }

    $gids = Drupal::entityQuery('group')
      ->condition('type', self::getRegionGroupType())
      ->condition('field_url_prefix', $region_code)
      ->accessCheck(FALSE)
      ->execute();

    if (!$gids) {
      $regions[$region_code] = NULL;
    }
    else {
      $storage_handler = $this->entityTypeManager->getStorage("group");
      $region = $storage_handler->load(reset($gids));

      $current_language = $this->languageManager->getCurrentLanguage();
      $regions[$region_code] = $region->hasTranslation($current_language->getId()) ? $region->getTranslation($current_language->getId()) : $region;
    }

    return $regions[$region_code];
  }

  public function getRegionByCountryCode($country_code) {
    $regions = &drupal_static(__FUNCTION__);
    if (isset($regions[$country_code])) {
      return $regions[$country_code];
    }

    $gids = Drupal::entityQuery('group')
      ->condition('type', self::getRegionGroupType())
      ->condition('field_iso_country_code', $country_code)
      ->accessCheck(FALSE)
      ->execute();

    if (!$gids) {
      $regions[$country_code] = NULL;
    }
    else {
      $storage_handler = $this->entityTypeManager->getStorage("group");
      $region = $storage_handler->load(reset($gids));

      $current_language = $this->languageManager->getCurrentLanguage();
      $regions[$country_code] = $region->hasTranslation($current_language->getId()) ? $region->getTranslation($current_language->getId()) : $region;
    }

    return $regions[$country_code];
  }

  public function validateFullPrefix($prefix) {
    return preg_match('/^[a-z]+-[a-z]+$/', $prefix);
  }


  public function validateRegionPrefix($prefix) {
    return preg_match('/^-[a-z]+$/', $prefix);
  }

  public function detectRegion() {
    $detection_info = Drupal\smart_ip\SmartIp::query();
    $country_code = $detection_info['countryCode'];

    return $this->getRegionByCountryCode($country_code);
  }


  public function detectLanguage() {
    $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);

    return $this->languageManager->getLanguage($lang);
  }
}
