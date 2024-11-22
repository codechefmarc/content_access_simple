<?php

namespace Drupal\Tests\content_access_simple\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_access\Functional\ContentAccessTestHelperTrait;

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
   * A user with permission to non administer.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $testUser;

  /**
   * A user with permission to administer.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

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
   * Node object to perform test.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node2;

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

    // Create test user with separate role.
    $this->testUser = $this->drupalCreateUser();

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'grant content access',
      'grant own content access',
      'bypass node access',
      'access administration pages',
      'grant content access simple',
    ]);
    $this->drupalLogin($this->adminUser);

    // Rebuild content access permissions.
    node_access_rebuild();

    // Create test content type.
    $this->contentType = $this->drupalCreateContentType();

    // Create test nodes.
    $this->node1 = $this->drupalCreateNode([
      'type' => $this->contentType->id(),
      'language' => 'en',
      'status' => 1,
      'title' => $this->englishTitle,
    ]);
    $this->node2 = $this->drupalCreateNode([
      'type' => $this->contentType->id(),
      'language' => 'en',
      'status' => 1,
      'title' => $this->englishTitle,
    ]);

    // Login admin and enable per node access.
    $this->drupalLogin($this->adminUser);
    $this->changeAccessPerNode();
    $this->drupalLogout();

  }

  /**
   * Test to see that our form element is on the node edit page.
   */
  public function testSeeFormElement() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/' . $this->node1->id() . '/edit');
    $this->assertSession()->pageTextContains('Access and Permissions');
  }

}
