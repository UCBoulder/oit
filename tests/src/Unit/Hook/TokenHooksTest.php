<?php

namespace Drupal\Tests\oit\Unit\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\oit\Hook\TokenHooks;
use Drupal\oit\Services\HeroImage;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the TokenHooks hook implementations.
 */
#[Group('oit')]
#[CoversClass(TokenHooks::class)]
#[CoversMethod(TokenHooks::class, 'tokenInfo')]
#[CoversMethod(TokenHooks::class, 'tokens')]
#[CoversMethod(TokenHooks::class, 'currentUserName')]
#[CoversMethod(TokenHooks::class, 'imageToken')]
#[CoversMethod(TokenHooks::class, 'dashboardImageFileId')]
#[CoversMethod(TokenHooks::class, 'defaultAlertImage')]
class TokenHooksTest extends DrupalUnitTestCase {

  /**
   * The known host used for the mocked request.
   */
  protected const HOST = 'https://oit.test';

  /**
   * The default alert image path, mirrored from the spec.
   */
  protected const DEFAULT_ALERT_IMAGE = '/sites/default/files/sa_images/sa_other.png';

  /**
   * The mocked current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * A real request stack carrying a request with a known host.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The mocked hero image service.
   *
   * @var \Drupal\oit\Services\HeroImage|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $heroImage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->heroImage = $this->createMock(HeroImage::class);

    $this->requestStack = new RequestStack();
    $request = Request::create(self::HOST . '/node/1');
    $this->requestStack->push($request);

    // BubbleableMetadata::addCacheContexts() validates contexts through the
    // cache_contexts_manager service.
    $cacheContextsManager = $this->createMock(CacheContextsManager::class);
    $cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cacheContextsManager);
    \Drupal::setContainer($container);
  }

  /**
   * Builds a TokenHooks instance.
   *
   * @return \Drupal\oit\Hook\TokenHooks
   *   A configured TokenHooks instance.
   */
  protected function createTokenHooks(): TokenHooks {
    $hooks = new TokenHooks(
      $this->currentUser,
      $this->entityTypeManager,
      $this->requestStack,
      $this->heroImage,
    );
    $hooks->setStringTranslation($this->getStringTranslationStub());
    return $hooks;
  }

  /**
   * Builds a partial mock with styledImageUrl() stubbed.
   *
   * @return \Drupal\oit\Hook\TokenHooks|\PHPUnit\Framework\MockObject\MockObject
   *   The partial mock.
   */
  protected function createTokenHooksWithStyledImageUrlStub() {
    $hooks = $this->getMockBuilder(TokenHooks::class)
      ->setConstructorArgs([
        $this->currentUser,
        $this->entityTypeManager,
        $this->requestStack,
        $this->heroImage,
      ])
      ->onlyMethods(['styledImageUrl'])
      ->getMock();
    $hooks->method('styledImageUrl')
      ->willReturnCallback(fn(int $fid, string $style): string => "styled:{$fid}:{$style}");
    $hooks->setStringTranslation($this->getStringTranslationStub());
    return $hooks;
  }

  /**
   * Builds a mocked field item list.
   *
   * @param bool $empty
   *   Whether the field reports as empty.
   * @param mixed $value
   *   The scalar value or target ID to expose.
   * @param string $key
   *   Either 'value' or 'target_id'.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked field item list.
   */
  protected function mockField(bool $empty, $value = NULL, string $key = 'value'): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn($empty);
    if (!$empty) {
      $field->method('getValue')->willReturn([[$key => $value]]);
      // FieldItemListInterface declares __get()/__set() as real interface
      // methods, so PHPUnit can stub __get() directly like any other method.
      $field->method('__get')->with($key)->willReturn($value);
    }
    return $field;
  }

  /**
   * Builds a mocked node.
   *
   * @param string $bundle
   *   The node bundle/type.
   * @param array $fields
   *   Map of field name to mocked FieldItemListInterface.
   *
   * @return \Drupal\node\NodeInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked node.
   */
  protected function mockNode(string $bundle, array $fields = []): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getType')->willReturn($bundle);
    $node->method('bundle')->willReturn($bundle);
    $node->method('hasField')->willReturnCallback(fn(string $name) => array_key_exists($name, $fields));
    $node->method('get')->willReturnCallback(function (string $name) use ($fields) {
      return $fields[$name] ?? NULL;
    });
    return $node;
  }

  /**
   * Tests tokenInfo() returns the oittoken type and no sa_title.
   */
  public function testTokenInfoStructure(): void {
    $hooks = $this->createTokenHooks();
    $info = $hooks->tokenInfo();

    $this->assertArrayHasKey('oittoken', $info['types']);
    $this->assertArrayHasKey('oittoken', $info['tokens']);
    $this->assertEqualsCanonicalizing(
      ['tweet_pic', 'og_image', 'who_i_is'],
      array_keys($info['tokens']['oittoken'])
    );
  }

  /**
   * Tests a non-oittoken type returns no replacements.
   */
  public function testTokensReturnsEmptyForOtherType(): void {
    $hooks = $this->createTokenHooks();
    $result = $hooks->tokens('node', ['[node:title]' => '[node:title]'], [], [], new BubbleableMetadata());
    $this->assertSame([], $result);
  }

  /**
   * Tests an unknown token name returns no replacements.
   */
  public function testTokensReturnsEmptyForUnknownToken(): void {
    $hooks = $this->createTokenHooks();
    $tokens = ['unknown' => '[oittoken:unknown]'];
    $result = $hooks->tokens('oittoken', $tokens, [], [], new BubbleableMetadata());
    $this->assertSame([], $result);
  }

  /**
   * Tests node tokens without a node in $data return no replacements.
   */
  public function testTokensReturnsEmptyForNodeTokenWithoutNode(): void {
    $hooks = $this->createTokenHooks();
    $tokens = ['tweet_pic' => '[oittoken:tweet_pic]'];
    $result = $hooks->tokens('oittoken', $tokens, [], [], new BubbleableMetadata());
    $this->assertSame([], $result);
  }

  /**
   * Tests several tokens in one call are all replaced.
   */
  public function testTokensReplacesMultipleTokensInOneCall(): void {
    $this->currentUser->method('isAnonymous')->willReturn(TRUE);
    $node = $this->mockNode('page', []);

    $hooks = $this->createTokenHooks();
    $tokens = [
      'who_i_is' => '[oittoken:who_i_is]',
      'tweet_pic' => '[oittoken:tweet_pic]',
      'og_image' => '[oittoken:og_image]',
    ];
    $result = $hooks->tokens('oittoken', $tokens, ['node' => $node], [], new BubbleableMetadata());

    $this->assertSame('', $result['[oittoken:who_i_is]']);
    $this->assertSame('', $result['[oittoken:tweet_pic]']);
    $this->assertSame('', $result['[oittoken:og_image]']);
  }

  /**
   * Tests anonymous users get an empty string without hitting user storage.
   */
  public function testWhoIisAnonymousNeverLoadsStorage(): void {
    $this->currentUser->method('isAnonymous')->willReturn(TRUE);
    $this->entityTypeManager->expects($this->never())->method('getStorage');

    $hooks = $this->createTokenHooks();
    $tokens = ['who_i_is' => '[oittoken:who_i_is]'];
    $metadata = new BubbleableMetadata();
    $result = $hooks->tokens('oittoken', $tokens, [], [], $metadata);

    $this->assertSame('', $result['[oittoken:who_i_is]']);
    $this->assertContains('user', $metadata->getCacheContexts());
  }

  /**
   * Tests who_i_is returns an empty string when the user fails to load.
   */
  public function testWhoIisUserDoesNotLoad(): void {
    $this->currentUser->method('isAnonymous')->willReturn(FALSE);
    $this->currentUser->method('id')->willReturn(7);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(7)->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $hooks = $this->createTokenHooks();
    $tokens = ['who_i_is' => '[oittoken:who_i_is]'];
    $result = $hooks->tokens('oittoken', $tokens, [], [], new BubbleableMetadata());

    $this->assertSame('', $result['[oittoken:who_i_is]']);
  }

  /**
   * Tests who_i_is returns an empty string when the user has no name field.
   */
  public function testWhoIisUserHasNoField(): void {
    $this->currentUser->method('isAnonymous')->willReturn(FALSE);
    $this->currentUser->method('id')->willReturn(7);

    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')->with('field_user_name')->willReturn(FALSE);
    $this->stubUserCacheability($user, 7);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(7)->willReturn($user);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $hooks = $this->createTokenHooks();
    $tokens = ['who_i_is' => '[oittoken:who_i_is]'];
    $result = $hooks->tokens('oittoken', $tokens, [], [], new BubbleableMetadata());

    $this->assertSame('', $result['[oittoken:who_i_is]']);
  }

  /**
   * Tests who_i_is returns an empty string when the name field is empty.
   */
  public function testWhoIisUserFieldEmpty(): void {
    $this->currentUser->method('isAnonymous')->willReturn(FALSE);
    $this->currentUser->method('id')->willReturn(7);

    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')->with('field_user_name')->willReturn(TRUE);
    $user->method('get')->with('field_user_name')->willReturn($this->mockField(TRUE));
    $this->stubUserCacheability($user, 7);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(7)->willReturn($user);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $hooks = $this->createTokenHooks();
    $tokens = ['who_i_is' => '[oittoken:who_i_is]'];
    $metadata = new BubbleableMetadata();
    $result = $hooks->tokens('oittoken', $tokens, [], [], $metadata);

    $this->assertSame('', $result['[oittoken:who_i_is]']);
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertContains('user:7', $metadata->getCacheTags());
  }

  /**
   * Tests who_i_is returns the user name HTML-escaped.
   */
  public function testWhoIisReturnsEscapedName(): void {
    $this->currentUser->method('isAnonymous')->willReturn(FALSE);
    $this->currentUser->method('id')->willReturn(7);

    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')->with('field_user_name')->willReturn(TRUE);
    $user->method('get')->with('field_user_name')->willReturn($this->mockField(FALSE, 'Ralphie <b>'));
    $this->stubUserCacheability($user, 7);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(7)->willReturn($user);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $hooks = $this->createTokenHooks();
    $tokens = ['who_i_is' => '[oittoken:who_i_is]'];
    $metadata = new BubbleableMetadata();
    $result = $hooks->tokens('oittoken', $tokens, [], [], $metadata);

    $this->assertSame('Ralphie &lt;b&gt;', $result['[oittoken:who_i_is]']);
    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertContains('user:7', $metadata->getCacheTags());
    $this->assertSame(Cache::PERMANENT, $metadata->getCacheMaxAge());
  }

  /**
   * Stubs the cacheability methods on a mocked user entity.
   *
   * @param \Drupal\user\UserInterface|\PHPUnit\Framework\MockObject\MockObject $user
   *   The mocked user.
   * @param int $uid
   *   The user ID used for the cache tag.
   */
  protected function stubUserCacheability($user, int $uid): void {
    $user->method('getCacheTags')->willReturn(['user:' . $uid]);
    $user->method('getCacheContexts')->willReturn([]);
    $user->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
  }

  /**
   * Data provider for the tweet_pic / og_image image tokens.
   *
   * @return array
   *   Sets of [token name, image style].
   */
  public static function imageTokenProvider(): array {
    return [
      'tweet_pic' => ['tweet_pic', 'large'],
      'og_image' => ['og_image', 'social_share'],
    ];
  }

  /**
   * Tests a bundle other than news or service_alert returns an empty string.
   */
  #[DataProvider('imageTokenProvider')]
  public function testImageTokenOtherBundleReturnsEmpty(string $tokenName, string $style): void {
    $node = $this->mockNode('page');

    $hooks = $this->createTokenHooksWithStyledImageUrlStub();
    $tokens = [$tokenName => "[oittoken:{$tokenName}]"];
    $result = $hooks->tokens('oittoken', $tokens, ['node' => $node], [], new BubbleableMetadata());

    $this->assertSame('', $result["[oittoken:{$tokenName}]"]);
  }

  /**
   * Tests a news node with no hero image returns an empty string.
   */
  #[DataProvider('imageTokenProvider')]
  public function testImageTokenNewsNoHeroImageReturnsEmpty(string $tokenName, string $style): void {
    $node = $this->mockNode('news');
    $this->heroImage->method('getFileId')->with($node)->willReturn(NULL);

    $hooks = $this->createTokenHooksWithStyledImageUrlStub();
    $tokens = [$tokenName => "[oittoken:{$tokenName}]"];
    $result = $hooks->tokens('oittoken', $tokens, ['node' => $node], [], new BubbleableMetadata());

    $this->assertSame('', $result["[oittoken:{$tokenName}]"]);
  }

  /**
   * Tests a news node with a hero image returns the styled URL.
   */
  #[DataProvider('imageTokenProvider')]
  public function testImageTokenNewsWithHeroImage(string $tokenName, string $style): void {
    $node = $this->mockNode('news');
    $this->heroImage->method('getFileId')->with($node)->willReturn(55);

    $hooks = $this->createTokenHooksWithStyledImageUrlStub();
    $tokens = [$tokenName => "[oittoken:{$tokenName}]"];
    $result = $hooks->tokens('oittoken', $tokens, ['node' => $node], [], new BubbleableMetadata());

    $this->assertSame("styled:55:{$style}", $result["[oittoken:{$tokenName}]"]);
  }

  /**
   * Tests a service_alert with an empty dashboard category returns empty.
   */
  #[DataProvider('imageTokenProvider')]
  public function testImageTokenServiceAlertEmptyDashCatReturnsEmpty(string $tokenName, string $style): void {
    $node = $this->mockNode('service_alert', [
      'field_service_alert_dash_cat' => $this->mockField(TRUE),
    ]);

    $hooks = $this->createTokenHooksWithStyledImageUrlStub();
    $tokens = [$tokenName => "[oittoken:{$tokenName}]"];
    $result = $hooks->tokens('oittoken', $tokens, ['node' => $node], [], new BubbleableMetadata());

    $this->assertSame('', $result["[oittoken:{$tokenName}]"]);
  }

  /**
   * Tests a service_alert whose term doesn't load returns the default image.
   */
  #[DataProvider('imageTokenProvider')]
  public function testImageTokenServiceAlertTermDoesNotLoad(string $tokenName, string $style): void {
    $node = $this->mockNode('service_alert', [
      'field_service_alert_dash_cat' => $this->mockField(FALSE, 12, 'target_id'),
    ]);

    $termStorage = $this->createMock(EntityStorageInterface::class);
    $termStorage->method('load')->with(12)->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->with('taxonomy_term')->willReturn($termStorage);

    $hooks = $this->createTokenHooksWithStyledImageUrlStub();
    $tokens = [$tokenName => "[oittoken:{$tokenName}]"];
    $result = $hooks->tokens('oittoken', $tokens, ['node' => $node], [], new BubbleableMetadata());

    $this->assertSame(self::HOST . self::DEFAULT_ALERT_IMAGE, $result["[oittoken:{$tokenName}]"]);
  }

  /**
   * Tests a service_alert whose term has no twitter image returns default.
   */
  #[DataProvider('imageTokenProvider')]
  public function testImageTokenServiceAlertTermNoTwitterImage(string $tokenName, string $style): void {
    $node = $this->mockNode('service_alert', [
      'field_service_alert_dash_cat' => $this->mockField(FALSE, 12, 'target_id'),
    ]);

    $term = $this->createMock(TermInterface::class);
    $term->method('get')->with('field_twitter_image')->willReturn($this->mockField(TRUE));

    $termStorage = $this->createMock(EntityStorageInterface::class);
    $termStorage->method('load')->with(12)->willReturn($term);
    $this->entityTypeManager->method('getStorage')->with('taxonomy_term')->willReturn($termStorage);

    $hooks = $this->createTokenHooksWithStyledImageUrlStub();
    $tokens = [$tokenName => "[oittoken:{$tokenName}]"];
    $result = $hooks->tokens('oittoken', $tokens, ['node' => $node], [], new BubbleableMetadata());

    $this->assertSame(self::HOST . self::DEFAULT_ALERT_IMAGE, $result["[oittoken:{$tokenName}]"]);
  }

  /**
   * Tests a service_alert whose term has an image returns the styled URL.
   *
   * For tweet_pic this is the regression test for 4.1: the term is loaded
   * by target_id, not by the wrong 'value' key.
   */
  #[DataProvider('imageTokenProvider')]
  public function testImageTokenServiceAlertTermWithImage(string $tokenName, string $style): void {
    $node = $this->mockNode('service_alert', [
      'field_service_alert_dash_cat' => $this->mockField(FALSE, 12, 'target_id'),
    ]);

    $term = $this->createMock(TermInterface::class);
    $term->method('get')->with('field_twitter_image')->willReturn($this->mockField(FALSE, 77, 'target_id'));

    $termStorage = $this->createMock(EntityStorageInterface::class);
    $termStorage->method('load')->with(12)->willReturn($term);
    $this->entityTypeManager->method('getStorage')->with('taxonomy_term')->willReturn($termStorage);

    $hooks = $this->createTokenHooksWithStyledImageUrlStub();
    $tokens = [$tokenName => "[oittoken:{$tokenName}]"];
    $result = $hooks->tokens('oittoken', $tokens, ['node' => $node], [], new BubbleableMetadata());

    $this->assertSame("styled:77:{$style}", $result["[oittoken:{$tokenName}]"]);
  }

}
