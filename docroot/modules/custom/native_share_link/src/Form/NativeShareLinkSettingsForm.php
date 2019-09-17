<?php

/**
 * @file
 * Contains \Drupal\area_interest\Form\AreaInterestSettingsForm
 */

namespace Drupal\native_share_link\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defiens a form to configure area of interest module settings.
 */
class NativeShareLinkSettingsForm extends ConfigFormBase {

  /**
   * (@inheritdoc)
   */
  public function getFormId() {
    return 'native_share_link_settings';
  }

  /**
   * (@inheritdoc)
   */
  protected function getEditableConfigNames() {
    return [
      'native_share_link.settings',
    ];
  }

  /**
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {

    $config = $this->config('native_share_link.settings');

    $form['email'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#title' => $this->t('Email Settings'),
    ];

    $form['email']['share'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Share Link'),
      '#default_value' => $config->get('email.share'),
      '#description' => t('Enable email share button.'),
    ];

    $form['facebook'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#title' => $this->t('Facebook Settings'),
    ];

    $form['facebook']['app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your app id'),
      '#default_value' => $config->get('facebook.app_id'),
    ];

    $form['facebook']['share'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Share Link'),
      '#default_value' => $config->get('facebook.share'),
      '#description' => t('Enables Facebook share page and post to the user facebook wall.'),
    ];

    $form['twitter'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#title' => $this->t('Twitter Settings'),
    ];

    $form['twitter']['share'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Share Link'),
      '#default_value' => $config->get('twitter.share'),
      '#description' => t('Enables twitter share page and post to the user facebook wall.'),
    ];

    $form['pinterest'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#title' => $this->t('Pinterest Settings'),
    ];

    $form['pinterest']['share'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Share Link'),
      '#default_value' => $config->get('pinterest.share'),
      '#description' => t('Enables Pinterest share will allow user to share any image on the page to their Pinterest board.'),
    ];

    return parent::buildForm($form, $form_state);
  }


  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $facebook_settings = $form_state->getValue('facebook');
    $twitter_settings = $form_state->getValue('twitter');
    $pinterest_settings = $form_state->getValue('pinterest');
    $email_settings = $form_state->getValue('email');

    $config = $this->config('native_share_link.settings');
    $config->set('email', $email_settings);
    $config->set('facebook', $facebook_settings);
    $config->set('twitter', $twitter_settings);
    $config->set('pinterest', $pinterest_settings);

    $config->save();



    parent::submitForm($form, $form_state);
  }

}