<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_phpunit_incubator\Unit\Robo\Task;

use Drupal\marvin_phpunit_incubator\Robo\Task\PhpunitConfigGeneratorTask;
use Drupal\marvin_incubator\Utils as MarvinIncubatorUtils;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Robo\Robo;
use Symfony\Component\Yaml\Yaml;

class PhpunitConfigGeneratorTaskTest extends TestCase {

  public function casesRunSuccess(): array {
    $rootDir = MarvinIncubatorUtils::marvinIncubatorDir();
    $casesDir = "$rootDir/tests/fixtures/cases/PhpunitConfigGenerator";

    return [
      'basic' => [
        [
          'phpunitConfig' => file_get_contents("$casesDir/basic.expected.xml"),
        ],
        Yaml::parseFile("$casesDir/basic.input.yml"),
      ],
    ];
  }

  /**
   * @dataProvider casesRunSuccess
   */
  public function testRunSuccessString(array $expected, array $options): void {
    $vfs = vfsStream::setup(__FUNCTION__);
    $vfsUrl = $vfs->url();

    $options['outputDestination'] = "$vfsUrl/a/b/phpunit.xml";
    $container = Robo::createDefaultContainer();

    Robo::setContainer($container);

    $task = new PhpunitConfigGeneratorTask();
    $task->setLogger($container->get('logger'));
    $task->setOptions($options);
    $result = $task->run();

    $this->assertSame($expected['phpunitConfig'], $result['phpunitConfig']);
    $this->assertSame($expected['phpunitConfig'], file_get_contents($options['outputDestination']));
  }

}
