<?php

namespace Drupal\cheppers_node_list_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserData as UserDataStorage;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a block of the title of the 10 most recent node content by default.
 *
 * @Block(
 *   id = "cheppers_node_list_block",
 *   admin_label = @Translation("Cheppers Node List Block")
 * )
 */
class CheppersNodeListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * Builds a user data entity destination.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\user\UserData $user_data
   *   The user data service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user, UserDataStorage $user_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('user.data')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    // Get the value of number of nodes set.
    $node_number = $this->getNodeNumberValue();

    $build = [
      'content' => [
        '#type' => 'markup',
        '#markup' => t('No content available.'),
      ],
    ];

    // Check if published content has been added.
    if ($nodes = node_get_recent((int) $node_number)) {
      $node_list_items = [];

      // Create node links.
      foreach ($nodes as $node) {
        $n_id = $node->id();
        $n_title = $node->getTitle();
        $node_list_items[$n_id] = Link::fromTextAndUrl(
          $n_title,
          Url::fromRoute(
            'entity.node.canonical',
            ['node' => $n_id]
          )
        );
      }

      // Add links to list.
      $build['content'] = [
        '#theme' => 'item_list',
        '#items' => $node_list_items,
      ];
    }

    // Add the 'user' cache context to the block.
    $build['#cache'] = [
      'contexts' => [
        'user',
      ],
      'max-age' => 0,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission(
      $account,
      'view cheppers node list block'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_config = \Drupal::config('cheppers_node_list_block.settings');

    return [
      'node_number' => $default_config->get('node_list.item_number'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;

    $form['node_number'] = [
      '#type' => 'number',
      '#title' => t('Number of nodes to list'),
      '#default_value' => $config['node_number'],
      '#min' => 0,
      '#max' => 100,
      '#description' => $this->t("Set number of nodes to list in this block."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['node_number'] = $form_state->getValue('node_number');
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    if (!is_numeric($form_state->getValue('node_number'))) {
      $form_state->setErrorByName(
        'node_number',
        $this->t('Value must be an integer.')
      );
    }
  }

  /**
   * Retrieve the number of nodes to show in block.
   *
   * @return string
   *   The value set by user or the default value.
   */
  public function getNodeNumberValue() {
    // Get current user object.
    $user = $this->currentUser;
    // Get block configuration.
    $config = $this->configuration;

    // Check if user is authenticated and has relevant permission.
    if ($user && $user->isAuthenticated() && $user->hasPermission('view cheppers node list block')) {
      // Get block value set by user.
      $node_number = $this->userData->get(
        'cheppers_node_list_block',
        $user->id(),
        'node_number'
      );
    }

    // Return value based on user setting, if available.
    return (isset($node_number) && $node_number !== FALSE) ? $node_number : $config['node_number'];
  }

}
