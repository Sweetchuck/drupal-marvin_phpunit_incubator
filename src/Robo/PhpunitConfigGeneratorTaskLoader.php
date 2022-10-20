<?php

declare(strict_types = 1);

namespace Drupal\marvin_phpunit_incubator\Robo;

use Drupal\marvin_phpunit_incubator\Robo\Task\PhpunitConfigGeneratorTask;

trait PhpunitConfigGeneratorTaskLoader {

  /**
   * @return \Robo\Collection\CollectionBuilder|\Drupal\marvin_phpunit_incubator\Robo\Task\PhpunitConfigGeneratorTask
   */
  protected function taskPhpunitConfigGenerator(array $options = []) {
    /** @var \Drupal\marvin_phpunit_incubator\Robo\Task\PhpunitConfigGeneratorTask $task */
    $task = $this->task(PhpunitConfigGeneratorTask::class);
    $task->setOptions($options);

    return $task;
  }

}
