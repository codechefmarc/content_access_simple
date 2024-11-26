<?php

namespace Drupal\Tests\content_access_simple\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_access\Functional\ContentAccessTestHelperTrait;
use Drupal\User\Entity\Role;

/**
 * Tests for the Content Access Simple module based on Content Access.
 */
class ContentAccessSimpleTest extends BrowserTestBase {
  use ContentAccessTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_access',
    'content_access_simple',
  ];

  /**
   * A user with permission for viewing nodes.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $specificRoleUser;

  /**
   * A user with permission to access content_access_simple.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $contentEditorUser;

  /**
   * A user with administrator privileges.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * A custom role ID to reference.
   *
   * @var string
   */
  protected $customRoleId = 'contributor';

  /**
   * Content type for test.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $contentType;

  /**
   * Node object to perform test.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node1;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * English title for nodes.
   *
   * @var string
   */
  protected string $englishTitle = 'English node';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a custom role to be used for testing access.
    $role = Role::create([
      'id' => $this->customRoleId,
      'label' => ucfirst($this->customRoleId),
    ]);
    $role->save();

    // Create test content type.
    $this->contentType = $this->drupalCreateContentType();

    // Create test user with separate role.
    $this->specificRoleUser = $this->drupalCreateUser();
    $this->specificRoleUser->addRole($this->customRoleId);
    $this->specificRoleUser->save();

    // Create content editor user.
    $this->contentEditorUser = $this->drupalCreateUser([
      'access content',
      'access administration pages',
      'grant content access simple',
      'edit any ' . $this->contentType->id() . ' content',
    ]);

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'grant content access',
      'grant own content access',
      'bypass node access',
      'access administration pages',
    ]);

    // Login as content editor.
    $this->drupalLogin($this->adminUser);

    // Rebuild content access permissions.
    node_access_rebuild();

    // Create test nodes.
    $this->node1 = $this->drupalCreateNode([
      'type' => $this->contentType->id(),
      'language' => 'en',
      'status' => 1,
      'title' => $this->englishTitle,
    ]);

    // Enable per node access.
    $this->changeAccessPerNode();
    $this->drupalLogout();
  }

  /**
   * Test to see that our form element is on the node edit page.
   */
  public function testSeeFormElement() {
    $this->drupalLogin($this->contentEditorUser);
    $this->drupalGet('node/' . $this->node1->id() . '/edit');
    $this->assertSession()->pageTextContains('Access and Permissions');
  }

  /**
   * Test to change permissions and test access via our form.
   */
  public function testViewAccess() {
    // Remove anonymous and authenticated from accessing content type.
    $this->drupalLogin($this->adminUser);
    $accessPermissions = [
      'view[anonymous]' => FALSE,
      'view[authenticated]' => FALSE,
    ];
    $this->changeAccessContentType($accessPermissions);
    $this->drupalLogout();

    // Add our custom role to view access.
    $this->drupalLogin($this->contentEditorUser);
    $this->drupalGet('node/' . $this->node1->id() . '/edit');
    $formEdit = [
      "view[$this->customRoleId]" => TRUE,
    ];
    $this->submitForm($formEdit, 'Save');
    $this->drupalLogout();

    // Login as our specific role.
    $this->drupalLogin($this->specificRoleUser);
    $this->drupalGet('node/' . $this->node1->id());
    $this->assertSession()->pageTextContains($this->node1->getTitle());
  }

  /**
   * Test hidden roles.
   */
  public function testHiddenRoles() {
    // Set the config for a hidden role.
    $config = \Drupal::service('config.factory')->getEditable('content_access_simple.settings');
    $config->set('role_config.hidden_roles', [$this->customRoleId])->save();

    // Login as content editor and test if that role is missing from the list.
    $this->drupalLogin($this->contentEditorUser);
    $this->drupalGet('node/' . $this->node1->id() . '/edit');
    $this->assertSession()->pageTextNotContains(ucfirst($this->customRoleId));
  }

  /**
   * Test disabled roles.
   */
  public function testDisabledRoles() {
    // Set the config for a disabled role.
    $config = \Drupal::service('config.factory')->getEditable('content_access_simple.settings');
    $config->set('role_config.disabled_roles', [$this->customRoleId])->save();

    // Login as content editor and test if tha role is disabled in the list.
    $this->drupalLogin($this->contentEditorUser);
    $this->drupalGet('node/' . $this->node1->id() . '/edit');
    $page = $this->getSession()->getPage();
    $checkbox = $page->find('css', 'input[name="view[' . $this->customRoleId . ']"]');
    $this->assertTrue($checkbox->hasAttribute('disabled'), 'The checkbox for the disabled role is correctly disabled.');
  }

  /**
   * Test complex permissions set.
   */
  public function testComplexPermissions() {
    // Remove content type permissions for view_own.
    $this->drupalLogin($this->adminUser);
    $accessPermissions = [
      'view_own[authenticated]' => FALSE,
    ];
    $this->changeAccessContentType($accessPermissions);
    $this->drupalLogout();

    // Set specific node view_own permissions.
    $settings = [
      'view_own' => [
        'authenticated',
      ],
    ];
    content_access_save_per_node_settings($this->node1, $settings);

    $this->drupalLogin($this->contentEditorUser);
    $this->drupalGet('node/' . $this->node1->id() . '/edit');
    $this->assertSession()->pageTextContains('Complex permissions set');
  }

}
