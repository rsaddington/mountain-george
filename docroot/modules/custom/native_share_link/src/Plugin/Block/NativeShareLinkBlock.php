<?php

namespace Drupal\native_share_link\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Share Link' Block.
 *
 * @Block(
 *   id = "native_share_link_block",
 *   admin_label = @Translation("Native Share Link"),
 *   category = @Translation("Glen Dimplex"),
 * )
 */
class NativeShareLinkBlock extends BlockBase {

  /**
   * {@inheritdoc}
   *
   * Dynamically load libraries.
   */
  public function build() {


    $socials = [];
    $libraries = [];
    $drupalSettings = [];
    $config = \Drupal::config('native_share_link.settings');

    $libraries[] = 'native_share_link/main';

    if ($config->get('facebook.app_id')) {
      $drupalSettings['facebook'] = ['facebookJS' => ['app_id' => $config->get('facebook.app_id')]];
      $libraries[] = 'native_share_link/facebook';
      if($config->get('facebook.share')) {
        $socials['facebook']['share'] = 1 ;
      }
    }

    if ($config->get('twitter.share')) {
      $libraries[] = 'native_share_link/twitter';
      $socials['twitter']['share'] = 1 ;
    }

    if ($config->get('pinterest.share')) {
      $libraries[] = 'native_share_link/pinterest';
      $socials['pinterest']['share'] = 1 ;
    }

    if ($config->get('email.share')) {
      $socials['email']['share'] = 1 ;
    }

    return [
      '#attached' => array(
        'library' => $libraries,
        'drupalSettings' => $drupalSettings,
      ),
      '#theme' => 'native_share_link',
      '#socials' => $socials,
    ];
  }

}