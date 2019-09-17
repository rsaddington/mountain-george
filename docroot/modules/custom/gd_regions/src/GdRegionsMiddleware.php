<?php

namespace Drupal\gd_regions;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\smart_ip\SmartIp;

/**
 * This middleware checks if there's a region/language prefix, if it's valid and redirects to valid one if
 * both previous conditions are false.
 */
class GdRegionsMiddleware implements HttpKernelInterface {

  use StringTranslationTrait;

  /**
   * The kernel.
   *
   * @var HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The kernel.
   *
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The kernel.
   *
   * @var RegionManager
   */
  protected $regionManager;

  /**
   * Constructs the GdRegionsMiddleware object.
   *
   * @param HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param LanguageManagerInterface $language_manager
   * @param RegionManager $region_manager
   */
  public function __construct(
    HttpKernelInterface $http_kernel,
    LanguageManagerInterface $language_manager,
    RegionManager $region_manager
  ) {
    $this->httpKernel = $http_kernel;
    $this->languageManager = $language_manager;
    $this->regionManager = $region_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    if (!$this->regionManager->isMultiRegional()) {
      return $this->httpKernel->handle($request, $type, $catch);
    }


    // Insuring that prefix consist of proper region and language that both exist in system.
    $request_path = urldecode(trim($request->getPathInfo(), '/'));
    $path_args = explode('/', $request_path);
    $prefix = array_shift($path_args);

    if ($prefix == 'api') {
      return $this->httpKernel->handle($request, $type, $catch);
    }


    if (!$this->regionManager->validateFullPrefix($prefix)) {
      return $this->detectRegionAndLanguage($request);
    }

    list($language_code, $region_code) = explode('-', $prefix);
    if (!$this->regionManager->getRegionByUrlPrefix($region_code) || !$this->languageManager->getLanguage($language_code)) {
      return $this->detectRegionAndLanguage($request);
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Redirecting to properly prefixed path.
   *
   * @param Request $request
   * @return RedirectResponse
   */
  private function detectRegionAndLanguage(Request $request) {
    $path = $request->getPathInfo();
    $host = $request->getSchemeAndHttpHost();
    $query = $request->getQueryString() === NULL ? '' : '?' . $request->getQueryString();

    $detected_region = $this->regionManager->detectRegion();
    $detected_language = $this->regionManager->detectLanguage();

    $default_region = $detected_region ? $detected_region->field_url_prefix->value : $this->regionManager->getDefaultRegionCode();
    $default_language = $detected_language ? $detected_language->getId() : $this->languageManager->getDefaultLanguage()->getId();

    $url = "$host/$default_language-$default_region$path$query";

    return new RedirectResponse($url, 301);
  }

}
