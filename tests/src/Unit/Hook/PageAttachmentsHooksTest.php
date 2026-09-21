<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\oit\Hook\PageAttachmentsHooks;
use Drupal\oit\Plugin\Domain;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the PageAttachmentsHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(PageAttachmentsHooks::class)]
#[CoversMethod(PageAttachmentsHooks::class, '__construct')]
#[CoversMethod(PageAttachmentsHooks::class, 'pageAttachmentsAlter')]
class PageAttachmentsHooksTest extends DrupalUnitTestCase {

  /**
   * The mocked route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $routeMatch;

  /**
   * The mocked domain service.
   *
   * @var \Drupal\oit\Plugin\Domain|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $domain;

  /**
   * The mocked current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The original Pantheon environment value.
   *
   * @var string|false
   */
  protected $originalPantheonEnvironment;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->domain = $this->getMockBuilder(Domain::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->originalPantheonEnvironment = getenv('PANTHEON_ENVIRONMENT');
    putenv('PANTHEON_ENVIRONMENT');
  }

  /**
   * Tests downloads libraries are attached for the downloads node.
   */
  public function testPageAttachmentsAlterAttachesDownloadsLibrariesForDownloadsNode(): void {
    putenv('PANTHEON_ENVIRONMENT');
    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->once())
      ->method('id')
      ->willReturn(262);
    $node->expects($this->once())
      ->method('getType')
      ->willReturn('page');
    $this->routeMatch->expects($this->once())
      ->method('getParameter')
      ->with('node')
      ->willReturn($node);
    $this->domain->expects($this->once())
      ->method('getDomain')
      ->willReturn('cu');
    $this->currentUser->expects($this->never())
      ->method('getRoles');

    $attachments = [];
    $hooks = new PageAttachmentsHooks($this->routeMatch, $this->domain, $this->currentUser);
    $hooks->pageAttachmentsAlter($attachments);

    $this->assertContains('oit/listjs', $attachments['#attached']['library']);
    $this->assertContains('oit/downloads_search', $attachments['#attached']['library']);
  }

  /**
   * Tests tutorial library is attached for tutorial nodes only.
   */
  public function testPageAttachmentsAlterAttachesTutorialLibraryForTutorialNode(): void {
    putenv('PANTHEON_ENVIRONMENT');
    $node = $this->createMock(NodeInterface::class);
    $node->expects($this->once())
      ->method('id')
      ->willReturn(999);
    $node->expects($this->once())
      ->method('getType')
      ->willReturn('tutorial');
    $this->routeMatch->expects($this->once())
      ->method('getParameter')
      ->with('node')
      ->willReturn($node);
    $this->domain->expects($this->once())
      ->method('getDomain')
      ->willReturn('cu');
    $this->currentUser->expects($this->never())
      ->method('getRoles');

    $attachments = [];
    $hooks = new PageAttachmentsHooks($this->routeMatch, $this->domain, $this->currentUser);
    $hooks->pageAttachmentsAlter($attachments);

    $this->assertContains('dingo/tutorial', $attachments['#attached']['library']);
    $this->assertNotContains('oit/listjs', $attachments['#attached']['library']);
    $this->assertNotContains('oit/downloads_search', $attachments['#attached']['library']);
  }

  /**
   * Tests string node parameters take the downloads branch only.
   */
  public function testPageAttachmentsAlterAttachesDownloadsLibrariesForStringNodeParameter(): void {
    putenv('PANTHEON_ENVIRONMENT');
    $this->routeMatch->expects($this->once())
      ->method('getParameter')
      ->with('node')
      ->willReturn('262');
    $this->domain->expects($this->once())
      ->method('getDomain')
      ->willReturn('cu');
    $this->currentUser->expects($this->never())
      ->method('getRoles');

    $attachments = [];
    $hooks = new PageAttachmentsHooks($this->routeMatch, $this->domain, $this->currentUser);
    $hooks->pageAttachmentsAlter($attachments);

    $this->assertContains('oit/listjs', $attachments['#attached']['library']);
    $this->assertContains('oit/downloads_search', $attachments['#attached']['library']);
    $this->assertNotContains('dingo/tutorial', $attachments['#attached']['library']);
  }

  /**
   * Tests no libraries are attached when no node is on the route.
   */
  public function testPageAttachmentsAlterSkipsNodeLibrariesWhenRouteHasNoNode(): void {
    putenv('PANTHEON_ENVIRONMENT');
    $this->routeMatch->expects($this->once())
      ->method('getParameter')
      ->with('node')
      ->willReturn(NULL);
    $this->domain->expects($this->once())
      ->method('getDomain')
      ->willReturn('cu');
    $this->currentUser->expects($this->never())
      ->method('getRoles');

    $attachments = [];
    $hooks = new PageAttachmentsHooks($this->routeMatch, $this->domain, $this->currentUser);
    $hooks->pageAttachmentsAlter($attachments);

    $this->assertArrayNotHasKey('#attached', $attachments);
  }

  /**
   * Tests Google verification and chatbot are attached on the OIT domain.
   */
  public function testPageAttachmentsAlterAttachesGoogleVerificationAndChatbotForOit(): void {
    putenv('PANTHEON_ENVIRONMENT');
    $this->routeMatch->expects($this->once())
      ->method('getParameter')
      ->with('node')
      ->willReturn(NULL);
    $this->domain->expects($this->once())
      ->method('getDomain')
      ->willReturn('oit');
    $this->currentUser->expects($this->once())
      ->method('getRoles')
      ->willReturn(['authenticated']);

    $attachments = [];
    $hooks = new PageAttachmentsHooks($this->routeMatch, $this->domain, $this->currentUser);
    $hooks->pageAttachmentsAlter($attachments);

    $this->assertArrayHasKey('#attached', $attachments);
    $this->assertArrayHasKey('html_head', $attachments['#attached']);
    $html_head = $attachments['#attached']['html_head'];

    $google_meta = $this->getHtmlHeadAttachmentByKey($html_head, 'google-site-verification');
    $this->assertSame('meta', $google_meta['#tag']);
    $this->assertSame('google-site-verification', $google_meta['#attributes']['name']);

    $chatbot_config = $this->getHtmlHeadAttachmentByKey($html_head, 'gk-chatbot-widget-config');
    $this->assertStringContainsString('window.gkCBWConfig = ', $chatbot_config['#value']);
    $prefix = 'window.gkCBWConfig = ';
    $json = substr($chatbot_config['#value'], strlen($prefix), -1);
    $decoded = json_decode($json, TRUE);
    $this->assertIsArray($decoded);
    $this->assertArrayHasKey('aiBotId', $decoded);
    $this->assertArrayHasKey('apiKey', $decoded);

    $chatbot_widget = $this->getHtmlHeadAttachmentByKey($html_head, 'gk-chatbot-widget');
    $this->assertSame('script', $chatbot_widget['#tag']);
    $this->assertSame('module', $chatbot_widget['#attributes']['type']);
  }

  /**
   * Tests administrators do not get the chatbot widget.
   */
  public function testPageAttachmentsAlterSkipsChatbotForAdministrator(): void {
    putenv('PANTHEON_ENVIRONMENT');
    $this->routeMatch->expects($this->once())
      ->method('getParameter')
      ->with('node')
      ->willReturn(NULL);
    $this->domain->expects($this->once())
      ->method('getDomain')
      ->willReturn('oit');
    $this->currentUser->expects($this->once())
      ->method('getRoles')
      ->willReturn(['authenticated', 'administrator']);

    $attachments = [];
    $hooks = new PageAttachmentsHooks($this->routeMatch, $this->domain, $this->currentUser);
    $hooks->pageAttachmentsAlter($attachments);

    $html_head = $attachments['#attached']['html_head'];
    $this->assertNotNull($this->getHtmlHeadAttachmentByKey($html_head, 'google-site-verification'));
    $this->assertNull($this->getHtmlHeadAttachmentByKey($html_head, 'gk-chatbot-widget-config'));
    $this->assertNull($this->getHtmlHeadAttachmentByKey($html_head, 'gk-chatbot-widget'));
  }

  /**
   * Tests local environments do not get the chatbot widget.
   */
  public function testPageAttachmentsAlterSkipsChatbotForLocalEnvironment(): void {
    putenv('PANTHEON_ENVIRONMENT=local');
    $this->routeMatch->expects($this->once())
      ->method('getParameter')
      ->with('node')
      ->willReturn(NULL);
    $this->domain->expects($this->once())
      ->method('getDomain')
      ->willReturn('oit');
    $this->currentUser->expects($this->once())
      ->method('getRoles')
      ->willReturn(['authenticated']);

    $attachments = [];
    $hooks = new PageAttachmentsHooks($this->routeMatch, $this->domain, $this->currentUser);
    $hooks->pageAttachmentsAlter($attachments);

    $html_head = $attachments['#attached']['html_head'];
    $this->assertNotNull($this->getHtmlHeadAttachmentByKey($html_head, 'google-site-verification'));
    $this->assertNull($this->getHtmlHeadAttachmentByKey($html_head, 'gk-chatbot-widget-config'));
    $this->assertNull($this->getHtmlHeadAttachmentByKey($html_head, 'gk-chatbot-widget'));
  }

  /**
   * Tests non-OIT domains do not get OIT head attachments.
   */
  public function testPageAttachmentsAlterSkipsHtmlHeadAttachmentsForOtherDomains(): void {
    putenv('PANTHEON_ENVIRONMENT');
    $this->routeMatch->expects($this->once())
      ->method('getParameter')
      ->with('node')
      ->willReturn(NULL);
    $this->domain->expects($this->once())
      ->method('getDomain')
      ->willReturn('cu');
    $this->currentUser->expects($this->never())
      ->method('getRoles');

    $attachments = [];
    $hooks = new PageAttachmentsHooks($this->routeMatch, $this->domain, $this->currentUser);
    $hooks->pageAttachmentsAlter($attachments);

    $this->assertArrayNotHasKey('#attached', $attachments);
  }

  /**
   * Gets an HTML head attachment by key.
   *
   * @param array $html_head
   *   The html_head attachments.
   * @param string $key
   *   The attachment key.
   *
   * @return array|null
   *   The matching attachment render array, or NULL if none was found.
   */
  protected function getHtmlHeadAttachmentByKey(array $html_head, string $key): ?array {
    foreach ($html_head as $attachment) {
      if ($attachment[1] === $key) {
        return $attachment[0];
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->originalPantheonEnvironment === FALSE) {
      putenv('PANTHEON_ENVIRONMENT');
    }
    else {
      putenv('PANTHEON_ENVIRONMENT=' . $this->originalPantheonEnvironment);
    }
    parent::tearDown();
  }

}
