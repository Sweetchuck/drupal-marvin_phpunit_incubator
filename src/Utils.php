<?php

declare(strict_types = 1);

namespace Drupal\marvin_phpunit_incubator;

class Utils {

  public static function getPhpunitConfigFileName(
    string $projectRootDir,
    array $phpVariant,
    array $dbVariant
  ): string {
    return sprintf(
      '%s/phpunit.%s.%s.xml',
      $projectRootDir,
      $dbVariant['id'],
      $phpVariant['id'],
    );
  }

}
