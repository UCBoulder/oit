<?php

namespace Drupal\Tests\oit\Unit\Services;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\oit\Services\HeroImage;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the HeroImage service.
 */
#[Group('oit')]
#[CoversClass(HeroImage::class)]
#[CoversMethod(HeroImage::class, 'getFileId')]
class HeroImageTest extends DrupalUnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The service under test.
   *
   * @var \Drupal\oit\Services\HeroImage
   */
  protected $heroImage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->heroImage = new HeroImage($this->entityTypeManager);
  }

  /**
   * Builds a mocked field item list.
   *
   * @param bool $empty
   *   Whether the field should report as empty.
   * @param int|null $targetId
   *   The target ID the field's first value should carry.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked field item list.
   */
  protected function mockField(bool $empty, ?int $targetId = NULL): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn($empty);
    if (!$empty) {
      $field->method('getValue')->willReturn([['target_id' => $targetId]]);
    }
    return $field;
  }

  /**
   * Tests a node without the hero image field returns NULL.
   */
  public function testNoHeroFieldReturnsNull(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_media_hero_image')->willReturn(FALSE);
    $node->expects($this->never())->method('get');

    $this->assertNull($this->heroImage->getFileId($node));
  }

  /**
   * Tests an empty hero image field returns NULL.
   */
  public function testEmptyHeroFieldReturnsNull(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_media_hero_image')->willReturn(TRUE);
    $node->method('get')->with('field_media_hero_image')->willReturn($this->mockField(TRUE));
    $this->entityTypeManager->expects($this->never())->method('getStorage');

    $this->assertNull($this->heroImage->getFileId($node));
  }

  /**
   * Tests a media entity that fails to load returns NULL.
   */
  public function testMediaDoesNotLoadReturnsNull(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_media_hero_image')->willReturn(TRUE);
    $node->method('get')->with('field_media_hero_image')->willReturn($this->mockField(FALSE, 42));

    $mediaStorage = $this->createMock(EntityStorageInterface::class);
    $mediaStorage->method('load')->with(42)->willReturn(NULL);
    $this->entityTypeManager->method('getStorage')->with('media')->willReturn($mediaStorage);

    $this->assertNull($this->heroImage->getFileId($node));
  }

  /**
   * Tests an empty media image field returns NULL.
   */
  public function testEmptyMediaImageFieldReturnsNull(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_media_hero_image')->willReturn(TRUE);
    $node->method('get')->with('field_media_hero_image')->willReturn($this->mockField(FALSE, 42));

    $media = $this->createMock(MediaInterface::class);
    $media->method('get')->with('field_media_image_2')->willReturn($this->mockField(TRUE));

    $mediaStorage = $this->createMock(EntityStorageInterface::class);
    $mediaStorage->method('load')->with(42)->willReturn($media);
    $this->entityTypeManager->method('getStorage')->with('media')->willReturn($mediaStorage);

    $this->assertNull($this->heroImage->getFileId($node));
  }

  /**
   * Tests the happy path returns the file ID from the media image field.
   */
  public function testHappyPathReturnsFileId(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_media_hero_image')->willReturn(TRUE);
    $node->method('get')->with('field_media_hero_image')->willReturn($this->mockField(FALSE, 42));

    $media = $this->createMock(MediaInterface::class);
    $media->method('get')->with('field_media_image_2')->willReturn($this->mockField(FALSE, 99));

    $mediaStorage = $this->createMock(EntityStorageInterface::class);
    $mediaStorage->method('load')->with(42)->willReturn($media);
    $this->entityTypeManager->method('getStorage')->with('media')->willReturn($mediaStorage);

    $this->assertSame(99, $this->heroImage->getFileId($node));
  }

}
