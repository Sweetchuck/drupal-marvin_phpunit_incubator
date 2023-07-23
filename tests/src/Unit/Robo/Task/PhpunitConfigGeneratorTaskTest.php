<?php

declare(strict_types=1);

namespace Drupal\Tests\marvin_phpunit_incubator\Unit\Robo\Task;

use Drupal\marvin_phpunit_incubator\Robo\Task\PhpunitConfigGenTask;
use Drupal\Tests\marvin_phpunit_incubator\Unit\TestBase;
use org\bovigo\vfs\vfsStream;
use Robo\Robo;
use Sweetchuck\EnvVarStorage\ArrayStorage;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class PhpunitConfigGeneratorTaskTest extends TestBase {

  /**
   * @phpstan-return array<string, mixed>
   */
  public function casesRunSuccess(): array {
    $fixturesDir = $this->getFixturesDir();
    $casesDir = "$fixturesDir/cases/PhpunitConfigGen";

    $files = (new Finder())
      ->in($casesDir)
      ->files()
      ->name('*.expected.txt');

    $cases = [];
    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    foreach ($files as $file) {
      $id = $file->getBasename('.expected.txt');
      $cases[$id] = [
        [
          'phpunitConfig' => file_get_contents($file->getPathname()),
        ],
        Yaml::parseFile($file->getPath() . "/$id.input.yml"),
      ];
    }

    return $cases;
  }

  /**
   * @phpstan-param array<string, mixed> $expected
   * @phpstan-param array<string, mixed> $options
   *
   * @dataProvider casesRunSuccess
   */
  public function testRunSuccessString(array $expected, array $args): void {
    /** @var \League\Container\Container $container */
    $container = Robo::createContainer();
    $logger = new BufferingLogger();
    $container->add('logger', $logger);
    Robo::setContainer($container);

    $args['options']['envVarStorage'] = new ArrayStorage(new \ArrayObject($args['envVars'] ?? []));

    $vfsRootDirName = $this->getName(FALSE) . '.' . $this->dataName();
    $vfs = vfsStream::setup($vfsRootDirName, NULL, $args['vfsStructure'] ?? []);
    $args['options']['cwd'] = $vfs->url() . '/' . $args['options']['cwd'];

    $task = new PhpunitConfigGenTask();
    $task->setLogger($logger);
    $task->setOptions($args['options']);
    $result = $task->run();

    $xml = new \DOMDocument();
    $xml->formatOutput = TRUE;
    $xml->preserveWhiteSpace = TRUE;
    $xml->loadXML($expected['phpunitConfig']);

    static::assertSame($xml->saveXML(), $result['phpunitConfig']);
  }

}
