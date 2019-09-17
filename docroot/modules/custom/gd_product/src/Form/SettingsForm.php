<?php

namespace Drupal\gd_product\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\gd_commercetools_integration\ProductProviderInterface;
use Drupal\gd_product\CatalogManager;
use Drupal\gd_regions\RegionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure GD Product settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * @var ProductProviderInterface
   */
  private $productProvider;

  /**
   * @var RegionManager
   */
  private $regionManager;

  /**
   * @var LanguageManagerInterface
   */
  private $languageManager;

  /**
   * @var CatalogManager
   */
  private $catalogManager;

  /**
   * Constructs a \Drupal\statistics\StatisticsSettingsForm object.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param ProductProviderInterface $product_provider
   * @param RegionManager $gd_regions_region_manager
   * @param LanguageManagerInterface $language_manager
   * @param CatalogManager $catalog_manager
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ProductProviderInterface $product_provider,
    RegionManager $gd_regions_region_manager,
    LanguageManagerInterface $language_manager,
    CatalogManager $catalog_manager
  ) {
    parent::__construct($config_factory);
    $this->productProvider = $product_provider;
    $this->regionManager = $gd_regions_region_manager;
    $this->languageManager = $language_manager;
    $this->catalogManager = $catalog_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('gd_commercetools_integration.product_provider_commercetools'),
      $container->get('gd_regions.region_manager'),
      $container->get('language_manager'),
      $container->get('gd_product.catalog_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gd_product_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['gd_product.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $product_types = $this->productProvider->getProductTypes();
    $cur_langcode = $this->languageManager->getCurrentLanguage()->getId();

    $form['product_sync_interval'] = [
      '#type' => 'textfield',
      '#description' => $this->t('Defines how ofter product nodes will be synchronised with commercetools. Value in seconds.'),
      '#title' => $this->t('Product sync interval'),
      '#default_value' => $this->config('gd_product.settings')->get('product_sync_interval'),
    ];

    $form['attributes_to_show_on_specs'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
    ];

    foreach ($product_types as $product_type) {
      $form['attributes_to_show_on_specs']['attributes_to_show_on_specs_block_' . $product_type->getId()] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Attributes to show on specs blocks.'),
        '#default_value' => $this->config('gd_product.settings')->get('attributes_to_show_on_specs_block_' . $product_type->getId()),
        '#options' => [],
      ];

      foreach ($product_type->getAttributeDefinitions() as $attribute_id => $attribute) {
        $form['attributes_to_show_on_specs']['attributes_to_show_on_specs_block_' . $product_type->getId()]['#options'][$attribute_id] = $attribute->getName($cur_langcode);
      }
    }




    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('attributes_to_show_on_specs') as $key => $config) {
      $this->config('gd_product.settings')->set($key, array_filter($config));
    }

    $this->config('gd_product.settings')
      ->set('product_sync_interval', $form_state->getValue('product_sync_interval'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
