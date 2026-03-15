<?php

declare(strict_types=1);

namespace Drupal\marvin_phpunit_incubator;

use Drupal\marvin\ContainerInitializerBase;
use Drupal\marvin\Utils as MarvinUtils;
use Drupal\marvin_incubator\ContainerInitializer as ContainerInitializerMarvinProduct;

class ContainerInitializer extends ContainerInitializerBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function getDependencies(): array {
    return [
      ContainerInitializerMarvinProduct::class,
    ];
  }

  #[\Override]
  public static function getServiceInfoList(): array {
    return [
      PhpunitConfigGen::class => [
        'class' => PhpunitConfigGen::class,
        'shared' => TRUE,
        'arguments' => [
          '@' . MarvinUtils::class,
        ],
      ],
    ];
  }

}
