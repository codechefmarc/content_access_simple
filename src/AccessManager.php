<?php

namespace Drupal\content_access_simple;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;

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
   * Constructs a new AccessManager service.
   *
   * @var Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   */
  public function __construct(
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager
    ) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
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
    $user_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $roles = [];
    foreach ($user_roles as $role) {
      /** @var Drupal\user\Entity\Role $role */
      $roles[$role->id()] = $role->get('label');
    }
    return $roles;
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
      '#weight' => 100,
    ];

    if ($defaults['view'] != $defaults['view_own']) {
      $form['content_access_simple']['complex_message'] = [
        '#theme' => 'complex_message',
        '#weight' => -10,
      ];
      return;
    }

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
