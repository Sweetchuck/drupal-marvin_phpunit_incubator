<?php

declare(strict_types=1);

namespace Drupal\marvin_phpunit_incubator\Robo;

use Drupal\marvin_phpunit_incubator\Robo\Task\PhpunitConfigGenTask;

trait PhpunitConfigGenTaskLoader {

  /**
   * @phpstan-param marvin-phpunit-incubator-robo-task-phpunit-config-generator-options $options
   *
   * @return \Robo\Collection\CollectionBuilder|\Drupal\marvin_phpunit_incubator\Robo\Task\PhpunitConfigGenTask
   */
  protected function taskMarvinPhpunitConfigGenerator(array $options = []) {
    /** @var \Drupal\marvin_phpunit_incubator\Robo\Task\PhpunitConfigGenTask $task */
    $task = $this->task(PhpunitConfigGenTask::class);
    $task->setOptions($options);

    return $task;
  }

}
