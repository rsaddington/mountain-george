<?php


namespace Drupal\gd_commercetools_integration;

/**
 * Product interface.
 *
 * Interface ProductInterface
 * @package Drupal\gd_commercetools_integration
 */
interface ProductInterface
{
  public function getId();

  /**
   * @param $langcode
   *   Drupal langcode
   *
   * @return string
   */
  public function getTitle($langcode);


  /**
   * @param $langcode
   *   Drupal langcode
   *
   * @return string
   */
  public function getDescription($langcode);

  /**
   * @return VariantInterface[]
   */
  public function getVariants();

  /**
   * @return array
   */
  public function getAvailability();

  /**
   * Return images array, including url, title and alt.
   *
   * @return array
   *
   * <pre>
   * [
   *   'url' => 'http://example.com/image.jpg',
   *   'title' => 'Image Title',
   *   'alt' => 'Image Alt',
   *   'thumb_url' => 'http://example.com/image_thumb.jpg',
   * ];
   * </pre>
   */
  public function getImages();

  /**
   * @param $langcode
   * @return string[]
   *   Array of metada including title, description and keywords keys.
   *
   * <pre>
   * [
   *   'title' => 'MetaTitle',
   *   'description' => 'MetaDescription',
   *   'keywords' => 'MetaKeyword1, MetaKeyword2',
   * ];
   * </pre>
   */
  public function getMetadata($langcode);

  /**
   * @return array
   */
  public function getCategoryIds();

  /**
   * @return string
   */
  public function getVersion();

  public function getTypeId();
}