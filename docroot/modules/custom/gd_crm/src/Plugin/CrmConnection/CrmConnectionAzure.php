<?php

namespace Drupal\gd_crm\Plugin\CrmConnection;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\gd_crm\CrmConnectionInterface;
use Drupal\webform\WebformInterface;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

/**
 * @CrmConnection(
 *   id = "azure",
 *   label = @Translation("Azure CRM Connection"),
 *   description = @Translation("Azure CRM Connection.")
 * )
 */
class CrmConnectionAzure extends PluginBase implements CrmConnectionInterface {

  public function alterWebformSettingsForm(WebformInterface $webform, array &$form, FormStateInterface $form_state) {
    $crm_settings = $webform->getThirdPartySettings('gd_crm');

    $form['third_party_settings']['gd_crm'] = [
      '#tree' => TRUE,
      '#title' => t('CRM Integration'),
      '#type' => 'details',
      '#open' => FALSE,
    ];

    $form['third_party_settings']['gd_crm']['enable_crm_integration'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable CRM Integration'),
      '#default_value' => $crm_settings['enable_crm_integration'] ?? FALSE,
    ];

    $form['third_party_settings']['gd_crm']['crm_form_id'] = [
      '#type' => 'textfield',
      '#title' => t('CRM form ID.'),
      '#default_value' => $crm_settings['crm_form_id'] ?? '',
    ];
  }

  public function alterConfigForm($configs) {

  }

  public function getDealersInfo($configs) {

  }

  public function sendWebformSubmission($webform_config, $data) {
    $configs = \Drupal::configFactory()->get('gd_crm.settings');

    $lang = strtoupper(\Drupal::languageManager()->getCurrentLanguage()->getId());
    $country = \Drupal::service('gd_regions.region_manager')->getCurrentRegionCountryCode();

    $data['websiteCountry'] = $country;
    $data['websitelanguage'] = $lang;
    $data['formIdentifier'] = $webform_config['crm_form_id'];

    $endpoint = $configs->get('crm_endpoint_url');
    $token = $configs->get('crm_auth_token');

    $client = \Drupal::httpClient();

    $options = [
      'headers' => [
        'x-functions-key' => $token,
      ],
      'query' => [
        'formIdentifier' => $webform_config['crm_form_id'],
      ],
      RequestOptions::JSON => $data,
    ];

    try {
      $response = $client->post($endpoint, $options);
    }
    catch (ClientException $e) {
      \Drupal::messenger()->addMessage(t('Unable to send submission. Please try again later.'), 'error');
      return '';
    }

    $response = json_decode((string) $response->getBody());
    return $response->requestId ?? '';
  }

}