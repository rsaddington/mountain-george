<?php

namespace Drupal\gd_product\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\gd_product\CategorySynchroniser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\gd_product\ProductSynchroniser;

/**
 * Defines 'gd_product_category_synchronise_queue' queue worker.
 *
 * @QueueWorker(
 *   id = "gd_product_category_synchronise_queue",
 *   title = @Translation("Category Synchronise Queue"),
 *   cron = {"time" = 2}
 * )
 */
class GdCategoryQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface  {

  /**
   * @var \Drupal\gd_product\CategorySynchroniser
   */
  private $productSynchroniser;

  /**
   * DI related method.
   *
   * @param ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @return ContainerFactoryPluginInterface|GdProductQueue
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('gd_product.category_synchroniser')
    );
  }

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CategorySynchroniser $product_synchroniser
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->productSynchroniser = $product_synchroniser;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->productSynchroniser->doSync($data);
  }

}
