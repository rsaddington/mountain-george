<?php

namespace Drupal\gd_crm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformInterface;

interface CrmConnectionInterface {
  public function alterWebformSettingsForm(WebformInterface $webform, array &$form, FormStateInterface $form_state);
  public function alterConfigForm($configs);
  public function getDealersInfo($configs);
  public function sendWebformSubmission($webform_config, $data);
}