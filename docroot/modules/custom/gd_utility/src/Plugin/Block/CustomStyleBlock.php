<?php

namespace Drupal\gd_utility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\example\ExampleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom style block block.
 *
 * @Block(
 *   id = "gd_utility_custom_style_block",
 *   admin_label = @Translation("Custom Style Block"),
 *   category = @Translation("Utility")
 * )
 */
class CustomStyleBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $config;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new CustomStyleBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'foo' => $this->t('Hello world!'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['#config_count'] = $form['#config_count'] ?? (isset($this->configuration['data']) ? count($this->configuration['data']) : 0);


    $form['key_values'] = [
      '#type' => 'details',
      '#title' => $this->t('Key-Values'),
      '#attributes' => [
        'id' => 'key-values',
      ],
      '#open' => TRUE,
    ];

    // We're using ::getUserInput() instead of ::getValues() since https://www.drupal.org/project/drupal/issues/2798261
    $input = $form_state->getUserInput();

    $data = [];
    if (isset($input['settings']['key_values'])) {
      $data = $input['settings']['key_values'];
    }
    else if (isset($this->configuration['data'])) {
      $data = $this->configuration['data'];
    }

    for ($i = 0; $i < count($data) / 2; $i++) {
      $form['key_values']['key_' . $i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Key'),
        '#default_value' => $this->configuration['data']['key_' . $i] ?? '',
      ];

      $form['key_values']['value_' . $i] = [
        '#type' => 'textarea',
        '#title' => $this->t('Value'),
        '#default_value' => $this->configuration['data']['value_' . $i] ?? '',
      ];
    }

    $form['key_values']['key_' . $i] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key'),
    ];

    $form['key_values']['value_' . $i] = [
      '#type' => 'textarea',
      '#title' => $this->t('Value'),
    ];

    $form['add_more'] = [
      '#type' => 'button',
      '#value' => $this->t('Add More'),
      '#ajax' => [
        'wrapper' => 'key-values',
        'callback' => 'Drupal\gd_utility\Plugin\Block\CustomStyleBlock::updateKeyValues'
      ],
    ];

    return $form;
  }

  public function updateKeyValues($form, FormStateInterface $form_state) {
    return $form['settings']['key_values'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getUserInput();
    $data = array_filter($values['settings']['key_values']);

    $this->configuration['data'] = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#markup' => $this->t('Override this block in template.'),
    ];
    return $build;
  }

}
