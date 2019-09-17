<?php

namespace Drupal\gd_crm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure GD CRM Integration settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gd_crm_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['gd_crm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo: add more settings for different situations.
    $form['crm_endpoint_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CRM Endpoint URL'),
      '#default_value' => $this->config('gd_crm.settings')->get('crm_endpoint_url'),
    ];

    $form['crm_file_upload_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CRM File Upload URL'),
      '#default_value' => $this->config('gd_crm.settings')->get('crm_file_upload_url'),
    ];

    $form['crm_auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CRM Auth Token'),
      '#default_value' => $this->config('gd_crm.settings')->get('crm_auth_token'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('gd_crm.settings')
      ->set('crm_endpoint_url', $form_state->getValue('crm_endpoint_url'))
      ->set('crm_file_upload_url', $form_state->getValue('crm_file_upload_url'))
      ->set('crm_auth_token', $form_state->getValue('crm_auth_token'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
