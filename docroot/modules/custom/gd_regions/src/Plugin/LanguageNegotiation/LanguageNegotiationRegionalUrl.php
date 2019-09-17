<?php

namespace Drupal\gd_regions\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\gd_regions\RegionManager;
use Drupal\language\LanguageNegotiationMethodBase;
use Drupal\language\LanguageSwitcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for identifying language via URL prefix or domain.
 *
 * @LanguageNegotiation(
 *   id = "language-url-regional",
 *   types = {\Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_CONTENT,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_URL},
 *   weight = 10,
 *   name = @Translation("Regional URL"),
 *   description = @Translation("Language from the URL prefix. Region Dependant."),
 * )
 */
class LanguageNegotiationRegionalUrl extends LanguageNegotiationMethodBase implements InboundPathProcessorInterface, OutboundPathProcessorInterface, LanguageSwitcherInterface, ContainerFactoryPluginInterface {

  /**
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * Constructs a new SelectionBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\gd_regions\RegionManager $region_manager
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RegionManager $region_manager
  ) {
    $this->regionManager = $region_manager;
  }


  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('gd_regions.region_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    $langcode = NULL;

    $request_path = urldecode(trim($request->getPathInfo(), '/'));
    $path_args = explode('/', $request_path);

    // We're assuming that prefix will always exist and will always be valid, so don't need for extra checks.
    $prefix = explode('-', $path_args[0]);
    $langcode = $prefix[0];

    foreach ($this->languageManager->getLanguages() as $language) {
      if ($language->getId() == $langcode) {
        return $langcode;
      }
    }

    return $this->languageManager->getDefaultLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSwitchLinks(Request $request, $type, Url $url) {
    $links = [];
    $query = $request->query->all();

    foreach ($this->languageManager->getNativeLanguages() as $language) {
      $links[$language->getId()] = [
        // We need to clone the $url object to avoid using the same one for all
        // links. When the links are rendered, options are set on the $url
        // object, so if we use the same one, they would be set for all links.
        'url' => clone $url,
        'title' => $language->getName(),
        'language' => $language,
        'attributes' => ['class' => ['language-link']],
        'query' => $query,
      ];
    }

    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    // We're handling everything in region path processor.
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    // We're handling everything in region path processor.
    return $path;
  }
}
