<?php

declare(strict_types=1);

namespace Drupal\Tests\marvin_phpunit_incubator\Unit;

use Drupal\marvin_phpunit_incubator\PhpunitConfigGen;
use org\bovigo\vfs\vfsStream;
use Sweetchuck\EnvVarStorage\ArrayStorage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * @covers \Drupal\marvin_phpunit_incubator\PhpunitConfigGen
 */
class PhpunitConfigGenTest extends TestBase {

  /**
   * @phpstan-return array<string, mixed>
   */
  public function casesGenerate(): array {
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
        file_get_contents($file->getPathname()),
        Yaml::parseFile($file->getPath() . "/$id.input.yml"),
      ];
    }

    return $cases;
  }

  /**
   * @phpstan-param array<string, mixed> $args
   *
   * @dataProvider casesGenerate
   */
  public function testGenerate(string $expected, array $args): void {
    $envVarStorage = new ArrayStorage(new \ArrayObject($args['envVars'] ?? []));

    $vfsRootDirName = $this->getName(FALSE) . '.' . $this->dataName();
    $vfs = vfsStream::setup($vfsRootDirName, NULL, $args['vfsStructure'] ?? []);

    $args['options']['cwd'] = $vfs->url() . '/' . $args['options']['cwd'];
    $generator = new PhpunitConfigGen($envVarStorage);
    $generator->setOptions($args['options']);

    $xml = new \DOMDocument();
    $xml->formatOutput = TRUE;
    $xml->preserveWhiteSpace = TRUE;
    $xml->loadXML($expected);

    static::assertSame($xml->saveXML(), $generator->generate());
  }

}
