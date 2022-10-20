<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_phpunit_incubator;

use Drupal\marvin\Utils;
use Drush\Commands\marvin_phpunit\TestCommandsBase;
use Drupal\marvin\Utils as MarvinUtils;
use Drupal\marvin_incubator\CommandsBaseTrait;
use Drupal\marvin_phpunit_incubator\Utils as PhpunitUtils;
use Robo\Collection\CollectionBuilder;
use Sweetchuck\Utils\Filter\ArrayFilterEnabled;
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
   * @command marvin:test:phpunit
   * @bootstrap none
   *
   * @marvinArgPackages packages
   * @marvinOptionPhpVariants phpVariants
   * @marvinOptionDatabaseVariants dbVariants
   *
   * @todo CLI option for testSuiteNames.
   */
  public function cmdRunExecute(
    array $packages,
    array $options = [
      'phpVariants' => [],
      'dbVariants' => [],
    ]
  ): ?CollectionBuilder {
    $testSuiteNames = $this->getTestSuiteNamesByEnvironmentVariant();
    if ($testSuiteNames === NULL || !$packages) {
      return NULL;
    }

    $phpVariants = array_filter($options['phpVariants'], new ArrayFilterEnabled());
    if (!$phpVariants) {
      // @todo This warning is no longer required.
      $this
        ->getLogger()
        ->warning(dt('There is no configured PHP variant. Check ${marvin.php.variant} in your drush.yml files'));
    }

    $dbVariants = array_filter($options['dbVariants'], new ArrayFilterEnabled());
    if (!$dbVariants) {
      // @todo This warning is no longer required.
      $this
        ->getLogger()
        ->warning(dt('There is no configured Database variant. Check ${marvin.database.variant} in your drush.yml files'));
    }

    $groups = [];
    foreach ($packages as $packageName) {
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
   */
  protected function getTaskPhpUnitRun(array $options, array $phpVariant = [], array $dbVariant = []): CollectionBuilder {
    // @todo Find a better place to getenv().
    $simpleTestBaseUrlEnv = getenv('SIMPLETEST_BASE_URL');
    $simpleTestBaseUrlInput = $this->input()->getOption('uri');
    if (!$simpleTestBaseUrlEnv && $simpleTestBaseUrlInput) {
      $phpVariant['command']['envVar']['SIMPLETEST_BASE_URL'] = $simpleTestBaseUrlInput;
    }

    $phpUnitTask = parent::getTaskPhpUnitRun($options)
      ->setPhpExecutable(Utils::phpVariantToCommand($phpVariant));

    $phpUnitConfigFileName = PhpunitUtils::getPhpunitConfigFileName(
      $this->getProjectRootDir(),
      $phpVariant,
      $dbVariant
    );

    if ($this->fs->exists($phpUnitConfigFileName)) {
      $phpUnitTask->setConfiguration($phpUnitConfigFileName);
    }

    return $phpUnitTask;
  }

}
