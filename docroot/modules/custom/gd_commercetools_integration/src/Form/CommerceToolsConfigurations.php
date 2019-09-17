<?php

namespace Drupal\gd_commercetools_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure GD Commercetools integration. settings for this site.
 */
class CommerceToolsConfigurations extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gd_commercetools_integration_commerce_tools_configurations';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['gd_commercetools_integration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $this->config('gd_commercetools_integration.settings')->get('client_id'),
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $this->config('gd_commercetools_integration.settings')->get('client_secret'),
    ];

    $form['project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project'),
      '#default_value' => $this->config('gd_commercetools_integration.settings')->get('project'),
    ];

    $form['product_type_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Product Type Key'),
      '#default_value' => $this->config('gd_commercetools_integration.settings')->get('product_type_key'),
    ];

    $default_mapping = $this->config('gd_commercetools_integration.settings')->get('language_mapping');

    if ($default_mapping) {
      $mapping = '';

      foreach ($default_mapping as $key =>  $map) {
        $mapping .= "$key|$map" . PHP_EOL;
      }

      $default_mapping = $mapping;
    }
    else {
      $default_mapping = '';
    }

    $form['language_mapping'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Commercetools to Drupal language code mapping'),
      '#default_value' => $default_mapping,
    ];

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
    $language_mapping = $form_state->getValue('language_mapping');
    $lines = preg_split('/\r\n|\r|\n/', trim($language_mapping));
    $array_mapping = [];

    foreach ($lines as $line) {
      $key_value = explode('|', $line);
      $array_mapping[$key_value[0]] = $key_value[1];
    }

    $this->config('gd_commercetools_integration.settings')
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      ->set('project', $form_state->getValue('project'))
      ->set('product_type_key', $form_state->getValue('product_type_key'))
      ->set('language_mapping', $array_mapping)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
