<?php

declare(strict_types=1);

namespace Drupal\marvin_phpunit_incubator;

use Drupal\marvin\Utils as MarvinUtils;
use Sweetchuck\EnvVarStorage\EnvVarStorage;
use Sweetchuck\EnvVarStorage\EnvVarStorageInterface;
use Sweetchuck\Utils\Walker\FileSystemExistsWalker;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @todo Use an overridable Twig template.
 *
 * @see https://github.com/drush-ops/drush/issues/5662
 */
class PhpunitConfigGen {

  use PhpunitConfigGenProperties;

  protected EnvVarStorageInterface $envVarStorage;

  protected Filesystem $fs;

  protected FileSystemExistsWalker $fileSystemExistsWalker;

  protected \DOMDocument $xml;

  protected \DOMXPath $xpath;
  protected \DOMElement $xmlRoot;

  public function __construct(
    protected MarvinUtils $utils,
    ?EnvVarStorageInterface $envVarStorage = NULL,
    ?Filesystem $fs = NULL,
  ) {
    $this->envVarStorage = $envVarStorage ?: new EnvVarStorage();
    $this->fs = $fs ?: new Filesystem();
    $this->fileSystemExistsWalker = new FileSystemExistsWalker($this->fs);
  }

  /**
   * @param array<string, mixed> $options
   */
  public function setOptions(array $options): static {
    $this->setOptionsProperties($options);

    return $this;
  }

  public function generate(): string {
    $this->xml = new \DOMDocument('1.0', 'utf-8');
    $this->xml->preserveWhiteSpace = TRUE;
    $this->xml->formatOutput = TRUE;
    $this->xmlRoot = $this->xml->createElement('phpunit');
    $this->xml->appendChild($this->xmlRoot);
    $this->xpath = new \DOMXPath($this->xml);

    return (string) $this
      ->generateRootAttributes()
      ->generateUsageComment()
      ->generatePhp()
      ->generateSource()
      ->generateCoverage()
      ->generateTestsuites()
      ->generateLogging()
      ->xml
      ->saveXML();
  }

  protected function generateRootAttributes(): static {
    $this->addAttributes($this->xmlRoot, $this->getRootAttributes());

    return $this;
  }

  protected function generateUsageComment(): static {
    $drupalRoot = $this->getDrupalRoot();
    $projectName = $this->getProjectName();
    $mdeDir = $this->getMdeDir();
    $dstFilePathRelativeFromDrupalRoot = "../$mdeDir/$projectName/phpunit.xml";
    $text = <<< TEXT

      Usage:
      cd $drupalRoot
      ../vendor/bin/phpunit --configuration='$dstFilePathRelativeFromDrupalRoot'

      TEXT;

    $comment = $this->xml->createComment(str_replace('--', '&#45;&#45;', $text));
    $this->xmlRoot->appendChild($comment);

    return $this;
  }

  protected function generatePhp(): static {
    $phpElement = $this->ensureChildElement('php');
    $values = [
      'ini' => $this->getPhpIniValues(),
      'env' => $this->getPhpEnvValues(),
    ];
    foreach ($values as $type => $pairs) {
      foreach ($pairs as $name => $value) {
        $element = $this->xml->createElement($type);
        $element->setAttribute('name', (string) $name);
        $element->setAttribute('value', (string) $value);
        $phpElement->appendChild($element);
      }
    }

    return $this;
  }

  protected function generateSource(): static {
    $sourceElement = $this->ensureChildElement('source');
    $includeElement = $this->ensureChildElement('include', $sourceElement);
    foreach ($this->getSourceInclude() as $path => $info) {
      $element = $this->xml->createElement($info['type'], $path);
      $includeElement->appendChild($element);
      $this->addAttributes($element, $info['attributes']);
    }

    return $this;
  }

  protected function generateCoverage(): static {
    $coverageElement = $this->ensureChildElement('coverage');
    $reportElement = $this->ensureChildElement('report', $coverageElement);
    $values = $this->getCoverageReporters();
    foreach ($values as $tag => $attributes) {
      $element = $this->xml->createElement($tag);
      $reportElement->appendChild($element);
      $this->addAttributes($element, $attributes);
    }

    return $this;
  }

  protected function generateTestsuites(): static {
    $wrapperElement = $this->ensureChildElement('testsuites');
    foreach ($this->getTestsuites() as $suite) {
      $testsuiteElement = $this->xml->createElement('testsuite');
      $wrapperElement->appendChild($testsuiteElement);
      $testsuiteElement->setAttribute('name', $suite['name']);
      foreach ($suite['entries'] as $entry) {
        $element = $this->xml->createElement($entry['type'], $entry['value']);
        $testsuiteElement->appendChild($element);
        $this->addAttributes($element, $entry['attributes']);
      }
    }

    return $this;
  }

  protected function generateLogging(): static {
    $loggingElement = $this->ensureChildElement('logging');
    $this->addChildElements($loggingElement, $this->getLoggingEntries());

    return $this;
  }

  protected function ensureChildElement(string $name, ?\DOMElement $parent = NULL): \DOMElement {
    if (!$parent) {
      $parent = $this->xmlRoot;
    }

    $elements = $this->xmlRoot->getElementsByTagName($name);
    if ($elements->count()) {
      /* @noinspection PhpIncompatibleReturnTypeInspection */
      return $elements->item(0);
    }

    $element = $this->xml->createElement($name);
    $parent->appendChild($element);

    return $element;
  }

  /**
   * @phpstan-param array<mixed> $definitions
   */
  protected function addChildElements(\DOMElement $parent, array $definitions): static {
    foreach ($definitions as $definition) {
      $element = $this->xml->createElement($definition['name'], $definition['value'] ?? '');
      $parent->appendChild($element);
      $this->addAttributes($element, $definition['attributes'] ?? []);
    }

    return $this;
  }

  /**
   * @phpstan-param array<string, mixed> $attributes
   */
  protected function addAttributes(\DOMElement $element, array $attributes): static {
    foreach ($attributes as $name => $value) {
      $element->setAttribute($name, (string) $value);
    }

    return $this;
  }

  /**
   * @phpstan-return array<string, string>
   */
  protected function getRootAttributes(): array {
    $backToRoot = $this->getBackToRootFromPhpunitXml();
    $vendorDir = $this->getVendorDir();
    $projectName = $this->getProjectName();

    return [
      'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
      'xsi:noNamespaceSchemaLocation' => "$backToRoot/$vendorDir/phpunit/phpunit/phpunit.xsd",
      'bootstrap' => "$backToRoot/tests/bootstrap.php",
      'cacheDirectory' => "$backToRoot/.cache/phpunit/drupal/$projectName",
      'beStrictAboutOutputDuringTests' => 'true',
      'beStrictAboutChangesToGlobalState' => 'true',
      'displayDetailsOnPhpunitDeprecations' => 'true',
      'displayDetailsOnTestsThatTriggerNotices' => 'true',
      'displayDetailsOnTestsThatTriggerDeprecations' => 'true',
      'displayDetailsOnTestsThatTriggerWarnings' => 'true',
      'displayDetailsOnTestsThatTriggerErrors' => 'true',
      'colors' => 'true',
    ];
  }

  /**
   * @phpstan-return array<string, scalar>
   */
  protected function getPhpIniValues(): array {
    return [
      'memory_limit' => '-1',
      'error_reporting' => \E_ALL,
    ];
  }

  /**
   * @phpstan-return array<string, null|string>
   */
  protected function getPhpEnvValues(): array {
    $values = $this->getDefaultEnvVarValues();
    foreach ($values as $name => $defaultValue) {
      $actualValue = $this->envVarStorage->get($name);
      $values[$name] = gettype($actualValue) === 'string'
        ? $actualValue
        : $defaultValue;
    }

    return $values;
  }

  /**
   * @phpstan-return array<string, null|string>
   */
  protected function getDefaultEnvVarValues(): array {
    // @todo Remove or explain `chromedriver --port=4444 --url-base=/wd/hub`.
    $webDriver = [
      'chrome',
      [
        'browserName' => 'chrome',
        'goog:chromeOptions' => [
          'args' => [
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
          ],
        ],
      ],
      'http://127.0.0.1:4444/wd/hub',
    ];

    return [
      'SIMPLETEST_BASE_URL' => $this->getBaseUrl(),
      'SIMPLETEST_DB' => NULL,
      'SYMFONY_DEPRECATIONS_HELPER' => http_build_query([
        'max' => [
          // Twice in \Drupal\Tests\DocumentElement::getText().
          // Twice in \Drupal\Tests\DocumentElement::waitFor().
          'self' => 5,
          'direct' => 999,
          'indirect' => 999,
        ],
        'quiet' => [
          'direct',
          'indirect',
        ],
      ]),
      'MINK_DRIVER_CLASS' => 'Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver',
      'MINK_DRIVER_ARGS' => NULL,
      'MINK_DRIVER_ARGS_WEBDRIVER' => json_encode($webDriver, JSON_UNESCAPED_SLASHES),
    ];
  }

  /**
   * @phpstan-return array<string, array<string, mixed>>
   */
  protected function getSourceInclude(): array {
    $backToRoot = $this->getBackToRootFromPhpunitXml();
    $extDirFromXml = "$backToRoot/" . $this->getProjectRelativePath();
    $extDirFromCwd = Path::Join(
      $this->getCwd(),
      $this->getRootProjectDir(),
      $this->getProjectRelativePath(),
    );
    $this->fileSystemExistsWalker->setBaseDir($extDirFromCwd);

    $relativePaths = [
      'Commands' => TRUE,
      'Generators' => TRUE,
      'src' => TRUE,
    ];
    $relativePaths += array_fill_keys(
      $this->utils->getDirectDescendantDrupalPhpFiles($extDirFromCwd),
      TRUE,
    );
    array_walk($relativePaths, $this->fileSystemExistsWalker);

    $config = [];
    // @todo Apply the configuration at this point.
    $paths = [];
    foreach ($relativePaths as $relativePath => $exists) {
      if (!$exists || (isset($config[$relativePath]['enabled']) && empty($config[$relativePath]['enabled']))) {
        continue;
      }

      $key = "$extDirFromXml/$relativePath";
      $paths[$key] = $config[$relativePath] ?? [];
      $paths[$key]['type'] = is_dir("$extDirFromCwd/$relativePath") ?
        'directory'
        : 'file';
      $paths[$key]['attributes'] = array_filter($paths[$key]['attributes'] ?? []);
    }

    return $paths;
  }

  /**
   * @phpstan-return array<string, array<string, mixed>>
   */
  protected function getCoverageReporters(): array {
    return [
      'clover' => [
        'outputFile' => './reports/machine/coverage/phpunit.xml',
      ],
      'html' => [
        'outputDirectory' => './reports/human/coverage/html',
      ],
      'text' => [
        'outputFile' => 'php://stdout',
      ],
    ];
  }

  /**
   * @phpstan-return array<array<string, mixed>>
   *
   * @see \drupal_phpunit_get_extension_namespaces
   */
  protected function getTestsuites(): array {
    $pairs = [
      'unit' => 'Unit',
      'kernel' => 'Kernel',
      'functional' => 'Functional',
      'build' => 'Build',
      'functional-javascript' => 'FunctionalJavascript',
    ];
    $extDirFromXml = $this->getBackToRootFromPhpunitXml() . '/' . $this->getProjectRelativePath();
    $extDirFromCwd = Path::join(
      $this->getCwd(),
      $this->getRootProjectDir(),
      $this->getProjectRelativePath(),
    );

    $suites = [];
    foreach ($pairs as $suiteName => $suiteDir) {
      if (!$this->fs->exists("$extDirFromCwd/tests/src/$suiteDir")) {
        continue;
      }

      $suites[] = [
        'name' => $suiteName,
        'entries' => [
          [
            'type' => is_dir("$extDirFromCwd/tests/src/$suiteDir") ? 'directory' : 'file',
            'value' => "$extDirFromXml/tests/src/$suiteDir",
            'attributes' => [],
          ],
        ],
      ];
    }

    return $suites;
  }

  /**
   * @phpstan-return array<array<string, mixed>>
   */
  protected function getLoggingEntries(): array {
    return [
      [
        'name' => 'testdoxHtml',
        'attributes' => [
          'outputFile' => './reports/human/junit/phpunit.html',
        ],
      ],
      [
        'name' => 'junit',
        'attributes' => [
          'outputFile' => './reports/machine/junit/phpunit.xml',
        ],
      ],
    ];
  }

  /**
   * Relative path back to the rootProjectDir from "root/mdeDir/projectName".
   */
  protected function getBackToRootFromPhpunitXml(): string {
    // Back to root project dir from "<rootProjectDir>/<mdeDir>/<projectName>" (where the phpunit.xml is).
    // @todo Calculate, because <mdeDir> can be deep.
    return '../..';
  }

}
