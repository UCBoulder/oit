<?php

namespace Drupal\Tests\oit\Unit\Plugin;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\block_content\BlockContentInterface;
use Drupal\oit\Plugin\BlockUuidQuery;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the BlockUuidQuery plugin.
 */
#[Group('oit')]
#[CoversClass(BlockUuidQuery::class)]
class BlockUuidQueryTest extends UnitTestCase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The subject under test.
   *
   * @var \Drupal\oit\Plugin\BlockUuidQuery
   */
  protected $blockUuidQuery;

  /**
   * The test UUID.
   *
   * @var string
   */
  protected $uuid = 'bb686d55-fe0c-41ef-8dd4-0257b0a7256a';

  /**
   * The mock block ID.
   *
   * @var int
   */
  protected $blockId = 123;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the database connection.
    $this->connection = $this->createMock(Connection::class);

    // Mock the entity type manager.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Create the BlockUuidQuery instance.
    $this->blockUuidQuery = new BlockUuidQuery($this->connection, $this->entityTypeManager);
  }

  /**
   * Tests the getBidByUuid and loadBlock methods.
   */
  public function testGetBidByUuidAndLoadBlock(): void {
    // Mock the query builder.
    $query = $this->getMockBuilder('\Drupal\Core\Database\Query\Select')
      ->disableOriginalConstructor()
      ->getMock();
    $query->expects($this->once())
      ->method('fields')
      ->with('bc', ['id'])
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('bc.uuid', $this->uuid)
      ->willReturnSelf();

    // Mock the statement.
    $statement = $this->createMock(StatementInterface::class);
    $resultRow = new \stdClass();
    $resultRow->id = $this->blockId;
    $statement->expects($this->once())
      ->method('fetch')
      ->willReturn($resultRow);

    $query->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->connection->expects($this->once())
      ->method('select')
      ->with('block_content', 'bc')
      ->willReturn($query);

    // Mock the block storage and view builder.
    $blockStorage = $this->createMock(EntityStorageInterface::class);
    $viewBuilder = $this->createMock(EntityViewBuilderInterface::class);
    $block = $this->createMock(BlockContentInterface::class);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('block_content')
      ->willReturn($blockStorage);

    $blockStorage->expects($this->once())
      ->method('load')
      ->with($this->blockId)
      ->willReturn($block);

    $this->entityTypeManager->expects($this->once())
      ->method('getViewBuilder')
      ->with('block_content')
      ->willReturn($viewBuilder);

    $renderedBlock = ['#markup' => 'Rendered block content'];
    $viewBuilder->expects($this->once())
      ->method('view')
      ->with($block)
      ->willReturn($renderedBlock);

    // Test the methods.
    $this->blockUuidQuery->getBidByUuid($this->uuid);
    $result = $this->blockUuidQuery->loadBlock();

    // Assert the result is the rendered block.
    $this->assertSame($renderedBlock, $result);
  }

}
