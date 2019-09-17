<?php

namespace Drupal\gd_regions\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\example\ExampleInterface;
use Drupal\gd_regions\RegionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a region switcher block.
 *
 * @Block(
 *   id = "gd_regions_region_switcher",
 *   admin_label = @Translation("Region Switcher"),
 *   category = @Translation("Regions")
 * )
 */
class RegionSwitcherBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The gd_regions.region_manager service.
   *
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * The language manager.
   *
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The path matcher.
   *
   * @var PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The request object.
   *
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new RegionSwitcherBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param RegionManager $gd_regions_region_manager
   *   The gd_regions.region_manager service.
   * @param LanguageManagerInterface $language_manager
   *   The language manager.
   * @param PathMatcherInterface $path_matcher
   * @param RequestStack $request_stack
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RegionManager $gd_regions_region_manager,
    LanguageManagerInterface $language_manager,
    PathMatcherInterface $path_matcher,
    RequestStack $request_stack
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->regionManager = $gd_regions_region_manager;
    $this->languageManager = $language_manager;
    $this->pathMatcher = $path_matcher;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('gd_regions.region_manager'),
      $container->get('language_manager'),
      $container->get('path.matcher'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_name = $this->pathMatcher->isFrontPage() ? '<front>' : '<current>';
    Url::fromRoute($route_name);

    $languages = $this->languageManager->getLanguages();
    $regions = $this->regionManager->getRegions();
    $current_language = $this->languageManager->getCurrentLanguage();
    $current_region = $this->regionManager->getCurrentRegion();

    $region_links = [];
    $language_links = [];


    // Building region and language links.
    foreach ($regions as $region) {
      $available_languages = $region->field_available_languages->getValue();
      $res = [];
      foreach ($available_languages as $available_language) {
        $res[$available_language['value']] = TRUE;
      }
      $available_languages = $res;

      // Building language links for current region only.
      if ($region->id() == $current_region->id()) {
        foreach ($available_languages as $langcode => $available_language) {
          // No need to build link for current language.
          if ($langcode == $current_language->getId()) {
            continue;
          }
          $language_links[] = [
            '#title' => $languages[$langcode]->getName(),
            '#type' => 'link',
            '#url' => Url::fromRoute($route_name, [], ['region' => $region, 'language' => $languages[$langcode]]),
            '#query' => $this->requestStack->getCurrentRequest()->query->all(),
            '#attributes' => [
              'class' => ['dropdown-item'],
            ],
          ];
        }

        // We don't want to build region link for current regions.
        continue;
      }

      if (isset($available_languages[$current_language->getId()])) {
        $region_language = $current_language;
      }
      else {
        $region_default_lang = $region->field_default_language->value;
        $region_language = $languages[$region_default_lang];
      }

      $region_links[] = [
        '#title' => $region->label(),
        '#type' => 'link',
        '#url' => Url::fromRoute($route_name, [], ['region' => $region, 'language' => $region_language]),
        '#query' => $this->requestStack->getCurrentRequest()->query->all(),
        '#attributes' => [
          'class' => ['dropdown-item'],
        ],
      ];
    }

    $build = [
      '#theme' => 'gd_regions_region_switcher',
      '#region_links' => $region_links,
      '#language_links' => $language_links,
      '#current_language' => $current_language->getName(),
      '#current_region' => $current_region->label(),
      '#cache' => [
//        'contexts' => [
//          'languages:' . LanguageInterface::TYPE_URL,
//          'region',
//        ],
        'max-age' => 0,
      ],
    ];

    return $build;
  }

}
