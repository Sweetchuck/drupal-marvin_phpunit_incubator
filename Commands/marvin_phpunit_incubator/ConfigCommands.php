<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_phpunit_incubator;

use Drupal\Core\Url;
use Drupal\marvin_incubator\Attributes as MarvinIncubatorCLI;
use Drupal\marvin_incubator\CommandsBaseTrait;
use Drupal\marvin_phpunit_incubator\Robo\PhpunitConfigGenTaskLoader;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\marvin\CommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\Contract\TaskInterface;

class ConfigCommands extends CommandsBase {

  use CommandsBaseTrait;
  use PhpunitConfigGenTaskLoader;

  /**
   * @param string[] $packageNames
   *
   * @noinspection PhpUnused
   */
  #[CLI\Help(
    description: 'Generates phpunit.xml file for managed extensions.',
  )]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  #[CLI\Command(name: 'marvin:generate:phpunit-config')]
  #[CLI\Argument(
    name: 'packageNames',
    description: 'Package names.',
  )]
  #[MarvinIncubatorCLI\ValidatePackageNames(
    locators: [
      ['type' => 'argument', 'name' => 'packageNames'],
    ],
  )]
  public function cmdMarvinGeneratePhpunitConfigExecute(array $packageNames): TaskInterface {
    // NOTE: DrupalBootLevels is FULL because the URL is needed to set the SIMPLETEST_BASE_URL variable.
    // NOTE: Because of the DrupalBootLevels::FULL the CWD points to the drupalRoot.
    $packages = array_intersect_key(
      $this->getManagedDrupalExtensions(),
      array_flip($packageNames),
    );

    return $this->getTaskGeneratePhpunitConfigForPackages($packages);
  }

  /**
   * @phpstan-param array<string, mixed> $packages
   *
   * @todo PHPStan array shape.
   */
  protected function getTaskGeneratePhpunitConfigForPackages(array $packages): TaskInterface {
    // @phpstan-ignore-next-line
    return $this
      ->taskForEach($packages)
      ->iterationMessage('Generate PHPUnit configuration XML for package {key}')
      ->withBuilder($this->taskBuilderGeneratePhpunitConfigForPackage(...));
  }

  /**
   * @phpstan-param array<string, mixed> $package
   *
   * @todo PHPStan array shape.
   *
   * @noinspection PhpUnusedParameterInspection
   */
  protected function taskBuilderGeneratePhpunitConfigForPackage(
    CollectionBuilder $builder,
    string $key,
    $package,
  ): void {
    $projectRootDir = $this->getProjectRootDir();
    $drupalRootDir = $this->getComposerInfo()->getDrupalRootDir();
    // @todo Configurable.
    $mdeDir = "$projectRootDir/managedDrupalExtension";
    $baseUrl = Url::fromRoute('<front>', [], ['absolute' => TRUE]);

    $builder
      ->addTask(
        // @phpstan-ignore-next-line
        $this
          ->taskMarvinPhpunitConfigGenerator()
          ->setBaseUrl($baseUrl->toString())
          ->setDrupalRoot($drupalRootDir)
          ->setProjectVendor($package['projectVendor'])
          ->setProjectName($package['projectName'])
          ->setProjectRelativePath($package['pathRelative'])
      )
      ->addTask(
        // @todo This creates the required directories as well, but with wrong permissions.
        // @phpstan-ignore-next-line
        $this
          ->taskWriteToFile("$mdeDir/{$package['projectName']}/phpunit.xml")
          ->deferTaskConfiguration('text', 'phpunitConfig')
      );
  }

}
