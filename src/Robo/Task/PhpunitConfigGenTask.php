<?php

declare(strict_types=1);

namespace Drupal\marvin_phpunit_incubator\Robo\Task;

use Drupal\marvin\Robo\Task\BaseTask;
use Drupal\marvin_phpunit_incubator\PhpunitConfigGen;
use Drupal\marvin_phpunit_incubator\PhpunitConfigGenProperties;
use Sweetchuck\EnvVarStorage\EnvVarStorageInterface;

class PhpunitConfigGenTask extends BaseTask {

  use PhpunitConfigGenProperties;

  /**
   * {@inheritdoc}
   */
  protected string $taskName = 'Marvin - Generate PHPUnit XML';

  protected ?EnvVarStorageInterface $envVarStorage = NULL;

  public function getEnvVarStorage(): ?EnvVarStorageInterface {
    return $this->envVarStorage;
  }

  public function setEnvVarStorage(?EnvVarStorageInterface $storage): static {
    $this->envVarStorage = $storage;

    return $this;
  }

  /**
   * @phpstan-param marvin-phpunit-incubator-robo-task-phpunit-config-generator-options $options
   */
  public function setOptions(array $options): static {
    parent::setOptions($options);
    $this->setOptionsProperties($options);

    if (array_key_exists('envVarStorage', $options)) {
      $this->setEnvVarStorage($options['envVarStorage']);
    }

    return $this;
  }

  protected function initOptions(): static {
    parent::initOptions();

    $this->options['envVarStorage'] = [
      'type' => 'other',
      'value' => $this->getEnvVarStorage(),
    ];

    $this->options['baseUrl'] = [
      'type' => 'other',
      'value' => $this->getBaseUrl(),
    ];

    $this->options['cwd'] = [
      'type' => 'other',
      'value' => $this->getCwd(),
    ];

    $this->options['rootProjectDir'] = [
      'type' => 'other',
      'value' => $this->getRootProjectDir(),
    ];

    $this->options['drupalRoot'] = [
      'type' => 'other',
      'value' => $this->getDrupalRoot(),
    ];

    $this->options['mdeDir'] = [
      'type' => 'other',
      'value' => $this->getMdeDir(),
    ];

    $this->options['vendorDir'] = [
      'type' => 'other',
      'value' => $this->getVendorDir(),
    ];

    $this->options['projectVendor'] = [
      'type' => 'other',
      'value' => $this->getProjectVendor(),
    ];

    $this->options['projectName'] = [
      'type' => 'other',
      'value' => $this->getProjectName(),
    ];

    $this->options['projectRelativePath'] = [
      'type' => 'other',
      'value' => $this->getProjectRelativePath(),
    ];

    return $this;
  }

  protected function runAction(): static {
    $this->assets['phpunitConfig'] = $this->getGenerator()->generate();

    return $this;
  }

  protected function getGenerator(): PhpunitConfigGen {
    $generator = new PhpunitConfigGen(
      $this->options['envVarStorage']['value'],
    );

    $generator
      ->setBaseUrl($this->options['baseUrl']['value'])
      ->setCwd($this->options['cwd']['value'])
      ->setRootProjectDir($this->options['rootProjectDir']['value'])
      ->setDrupalRoot($this->options['drupalRoot']['value'])
      ->setMdeDir($this->options['mdeDir']['value'])
      ->setVendorDir($this->options['vendorDir']['value'])
      ->setProjectVendor($this->options['projectVendor']['value'])
      ->setProjectName($this->options['projectName']['value'])
      ->setProjectRelativePath($this->options['projectRelativePath']['value']);

    return $generator;
  }

}
