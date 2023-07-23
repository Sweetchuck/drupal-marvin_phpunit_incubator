<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_phpunit_incubator;

use Drupal\marvin\Utils as MarvinUtils;
use Drupal\marvin_incubator\CommandsBaseTrait;
use Drupal\marvin_incubator\Attributes as MarvinIncubatorCLI;
use Drupal\marvin_phpunit_incubator\Utils as PhpunitUtils;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\marvin_phpunit\TestCommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\Contract\TaskInterface;
use Sweetchuck\Utils\Filter\EnabledFilter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class TestCommands extends TestCommandsBase {

  use CommandsBaseTrait;

  protected Filesystem $fs;

  public function __construct() {
    parent::__construct();

    $this->fs = new Filesystem();
  }

  /**
   * @phpstan-param string[] $packageNames
   * @phpstan-param array<string, mixed> $options
   *
   * @marvinOptionPhpVariants phpVariants
   * @marvinOptionDatabaseVariants dbVariants
   *
   * @todo CLI option for testSuiteNames.
   * @todo Consistent argument/option names. See marvin:site:create.
   */
  #[CLI\Command(name: 'marvin:test:phpunit')]
  #[CLI\Help(
    description: 'Runs PHPUnit tests for the given managed Drupal extensions.',
  )]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  #[CLI\Option(
    name: 'phpVariants',
    description: '@todo Documentation.',
  )]
  #[CLI\Option(
    name: 'dbVariants',
    description: '@todo Documentation.',
  )]
  #[CLI\Argument(
    name: 'packageNames',
    description: 'Package names.',
  )]
  #[MarvinIncubatorCLI\ValidatePackageNames(
    locators: [
      [
        'type' => 'argument',
        'name' => 'packageNames',
      ],
    ],
  )]
  public function cmdRunExecute(
    array $packageNames,
    array $options = [
      'phpVariants' => [],
      'dbVariants' => [],
    ]
  ): ?CollectionBuilder {
    $testSuiteNames = $this->getTestSuiteNamesByEnvironmentVariant();
    if ($testSuiteNames === NULL || !$packageNames) {
      return NULL;
    }

    $phpVariants = array_filter($options['phpVariants'], new EnabledFilter());
    if (!$phpVariants) {
      // @todo This warning is no longer required.
      $this
        ->getLogger()
        ->warning('There is no configured PHP variant. Check ${marvin.php.variant} in your drush.yml files');
    }

    $dbVariants = array_filter($options['dbVariants'], new EnabledFilter());
    if (!$dbVariants) {
      // @todo This warning is no longer required.
      $this
        ->getLogger()
        ->warning('There is no configured Database variant. Check ${marvin.database.variant} in your drush.yml files');
    }

    $groups = [];
    foreach ($packageNames as $packageName) {
      $groups[] = MarvinUtils::splitPackageName($packageName)['name'];
    }

    $composerInfo = $this->getComposerInfo();
    $phpunitExecutable = Path::makeRelative(
      Path::join($this->getProjectRootDir(), $composerInfo['config']['bin-dir'], 'phpunit'),
      $this->getConfig()->get('env.cwd')
    );
    $cb = $this->collectionBuilder();
    foreach ($phpVariants as $phpVariant) {
      foreach ($dbVariants as $dbVariant) {
        $cb->addTask($this->getTaskPhpUnitRun(
          [
            'phpunitExecutable' => $phpunitExecutable,
            'testSuite' => $testSuiteNames,
            'group' => $groups,
          ],
          $phpVariant,
          $dbVariant
        ));
      }
    }

    return $cb;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $options
   * @phpstan-param marvin-php-variant $phpVariant
   * @phpstan-param marvin-incubator-db-variant $dbVariant
   */
  protected function getTaskPhpUnitRun(
    array $options,
    array $phpVariant = [],
    array $dbVariant = [],
  ): TaskInterface {
    // @todo Find a better place to getenv().
    $simpleTestBaseUrlEnv = getenv('SIMPLETEST_BASE_URL');
    $simpleTestBaseUrlInput = $this->input()->getOption('uri');
    if (!$simpleTestBaseUrlEnv && $simpleTestBaseUrlInput) {
      $phpVariant['command']['envVar']['SIMPLETEST_BASE_URL'] = $simpleTestBaseUrlInput;
    }

    $phpUnitTask = parent::getTaskPhpUnitRun($options)
      ->setPhpExecutable(MarvinUtils::phpVariantToCommand($phpVariant));

    $phpUnitConfigFileName = PhpunitUtils::getPhpunitConfigFileName(
      $this->getProjectRootDir(),
      $phpVariant,
      $dbVariant,
    );

    if ($this->fs->exists($phpUnitConfigFileName)) {
      $phpUnitTask->setConfiguration($phpUnitConfigFileName);
    }

    return $phpUnitTask;
  }

}
