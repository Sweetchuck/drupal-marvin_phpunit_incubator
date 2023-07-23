<?php

declare(strict_types=1);

namespace Drupal\marvin_phpunit_incubator;

class Utils {

  /**
   * @phpstan-param marvin-php-variant $phpVariant
   * @phpstan-param marvin-incubator-db-variant $dbVariant
   */
  public static function getPhpunitConfigFileName(
    string $projectRootDir,
    array $phpVariant,
    array $dbVariant,
  ): string {
    return sprintf(
      '%s/phpunit.%s.%s.xml',
      $projectRootDir,
      $dbVariant['id'],
      $phpVariant['id'],
    );
  }

}
