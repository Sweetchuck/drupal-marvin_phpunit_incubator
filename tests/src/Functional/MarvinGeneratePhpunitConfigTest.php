<?php

declare(strict_types=1);

namespace Drupal\Tests\marvin_phpunit_incubator\Functional;

use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MarvinGeneratePhpunitConfigTest extends TestCase {

  use DrushTestTrait;

  public static function casesExecuteSuccess(): array {
    return [
      'default' => [
        'expected' => [
          'stdOutput' => '',
          'stdError' => " [notice] Pipeline ID: marvin:phpunit:config:generate\n",
        ],
        'arguments' => [],
        'options' => [],
      ],
    ];
  }

  #[Test]
  #[DataProvider('casesExecuteSuccess')]
  public function testExecuteSuccess(
    array $expected,
    array $arguments,
    array $options,
  ): void {
    $this->drush(
      'marvin:phpunit:config:generate',
      $arguments,
      $options,
      NULL,
      NULL,
      $expected['exitCode'] ?? 0,
    );

    if (array_key_exists('stdOutput', $expected)) {
      $actualStdOutput = $this->getOutputRaw();
      static::assertSame($expected['stdOutput'], $actualStdOutput);
    }

    if (array_key_exists('stdError', $expected)) {
      $actualStdError = $this->getErrorOutputRaw();
      static::assertStringContainsString($expected['stdError'], $actualStdError);
    }
  }

}
