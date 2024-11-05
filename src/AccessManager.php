<?php

namespace Drupal\content_access_simple;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Service to handle Content Access Simple functions.
 */
class AccessManager {

  use StringTranslationTrait;

  /**
   * The account interface.
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager interface.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Content access configuration.
   */
  protected ConfigFactoryInterface $contentAccessSimpleConfig;

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new AccessManager service.
   *
   * @var Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   */
  public function __construct(
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    ) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->contentAccessSimpleConfig = $config_factory;
    $this->logger = $logger_factory->get('content_access_simple');
  }

  /**
   * Determine if content access is turned on per node and user has permission.
   */
  public function hasAccess(Node $node) {
    $nodeType = $node->getType();
    $contentAccessPerNode = content_access_get_settings('per_node', $nodeType);

    if (!$contentAccessPerNode) {
      return FALSE;
    }

    $allNodesAccess = $this->currentUser->hasPermission('grant content access simple');
    $ownNodeAccess = $this->currentUser->hasPermission('grant own content access simple') && ($this->currentUser->id() == $node->getOwnerId());

    return $allNodesAccess || $ownNodeAccess;
  }

  /**
   * Gets all the roles in the system.
   */
  private function getRoles() {
    $hiddenRoles = $this->getHiddenRoles();

    $user_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $roles = [];
    foreach ($user_roles as $role) {
      /** @var Drupal\user\Entity\Role $role */
      if (!in_array($role->id(), $hiddenRoles)) {
        $roles[$role->id()] = $role->get('label');
      }
    }
    return $roles;
  }

  /**
   * Check if permissions are too complex to use content access simple.
   */
  public function isComplex(NodeInterface $node) {
    // Checks if view_own permissions on node differ from defaults.
    $nodeOwnAccessRoles = content_access_per_node_setting('view_own', $node);
    $defaultOwnAccessRoles = content_access_get_settings('view_own', $node->getType());

    if ($nodeOwnAccessRoles != $defaultOwnAccessRoles) {
      if ($this->debugEnabled()) {
        $message = $this->t(
          'The view own permissions for @node_title (@node_id) differ from the view own permissions for the @node_type content type.',
            [
              '@node_title' => $node->getTitle(),
              '@node_id' => $node->id(),
              '@node_type' => $node->getType(),
            ]
        );
        $this->logger->warning($message);
      }
      return TRUE;
    }

    // Checks if 'view' hidden role permissions differ on node from defaults.
    $nodeAllAccessRoles = content_access_per_node_setting('view', $node);
    $defaultAllAccessRoles = content_access_get_settings('view', $node->getType());
    $hiddenRoles = $this->getHiddenRoles();

    foreach ($hiddenRoles as $hiddenRole) {
      $inNodeAccess = in_array($hiddenRole, $nodeAllAccessRoles);
      $inDefaultAccess = in_array($hiddenRole, $defaultAllAccessRoles);

      if ($inNodeAccess !== $inDefaultAccess) {
        if ($this->debugEnabled()) {
          $message = $this->t(
            'The view permissions for hidden roles for @node_title (@node_id) differ from the view permissions for the @node_type content type.',
              [
                '@node_title' => $node->getTitle(),
                '@node_id' => $node->id(),
                '@node_type' => $node->getType(),
              ]
          );
          $this->logger->warning($message);
        }
        return TRUE;
      }
    }

    return FALSE;

  }

  /**
   * Retrieves hidden roles from this module config.
   */
  public function getHiddenRoles() {
    $hiddenRoles = [];
    $config = $this->contentAccessSimpleConfig->get('content_access_simple.settings')->get('role_config');
    $hiddenRoles = !empty($config) ? $config['hidden_roles'] : [];

    return $hiddenRoles;
  }

  /**
   * Returns if debugging is enabled.
   */
  private function debugEnabled() {
    $debugEnabled = $this->contentAccessSimpleConfig->get('content_access_simple.settings')->get('debug') ?: FALSE;

    return $debugEnabled;
  }

  /**
   * Adds content access form elements to the node form.
   */
  public function addAccessFormElements(array &$form, $node) {
    $defaults = [];

    foreach (_content_access_get_operations() as $op => $label) {
      $defaults[$op] = content_access_per_node_setting($op, $node);
    }

    $form['content_access_simple'] = [
      '#type' => 'details',
      '#title' => $this->t('Access and Permissions'),
      '#open' => FALSE,
    ];

    // Show the complex message and end the form if complex.
    if ($this->isComplex($node)) {
      $form['content_access_simple']['complex_message'] = [
        '#theme' => 'complex_message',
        '#weight' => -10,
      ];
      return $form;
    }

    // Show the unpublished message if unpublished.
    if (!$node->isPublished()) {
      $form['content_access_simple']['unpublish_message'] = [
        '#theme' => 'unpublished_message',
        '#weight' => -10,
      ];
    }

    $form['content_access_simple']['view'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Visibility'),
      '#options' => $this->getRoles(),
      '#default_value' => $defaults['view'],
    ];

    // Runs Content Access disableCheckboxes for this module as well.
    $form['content_access_simple']['view']['#process'] = [
      [
        '\Drupal\Core\Render\Element\Checkboxes',
        'processCheckboxes',
      ],
      [
        '\Drupal\content_access\Form\ContentAccessRoleBasedFormTrait',
        'disableCheckboxes',
      ],
    ];

  }

}
