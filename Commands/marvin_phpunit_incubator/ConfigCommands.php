<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_phpunit_incubator;

use Drupal\marvin\PhpVariantTrait;
use Drupal\marvin_incubator\CommandsBaseTrait;
use Drupal\marvin_phpunit_incubator\Robo\PhpunitConfigGeneratorTaskLoader;
use Drush\Commands\marvin\CommandsBase;
use Drush\Sql\SqlBase;
use Psr\Log\LoggerInterface;
use Robo\Contract\TaskInterface;
use Webmozart\PathUtil\Path;

class ConfigCommands extends CommandsBase {

  use CommandsBaseTrait;
  use PhpVariantTrait;
  use PhpunitConfigGeneratorTaskLoader;

  /**
   * @command marvin:generate:phpunit-config
   * @bootstrap configuration
   */
  public function generatePhpunitConfig(): TaskInterface {
    $bootstrapManager = $this->getContainer()->get('bootstrap.manager');

    $uri = $bootstrapManager->getUri();
    if (is_bool($uri)) {
      return $this->getTaskLoggerWrite('URI could not be detected');
    }

    $uri = (string) $uri;
    $uriParts = parse_url($uri);
    // @todo URL parts detector.
    [$webPhpVariantId, , $dbId] = explode('.', $uriParts['host']);

    $phpVariants = $this->getConfigPhpVariants();
    $webPhpVariant = $phpVariants[$webPhpVariantId];

    $projectRoot = $bootstrapManager->getComposerRoot();
    $drupalRootAbs = $bootstrapManager->getRoot();
    $drupalRoot = Path::makeRelative($drupalRootAbs, $projectRoot);
    $backToProjectRoot = Path::makeRelative($projectRoot, $drupalRootAbs);

    $reportsDir = (string) $this->getConfig()->get('marvin.reportsDir', 'reports');
    $db = SqlBase::create([]);
    $dstFileName = "$backToProjectRoot/phpunit.$dbId.{$webPhpVariant['version']['majorMinor']}.xml";

    $dbConnection = $db->getDbSpec();
    unset($dbConnection['prefix']);

    return $this
      ->taskPhpunitConfigGenerator()
      ->setOutputDestination($dstFileName)
      ->setDrupalRoot($drupalRoot)
      ->setUrl($uri)
      ->setDbConnection($dbConnection)
      ->setPhpVersion((string) $webPhpVariant['version']['id'])
      ->setReportsDir($reportsDir)
      ->setPackagePaths($this->getManagedDrupalExtensions());
  }

  /**
   * @todo Move this method into \Drush\Commands\marvin\CommandsBase.
   *
   * @see \Drush\Commands\marvin\CommandsBase
   */
  protected function getTaskLoggerWrite(
    string $message,
    array $context = [],
    $exitCode = 1,
    string $level = 'error',
    ?LoggerInterface $logger = NULL
  ): TaskInterface {
    return $this
      ->collectionBuilder()
      ->addCode(function () use ($message, $context, $exitCode, $level, $logger): int {
        $logger = $logger ?: $this->getLogger();
        $logger->log($level, $message, $context);

        return $exitCode;
      });
  }

}
