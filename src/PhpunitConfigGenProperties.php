<?php

declare(strict_types=1);

namespace Drupal\marvin_phpunit_incubator;

trait PhpunitConfigGenProperties {

  protected ?string $baseUrl = NULL;

  public function getBaseUrl(): ?string {
    return $this->baseUrl;
  }

  public function setBaseUrl(?string $baseUrl): static {
    $this->baseUrl = $baseUrl;

    return $this;
  }

  protected string $cwd = '.';

  public function getCwd(): string {
    return $this->cwd;
  }

  public function setCwd(string $cwd): static {
    $this->cwd = $cwd;

    return $this;
  }

  protected string $rootProjectDir = '..';

  public function getRootProjectDir(): string {
    return $this->rootProjectDir;
  }

  /**
   * Relative from ::getCwd().
   */
  public function setRootProjectDir(string $rootProjectDir): static {
    $this->rootProjectDir = $rootProjectDir;

    return $this;
  }

  protected string $vendorDir = 'vendor';

  public function getVendorDir(): string {
    return $this->vendorDir;
  }

  /**
   * Relative from ::getRootProjectDir().
   */
  public function setVendorDir(string $vendorDir): static {
    $this->vendorDir = $vendorDir;

    return $this;
  }

  protected string $mdeDir = 'managedDrupalExtension';

  public function getMdeDir(): string {
    return $this->mdeDir;
  }

  /**
   * Relative from ::getRootProjectDir().
   */
  public function setMdeDir(string $mdeDir): static {
    $this->mdeDir = $mdeDir;

    return $this;
  }

  protected string $projectVendor = 'drupal';

  public function getProjectVendor(): string {
    return $this->projectVendor;
  }

  public function setProjectVendor(string $projectVendor): static {
    $this->projectVendor = $projectVendor;

    return $this;
  }

  protected string $projectName = '';

  public function getProjectName(): string {
    return $this->projectName;
  }

  /**
   * Example value: "my_module_01".
   */
  public function setProjectName(string $projectName): static {
    $this->projectName = $projectName;

    return $this;
  }

  protected string $projectRelativePath = '';

  public function getProjectRelativePath(): string {
    return $this->projectRelativePath;
  }

  /**
   * Relative from ::getRootProjectDir().
   *
   * Example value "../../drupal/my_module_01-1.x".
   */
  public function setProjectRelativePath(string $projectRelativePath): static {
    $this->projectRelativePath = $projectRelativePath;

    return $this;
  }

  protected string $drupalRoot = 'docroot';

  public function getDrupalRoot(): string {
    return $this->drupalRoot;
  }

  /**
   * Relative from ::getRootProjectDir().
   */
  public function setDrupalRoot(string $drupalRoot): static {
    $this->drupalRoot = $drupalRoot;

    return $this;
  }

  protected function setOptionsProperties(array $options): static {
    if (array_key_exists('baseUrl', $options)) {
      $this->setBaseUrl($options['baseUrl']);
    }

    if (array_key_exists('cwd', $options)) {
      $this->setCwd($options['cwd']);
    }

    if (array_key_exists('rootProjectDir', $options)) {
      $this->setRootProjectDir($options['rootProjectDir']);
    }

    if (array_key_exists('drupalRoot', $options)) {
      $this->setDrupalRoot($options['drupalRoot']);
    }

    if (array_key_exists('mdeDir', $options)) {
      $this->setMdeDir($options['mdeDir']);
    }

    if (array_key_exists('vendorDir', $options)) {
      $this->setVendorDir($options['vendorDir']);
    }

    if (array_key_exists('projectVendor', $options)) {
      $this->setProjectVendor($options['projectVendor']);
    }

    if (array_key_exists('projectName', $options)) {
      $this->setProjectName($options['projectName']);
    }

    if (array_key_exists('projectRelativePath', $options)) {
      $this->setProjectRelativePath($options['projectRelativePath']);
    }

    return $this;
  }

}
