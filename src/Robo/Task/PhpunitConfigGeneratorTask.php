<?php

declare(strict_types = 1);

namespace Drupal\marvin_phpunit_incubator\Robo\Task;

use Drupal\marvin\Robo\Task\BaseTask;
use Drupal\marvin\WriterWrapper;
use Drupal\marvin_phpunit_incubator\PhpunitConfigGen;

class PhpunitConfigGeneratorTask extends BaseTask {

  /**
   * {@inheritdoc}
   */
  protected string $taskName = 'Marvin - Generate PHPUnit XML';

  protected WriterWrapper $outputDestinationWrapper;

  public function __construct(?WriterWrapper $outputDestinationWrapper = NULL) {
    $this->outputDestinationWrapper = $outputDestinationWrapper ?: new WriterWrapper();
  }

  protected string $drupalRoot = '';

  public function getDrupalRoot(): string {
    return $this->drupalRoot;
  }

  public function setDrupalRoot(string $drupalRoot) {
    $this->drupalRoot = $drupalRoot;

    return $this;
  }

  protected string $url = '';

  public function getUrl(): string {
    return $this->url;
  }

  public function setUrl(string $value) {
    $this->url = $value;

    return $this;
  }

  protected array $dbConnection = [];

  public function getDbConnection(): array {
    return $this->dbConnection;
  }

  public function setDbConnection(array $value) {
    $this->dbConnection = $value;

    return $this;
  }

  /**
   * @var string[]
   */
  protected array $packagePaths = [];

  /**
   * @return string[]
   */
  public function getPackagePaths(): array {
    return $this->packagePaths;
  }

  /**
   * @param string[] $value
   */
  public function setPackagePaths(array $value): static {
    $this->packagePaths = $value;

    return $this;
  }

  protected string $phpVersion = '0704';

  public function getPhpVersion(): string {
    return $this->phpVersion;
  }

  public function setPhpVersion(string $value) {
    $this->phpVersion = $value;

    return $this;
  }

  protected string $reportsDir = 'reports';

  public function getReportsDir(): string {
    return $this->reportsDir;
  }

  public function setReportsDir(string $value) {
    $this->reportsDir = $value;

    return $this;
  }

  /**
   * @return null|string|\Symfony\Component\Console\Output\OutputInterface
   */
  public function getOutputDestination() {
    return $this->outputDestinationWrapper->getDestination();
  }

  /**
   * @param null|string|\Symfony\Component\Console\Output\OutputInterface $destination
   */
  public function setOutputDestination($destination): static {
    $this->outputDestinationWrapper->setDestination($destination);

    return $this;
  }

  public function getOutputDestinationMode(): string {
    return $this->outputDestinationWrapper->getDestinationMode();
  }

  public function setOutputDestinationMode(string $mode): static {
    $this->outputDestinationWrapper->setDestinationMode($mode);

    return $this;
  }

  public function setOptions(array $options): static {
    parent::setOptions($options);

    if (array_key_exists('outputDestination', $options)) {
      $this->setOutputDestination($options['outputDestination']);
    }

    if (array_key_exists('outputDestinationMode', $options)) {
      $this->setOutputDestinationMode($options['outputDestinationMode']);
    }

    if (array_key_exists('drupalRoot', $options)) {
      $this->setDrupalRoot($options['drupalRoot']);
    }

    if (array_key_exists('url', $options)) {
      $this->setUrl($options['url']);
    }

    if (array_key_exists('dbConnection', $options)) {
      $this->setDbConnection($options['dbConnection']);
    }

    if (array_key_exists('phpVersion', $options)) {
      $this->setPhpVersion($options['phpVersion']);
    }

    if (array_key_exists('packagePaths', $options)) {
      $this->setPackagePaths($options['packagePaths']);
    }

    if (array_key_exists('reportsDir', $options)) {
      $this->setReportsDir($options['reportsDir']);
    }

    return $this;
  }

  protected function initOptions(): static {
    parent::initOptions();

    $this->options['drupalRoot'] = [
      'type' => 'other',
      'value' => $this->getDrupalRoot(),
    ];

    $this->options['url'] = [
      'type' => 'other',
      'value' => $this->getUrl(),
    ];

    $this->options['dbConnection'] = [
      'type' => 'other',
      'value' => $this->getDbConnection(),
    ];

    $this->options['packagePaths'] = [
      'type' => 'other',
      'value' => $this->getPackagePaths(),
    ];

    $this->options['phpVersion'] = [
      'type' => 'other',
      'value' => $this->getPhpVersion(),
    ];

    $this->options['reportsDir'] = [
      'type' => 'other',
      'value' => $this->getReportsDir(),
    ];

    return $this;
  }

  protected function runAction(): static {
    $this->assets['phpunitConfig'] = $this->getGenerator()->generate();
    $this
      ->outputDestinationWrapper
      ->write($this->assets['phpunitConfig'])
      ->close();

    return $this;
  }

  protected function getGenerator(): PhpunitConfigGen {
    $generator = new PhpunitConfigGen();

    if ($this->options['drupalRoot']['value']) {
      $generator->setDrupalRoot($this->options['drupalRoot']['value']);
    }

    if ($this->options['url']['value']) {
      $generator->setUrl($this->options['url']['value']);
    }

    if ($this->options['dbConnection']['value']) {
      $generator->setDbConnection($this->options['dbConnection']['value']);
    }

    if ($this->options['phpVersion']['value']) {
      $generator->setPhpVersion($this->options['phpVersion']['value']);
    }

    if ($this->options['packagePaths']['value']) {
      $generator->setPackagePaths($this->options['packagePaths']['value']);
    }

    if ($this->options['reportsDir']['value']) {
      $generator->setReportsDir($this->options['reportsDir']['value']);
    }

    return $generator;
  }

}
