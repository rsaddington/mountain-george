<?php

namespace Drupal\gd_regions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\gd_regions\RegionManager;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Region settings settings for this site.
 */
class SettingsForm extends ConfigFormBase {


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
   * @param ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param RegionManager $region_manager
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RegionManager $region_manager
  ) {
    $this->setConfigFactory($config_factory);
    $this->regionManager = $region_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('gd_regions.region_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gd_regions_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['gd_regions.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $multi_region = $this->config('gd_regions.settings')->get('multi_regional');

    $form = parent::buildForm($form, $form_state);
    if (!$multi_region) {
      $form['actions']['submit']['#value'] = $this->t('Enable multi-regional support.');
    }
    else {
      $form['actions']['submit']['#value'] = $this->t('Disable multi-regional support.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $multi_region = (bool) $this->config('gd_regions.settings')->get('multi_regional');

    $this->config('gd_regions.settings')
      ->set('multi_regional', !$multi_region)
      ->save();

    if (!$multi_region) {

      $language_settings = \Drupal::configFactory()->getEditable('language.types')->get('negotiation');
      $language_settings['language_interface']['enabled']['language-url-regional'] = -20;
      $language_settings['language_interface']['method_weights']['language-url-regional'] = -20;

      \Drupal::configFactory()->getEditable('language.types')->set('negotiation', $language_settings)->save();
    }

    parent::submitForm($form, $form_state);
  }

}
