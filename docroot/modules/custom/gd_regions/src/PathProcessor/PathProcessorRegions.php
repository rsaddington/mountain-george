<?php

namespace Drupal\gd_regions\PathProcessor;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\gd_regions\RegionManager;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\EventSubscriber\ConfigSubscriber;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processes the inbound path using path alias lookups.
 */
class PathProcessorRegions implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * A config factory for retrieving required config settings.
   *
   * @var ConfigFactoryInterface
   */
  protected $config;

  /**
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * The kernel.
   *
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The language negotiator.
   *
   * @var LanguageNegotiatorInterface
   */
  protected $negotiator;

  /**
   * Local cache for language path processors.
   *
   * @var array
   */
  protected $processors;

  /**
   * Flag indicating whether the site is multilingual.
   *
   * @var bool
   */
  protected $multilingual;

  /**
   * The language configuration event subscriber.
   *
   * @var ConfigSubscriber
   */
  protected $configSubscriber;

  /**
   * Constructs a PathProcessorLanguage object.
   *
   * @param RegionManager $region_manager
   * @param LanguageManagerInterface $language_manager
   */
  public function __construct(
    RegionManager $region_manager,
    LanguageManagerInterface $language_manager
  ) {
    $this->regionManager = $region_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if (!$this->regionManager->isMultiRegional()) {
      return $path;
    }

    $parts = explode('/', trim($path, '/'));
    $prefix = array_shift($parts);
    if ($prefix == 'api') {
      return $path;
    }

    if (!$this->regionManager->validateFullPrefix($prefix)) {
      return $path;
    }

    // Middleware already made sure that prefix is correct.
    list($langcode, $region_code) = explode('-', $prefix);

    $path = '/' . implode('/', $parts);

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!$this->regionManager->isMultiRegional()) {
      return $path;
    }

    // Language.
    $languages = array_flip(array_keys($this->languageManager->getLanguages()));
    if (!isset($options['language'])) {
      $language_url = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL);
      $options['language'] = $language_url;
    }
    elseif (!is_object($options['language']) || !isset($languages[$options['language']->getId()])) {
      return $path;
    }

    // Region.
    if (!isset($options['region'])) {
      $region = $this->regionManager->getCurrentRegion();
      $options['region'] = $region;
    }


    $options['prefix'] = $options['language']->getId() . '-' . $options['region']->field_url_prefix->value . '/';
    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheContexts(['languages:' . LanguageInterface::TYPE_URL]);
      $bubbleable_metadata->addCacheContexts(['region']);
    }

    return $path;
  }
}
