<?php

namespace Drupal\gd_product\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\gd_product\ProductSynchroniser;

/**
 * Defines 'gd_product_product_synchronise_queue' queue worker.
 *
 * @QueueWorker(
 *   id = "gd_product_product_synchronise_queue",
 *   title = @Translation("Product Synchronise Queue"),
 *   cron = {"time" = 2}
 * )
 */
class GdProductQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface  {

  /**
   * @var ProductSynchroniser
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
      $container->get('gd_product.product_synchroniser')
    );
  }

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ProductSynchroniser $product_synchroniser
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
