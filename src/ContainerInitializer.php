<?php

declare(strict_types=1);

namespace Drupal\marvin_phpunit_incubator;

use Drupal\marvin\ContainerInitializerBase;
use Drupal\marvin\Utils as MarvinUtils;
use Psr\Container\ContainerInterface;

class ContainerInitializer extends ContainerInitializerBase {

  #[\Override]
  public static function isInitialized(ContainerInterface $container): bool {
    return $container->has(PhpunitConfigGen::class);
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
