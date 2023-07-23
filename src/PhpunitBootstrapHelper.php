<?php

declare(strict_types=1);

namespace Drupal\marvin_phpunit_incubator;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class PhpunitBootstrapHelper {

  protected ClassLoader $classLoader;

  protected string $root;

  protected string $composerFileName;

  /**
   * @phpstan-var array<string, mixed>
   */
  protected array $composer;

  public function populateClassLoader(
    string $root,
    string $composerFileName,
    ?ClassLoader $classLoader = NULL,
  ): static {
    $this->root = $root === '' ? '.' : $root;
    $this->composerFileName = $composerFileName;
    $this->composer = json_decode(
      file_get_contents(Path::join($this->root, $this->composerFileName)) ?: '{}',
      TRUE,
    );

    $this->classLoader = $classLoader
      ?: require Path::join(
        $this->root,
        $this->composer['config']['vendor-dir'] ?? 'vendor',
        'autoload.php',
      );

    $this->populateClassLoaderDrush();

    return $this;
  }

  protected function populateClassLoaderDrush(): static {
    foreach ($this->getDrupalDrushPackageDirs() as $extName => $extDir) {
      $composer = json_decode(file_get_contents("$extDir/composer.json") ?: '{}', TRUE);
      foreach (['autoload', 'autoload-dev'] as $type) {
        $psr4 = $composer[$type]['psr-4'] ?? $this->getDefaultNamespaces($extDir, $extName, $type);
        foreach ($psr4 as $namespace => $path) {
          // Resolve the symlinks.
          // If the psr-4 definition is there but the $path not exists,
          // then the \realpath() returns FALSE.
          $pathAbsolute = realpath(Path::join($extDir, $path));
          if ($pathAbsolute) {
            $this->classLoader->addPsr4($namespace, $pathAbsolute);
          }
        }
      }
    }

    return $this;
  }

  protected function getDrupalDrushDir(): string {
    $drushDir = Path::join('drush', 'Commands', 'contrib');
    foreach ($this->composer['extra']['installer-paths'] ?? [] as $dirPattern => $conditions) {
      if (in_array('type:drupal-drush', $conditions)) {
        // @todo If the "{name}" placeholder is not at the end then we are screwed.
        $drushDir = preg_replace('@/\{\$name}$@', '', $dirPattern);

        break;
      }
    }

    return Path::join($this->root, $drushDir);
  }

  /**
   * @phpstan-return array<string, string>
   */
  protected function getDrupalDrushPackageDirs(): array {
    $files = (new Finder())
      ->in($this->getDrupalDrushDir())
      ->followLinks()
      ->depth('== 1')
      ->name('composer.json');

    $packages = [];
    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    foreach ($files as $file) {
      $extDir = $file->getPath();
      $extName = basename($extDir);
      $packages[$extName] = $extDir;
    }

    return $packages;
  }

  /**
   * @phpstan-return array<string, string>
   */
  protected function getDefaultNamespaces(
    string $extensionDir,
    string $extensionName,
    string $type,
  ): array {
    $candidates = $type === 'autoload' ?
      [
        "Drush\\Commands\\$extensionName\\" => "Commands/$extensionName/",
        "Drush\\Generators\\$extensionName\\" => "Generators/$extensionName/",
        "Drupal\\$extensionName\\" => 'src/',
      ]
      : [
        "Drupal\\Tests\\$extensionName\\" => 'tests/src/',
      ];

    $namespaces = [];
    foreach ($candidates as $namespace => $path) {
      if (file_exists(Path::join($extensionDir, $path))) {
        $namespaces[$namespace] = $path;
      }
    }

    return $namespaces;
  }

}
