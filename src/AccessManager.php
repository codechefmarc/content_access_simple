<?php

namespace Drupal\content_access_simple;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\node\Entity\Node;
use Drupal\node\NodeForm;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;

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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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
    ModuleHandlerInterface $module_handler,
    ) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->contentAccessSimpleConfig = $config_factory;
    $this->logger = $logger_factory->get('content_access_simple');
    $this->moduleHandler = $module_handler;
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

    return $allNodesAccess;
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
        $label = ($role->id() == 'authenticated') ? $this->t('All logged in users') : $role->get('label');
        $roles[$role->id()] = $label;
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
    $config = $this->contentAccessSimpleConfig->get('content_access_simple.settings');
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
      $unpublished_message = $this->getUnpublishedMessage($node);
      $form['content_access_simple']['unpublish_message'] = [
        '#theme' => 'unpublished_message',
        '#sub_message' => $unpublished_message,
        '#weight' => -10,
      ];
    }

    $form['content_access_simple']['view'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Visibility'),
      '#description' => $config->get('help_text_view') ?? NULL,
      '#options' => $this->getRoles(),
      '#default_value' => $defaults['view'],
    ];

    // Runs Content Access disableCheckboxes as well as our disableRoles.
    $form['content_access_simple']['view']['#process'] = [
      [
        '\Drupal\Core\Render\Element\Checkboxes',
        'processCheckboxes',
      ],
      [
        '\Drupal\content_access\Form\ContentAccessRoleBasedFormTrait',
        'disableCheckboxes',
      ],
      [
        '\Drupal\content_access_simple\AccessManager',
        'disableRoles',
      ],
    ];

  }

  /**
   * Retrieves an unpublished message that is either dynamic or static.
   *
   * @param Drupal\node\Entity\Node $node
   *   The node object.
   *
   * @return string
   *   An unpublished message.
   */
  private function getUnpublishedMessage($node) {
    $unpublished_message = NULL;
    $config = $this->contentAccessSimpleConfig->get('content_access_simple.settings');
    if ($unpublished_message = $config->get('unpublished_message')) {
      return $unpublished_message;
    }
    else {
      // If we aren't using view unpublished module, exit early.
      if (!$this->moduleHandler->moduleExists('view_unpublished')) {
        return NULL;
      }

      $roles_unpublished_access = [];
      $bundle = $node->getType();
      $all_roles = Role::loadMultiple();
      foreach ($all_roles as $site_role) {
        if ($site_role->hasPermission("view any unpublished $bundle content") || $site_role->hasPermission("view any unpublished content")) {
          $roles_unpublished_access[] = $site_role->label();
        }
      }

      if ($roles_unpublished_access) {
        $dynamic_roles = implode(", ", $roles_unpublished_access);
        $dynamic_roles = Markup::create('<strong>' . $dynamic_roles . '</strong>');
        $unpublished_message = Markup::create($this->t(
          'Only the following roles can view unpublished pages of type @content_type: @dynamic_roles',
          [
            '@content_type' => $node->type->entity->label(),
            '@dynamic_roles' => $dynamic_roles,
          ]));
      }
    }

    return $unpublished_message;
  }

  /**
   * Checkboxes access for content.
   *
   * Formapi #process callback, that disables checkboxes for roles in
   * disabled_roles in config for this module.
   */
  public static function disableRoles(&$element, FormStateInterface $form_state, &$complete_form) {
    $config = \Drupal::service('config.factory')->get('content_access_simple.settings');
    $disabled_roles = $config->get('role_config.disabled_roles');
    $form_object = $form_state->getFormObject();
    if (!$disabled_roles || !$form_object instanceof NodeForm) {
      return $element;
    }

    $node = $form_object->getEntity();
    $per_node_settings = content_access_per_node_setting('view', $node);

    foreach (Element::children($element) as $key) {
      if (in_array($key, $disabled_roles)) {
        $element[$key]['#disabled'] = TRUE;
        $element[$key]['#default_value'] = !empty(array_intersect($per_node_settings, $disabled_roles));
        $element[$key]['#prefix'] = '<span ' . new Attribute([
          'title' => t("This role is disabled in the Content Access Simple configuration."),
        ]) . '>';
        $element[$key]['#suffix'] = "</span>";
      }
    }

    return $element;
  }

}
