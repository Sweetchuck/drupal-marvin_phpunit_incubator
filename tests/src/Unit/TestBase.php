<?php

declare(strict_types=1);

namespace Drupal\Tests\marvin_phpunit_incubator\Unit;

use PHPUnit\Framework\TestCase;

class TestBase extends TestCase {

  protected function getFixturesDir(): string {
    return dirname(__DIR__, 2) . '/fixtures';
  }

}
