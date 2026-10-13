<?php

namespace Drupal\Tests\oit\Unit\Plugin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\oit\Plugin\CvsToArray;
use Drupal\Tests\UnitTestCase as DrupalUnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CvsToArray.
 */
#[Group('oit')]
#[CoversClass(CvsToArray::class)]
class CvsToArrayTest extends DrupalUnitTestCase {

  /**
   * Mock handler queue backing the HTTP client.
   *
   * @var \GuzzleHttp\Handler\MockHandler
   */
  protected $mockHandler;

  /**
   * Recorded HTTP transactions.
   *
   * @var array
   */
  protected $history = [];

  /**
   * Fake Teams alert service recording sent messages.
   *
   * @var object
   */
  protected $teams;

  /**
   * The mocked logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->mockHandler = new MockHandler();
    $stack = HandlerStack::create($this->mockHandler);
    $stack->push(Middleware::history($this->history));
    $client = new Client(['handler' => $stack]);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->teams = new class() {

      /**
       * Sent messages.
       *
       * @var array
       */
      public array $messages = [];

      /**
       * Records a message.
       */
      public function sendMessage(string $message): void {
        $this->messages[] = $message;
      }

    };
    $factory = new class($this->logger) {

      /**
       * Constructs the factory.
       */
      public function __construct(private LoggerInterface $logger) {}

      /**
       * Returns the logger.
       */
      public function get(string $channel): LoggerInterface {
        return $this->logger;
      }

    };
    $container = new ContainerBuilder();
    $container->set('http_client', $client);
    $container->set('logger.factory', $factory);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('oit.teamsalert', $this->teams);
    \Drupal::setContainer($container);
  }

  /**
   * Tests parsing including a quoted multi-line cell and a quoted comma.
   */
  public function testParsesCsvWithMultilineQuotedCell(): void {
    $csv = "a,b,c\n1,\"line one\nline two\",\"x,y\"\n";
    $this->mockHandler->append(new Response(200, [], $csv));

    $result = (new CvsToArray('https://docs.google.com/x', ','))->getBuiltArray();

    $this->assertSame([
      ['a', 'b', 'c'],
      ['1', "line one\nline two", 'x,y'],
    ], $result);
    $this->assertCount(1, $this->history);
    $this->assertSame([], $this->teams->messages);
  }

  /**
   * Tests a transport failure yields an empty array and is reported.
   */
  public function testFailureReportsAndReturnsEmptyArray(): void {
    $this->mockHandler->append(
      new ConnectException('boom', new Request('GET', 'https://docs.google.com/x'))
    );
    $this->logger->expects($this->once())
      ->method('warning')
      ->with($this->anything(), $this->callback(
        fn(array $context) => str_contains($context['@reason'], 'boom')
      ));

    $result = (new CvsToArray('https://docs.google.com/x', ','))->getBuiltArray();

    $this->assertSame([], $result);
    $this->assertCount(1, $this->teams->messages);
  }

  /**
   * Tests an HTTP error status is treated as a failure.
   */
  public function testHttpErrorStatusReportsAndReturnsEmptyArray(): void {
    $this->mockHandler->append(new Response(404, [], 'secret body'));

    $result = (new CvsToArray('https://docs.google.com/x', ','))->getBuiltArray();

    $this->assertSame([], $result);
    $this->assertCount(1, $this->teams->messages);
    $this->assertStringContainsString('(HTTP 404 Not Found)', $this->teams->messages[0]);
    $this->assertStringNotContainsString('secret body', $this->teams->messages[0]);
  }

  /**
   * Tests a NULL URL skips Guzzle and is reported without a TypeError.
   */
  public function testNullFileSkipsHttpAndReports(): void {
    $result = (new CvsToArray(NULL, ','))->getBuiltArray();

    $this->assertSame([], $result);
    $this->assertCount(0, $this->history);
    $this->assertCount(1, $this->teams->messages);
    $this->assertStringContainsString('No URL provided.', $this->teams->messages[0]);
  }

}
