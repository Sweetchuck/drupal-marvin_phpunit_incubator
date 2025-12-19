<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_phpunit_incubator;

use Drupal\marvin\CommandEvent;
use Drupal\marvin\ComposerInfo;
use Drupal\marvin\MarvinTaskDefinitionCommandTrait;
use Drupal\marvin\Utils;
use Drupal\marvin_incubator\CommandsBaseTrait;
use Drupal\marvin_incubator\Package\Handler as PackageHandler;
use Drupal\marvin_phpunit_incubator\ContainerInitializer;
use Drupal\marvin_phpunit_incubator\Robo\PhpunitConfigGenTaskLoader;
use Drush\Attributes as CLI;
use Drush\Attributes\Bootstrap as CliBootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Robo\Collection\CallableTask;
use Robo\Collection\CollectionBuilder;
use Robo\Collection\Tasks as LoopTaskLoader;
use Robo\Contract\BuilderAwareInterface;
use Robo\State\Data as RoboState;
use Robo\Task\File\Tasks as FileTaskLoader;
use Robo\TaskAccessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Generates phpunit.xml file for managed Drupal extensions.',
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
class MarvinPhpunitConfigGenerateCommand extends Command implements BuilderAwareInterface {

  use AutowireTrait {
    create as protected autowireCreate;
  }
  use TaskAccessor;
  use FileTaskLoader;
  use LoopTaskLoader;
  use CommandsBaseTrait;
  use PhpunitConfigGenTaskLoader;
  use MarvinTaskDefinitionCommandTrait;

  public const string NAME = 'marvin:phpunit:config:generate';

  protected ?array $packages = NULL;

  protected function getPackages(): array {
    if (!$this->packages) {
      $this->packages = $this->packageHandler->collect(
        '.',
        $this->utils->getComposerJsonFileName(),
      );
    }

    return $this->packages;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    ContainerInitializer::initialize($container);

    return self::autowireCreate($container);
  }

  public function __construct(
    #[Autowire(Filesystem::class)]
    protected Filesystem $fs,
    #[Autowire('config')]
    protected DrushConfig $drushConfig,
    #[Autowire('eventDispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    #[Autowire(Utils::class)]
    protected Utils $utils,
    #[Autowire(PackageHandler::class)]
    protected PackageHandler $packageHandler,
    #[Autowire(LoggerInterface::class)]
    protected LoggerInterface $logger,
    #[Autowire(ContainerInterface::class)]
    protected ContainerInterface $container,
  ) {
    parent::__construct();
    // @todo Subscribe to composer:script:post-install-cmd.
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function configure(): void {
    parent::configure();
    $this
      ->addArgument(
        'packageNames',
        InputArgument::IS_ARRAY,
        'Package names. Run "drush marvin:mde:list" to see the list of available packages.',
      )
      ->addUsage('First ::addUsage() goes into /dev/null')
      ->addUsage('my_module_01 drupal/my_module_02');
  }

  /**
   * @param array<string> $packageNames
   */
  #[\Override]
  public function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $this->validate($input, $output);
    }
    catch (\Throwable $exception) {
      $this->logger->error($exception->getMessage());

      return self::FAILURE;
    }

    $event = new CommandEvent(
      $input,
      $output,
      NULL,
      $this->collectionBuilder(),
      [],
    );
    $event->taskDefinitions += $this->getTaskDefsInitStateDataBase($event);
    $event->taskDefinitions += $this->getTaskDefsInitStateDataCustom($event);
    $event->taskDefinitions += $this->getTaskDefsPhpunitGenerateConfig($event);

    return $this->mtdRun(
      self::NAME,
      $event->collectionBuilder,
      $event->taskDefinitions,
    );
  }

  protected function validate(InputInterface $input, OutputInterface $output): self {
    $packageNames = $this
      ->packageHandler
      ->normalizePackageNames($input->getArgument('packageNames'));

    $packages = $this->getPackages();
    $unknownPackageNames = array_diff(
      $packageNames,
      array_keys($packages),
    );

    if ($unknownPackageNames) {
      throw new \InvalidArgumentException(sprintf(
        'Unknown packages: %s',
        implode(', ', $unknownPackageNames),
      ));
    }

    return $this;
  }

  protected function getTaskDefsInitStateDataCustom(CommandEvent $event): array {
    $task = new CallableTask(
      function (RoboState $state): int {
        // @todo $state['baseUrl'].
        $state['baseUrl'] = 'http://my-dummy.com';

        return 0;
      },
      $event->collectionBuilder,
    );

    return [
      'Init-StateDataCustom.marvin_phpunit_incubator' => [
        'weight' => -998,
        'description' => 'Initialize state data.',
        'task' => $task,
      ],
    ];
  }

  /**
   * @todo Split "Generate" and "Write" into individual tasks.
   */
  protected function getTaskDefsPhpunitGenerateConfig(CommandEvent $event): array {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Generate PHPUnit configuration XML for package {key}')
      ->deferTaskConfiguration('setIterable', 'packages')
      ->withBuilder(function(
        CollectionBuilder $builder,
        string $packageName,
        $package,
      ) use ($taskForEach): void {
        $state = $taskForEach->getState();

        $projectRootDir = $this->getProjectRootDir();
        $composerInfo = ComposerInfo::create(
          $projectRootDir,
          $this->utils->getComposerJsonFileName(),
        );
        $drupalRootDir = $composerInfo->getDrupalRootDir();
        // @todo Configurable.
        $mdeDir = "$projectRootDir/managedDrupalExtension";

        $builder
          ->addTask(
            // @phpstan-ignore-next-line
            $this
              ->taskMarvinPhpunitConfigGenerator()
              ->setBaseUrl($state['baseUrl'])
              ->setDrupalRoot($drupalRootDir)
              ->setProjectVendor($package['nameVendor'])
              ->setProjectName($package['nameProject'])
              ->setProjectRelativePath($package['pathRelative'])
          )
          ->addTask(
            // @todo This creates the required directories as well, but with wrong permissions.
            // @phpstan-ignore-next-line
            $this
              ->taskWriteToFile("$mdeDir/{$package['nameProject']}/phpunit.xml")
              ->deferTaskConfiguration('text', 'phpunitConfig')
          );
      });

    return [
      'Generate-PhpunitConfig.marvin_phpunit_incubator' => [
        'weight' => 0,
        'description' => 'Generates phpunit.xml files for managed Drupal extensions.',
        'task' => $taskForEach,
      ],
    ];
  }

}
