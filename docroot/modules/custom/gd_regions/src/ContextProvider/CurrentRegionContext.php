<?php

namespace Drupal\gd_regions\ContextProvider;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\gd_regions\RegionManager;

/**
 * Sets the current language as a context.
 */
class CurrentRegionContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The language manager.
   *
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * Constructs a new CurrentLanguageContext.
   *
   * @param \Drupal\gd_regions\RegionManager $region_manager
   *   The language manager.
   */
  public function __construct(RegionManager $region_manager) {
    $this->regionManager = $region_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $result = [];
    $context = new Context(new ContextDefinition('entity:group', 'region'), $this->regionManager->getCurrentRegion());

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['region']);
    $context->addCacheableDependency($cacheability);

    $result['region'] = $context;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    if (!$this->regionManager->isMultiRegional()) {
      return [];
    }

    // Not specifying where we taking this context from since we're using a lot
    // of sources to retrieve department from.
    $context = new Context(new ContextDefinition('entity:group', $this->t('Region context.')));

    return [
      'region' => $context,
    ];
  }

}
