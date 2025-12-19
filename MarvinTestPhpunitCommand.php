<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_phpunit_incubator;

use Drupal\marvin\CommandEvent;
use Drupal\marvin\ComposerInfo;
use Drupal\marvin\Lint\CommandEvent as LintCommandEvent;
use Drupal\marvin\Test\CommandEvent as TestCommandEvent;
use Drupal\marvin\MarvinTaskDefinitionCommandTrait;
use Drupal\marvin\Utils;
use Drupal\marvin_git\GitHook\CommandEvent as GitHookCommandEvent;
use Drupal\marvin_incubator\CommandsBaseTrait;
use Drupal\marvin_incubator\Package\Handler as PackageHandler;
use Drupal\marvin_incubator\Utils as IncubatorUtils;
use Drupal\marvin_phpunit_incubator\ContainerInitializer;
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
use Robo\Task\Base\Tasks as BaseTaskLoader;
use Robo\Task\File\Tasks as FileTaskLoader;
use Robo\TaskAccessor;
use Sweetchuck\Utils\Filter\EnabledFilter;
use Sweetchuck\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Runs PHPUnit for the given packages.',
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
class MarvinTestPhpunitCommand extends Command implements BuilderAwareInterface {

  use AutowireTrait {
    create as protected autowireCreate;
  }
  use TaskAccessor;
  use BaseTaskLoader;
  use FileTaskLoader;
  use LoopTaskLoader;
  use CommandsBaseTrait;
  use MarvinTaskDefinitionCommandTrait;

  public const string NAME = 'marvin:test:phpunit';

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
    #[Autowire(StringUtils::class)]
    protected StringUtils $stringUtils,
    #[Autowire(Utils::class)]
    protected Utils $utils,
    #[Autowire(IncubatorUtils::class)]
    protected IncubatorUtils $incubatorUtils,
    #[Autowire(PackageHandler::class)]
    protected PackageHandler $packageHandler,
    #[Autowire(LoggerInterface::class)]
    protected LoggerInterface $logger,
    #[Autowire(ContainerInterface::class)]
    protected ContainerInterface $container,
  ) {
    parent::__construct();

    $this->eventDispatcher->addListener(
      TestCommandEvent::EVENT_RUN_TASKS_COLLECT,
      $this->onEventMarvinTestTasksCollect(...),
    );

    $this->eventDispatcher->addListener(
      GitHookCommandEvent::EVENT_PRE_COMMIT_TASKS_COLLECT,
      $this->onEventMarvinGitHookPreCommitTasksCollect(...),
    );
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
    $event->taskDefinitions += $this->getTaskDefsRunPhpunit($event);

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

  public function onEventMarvinTestTasksCollect(LintCommandEvent $event): void {
    $event->taskDefinitions += $this->getTaskDefsRunPhpunit($event);
  }

  public function onEventMarvinGitHookPreCommitTasksCollect(GitHookCommandEvent $event): void {
    $event->taskDefinitions += $this->getTaskDefsRunPhpunit($event);
  }

  protected function getTaskDefsInitStateDataCustom(CommandEvent $event): array {
    $task = new CallableTask(
      function (RoboState $state): int {
        $state['primarySiteName'] = $this->getPrimarySiteName();

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
  protected function getTaskDefsRunPhpunit(CommandEvent $event): array {
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
        $primarySiteName = $state['primarySiteName'];
        $phpVariants = $this->getPhpVariants($package);
        $databaseVariants = $this->getDatabaseVariants($package);

        $urlPattern = $this->drushConfig->get('marvin.urlPattern');
        foreach ($phpVariants as $phpVariant) {
          foreach ($databaseVariants as $databaseVariant) {
            $envVars = $phpVariant['command']['envVars'] ?? [];
            $envVars['SIMPLETEST_BASE_URL'] = $this->getHttpUrl($urlPattern, $primarySiteName, $databaseVariant, $phpVariant);
            $envVars['SIMPLETEST_DB'] = $this->getDatabaseUrl($primarySiteName, $databaseVariant);
            $envVars = array_filter(
              $envVars,
              fn($value) => $value !== NULL,
            );

            $command = [
              $phpVariant['command']['executable'],
              ...($phpVariant['command']['arguments'] ?? []),
            ];
            $command[] = 'vendor/bin/phpunit';
            $command[] = "--configuration=managedDrupalExtension/{$package['nameProject']}/phpunit.xml";

            // @todo Escape shell args.
            $builder->addTask(
              $this
                ->taskExec(implode(' ', $command))
                ->envVars($envVars),
            );
          }
        }
      });

    return [
      'Execute-Phpunit.marvin_phpunit_incubator' => [
        'weight' => 0,
        'description' => 'Executes PHPUnit.',
        'task' => $taskForEach,
      ],
    ];
  }

  protected function getHttpUrl(
    string $urlPattern,
    string $siteName,
    array $databaseVariant,
    array $phpVariant,
  ): string {
    $replacements = [
      '{{ siteName }}' => $siteName,
      '{{ dbId }}' => $databaseVariant['id'],
      '{{ phpId }}' => $phpVariant['id'],
    ];

    return strtr($urlPattern, $replacements);
  }

  protected function getPrimarySiteName(): string {
    $composerInfo = ComposerInfo::create('.', $this->utils->getComposerJsonFileName());
    $drupalRootDir = $composerInfo->getDrupalRootDir();
    $siteDirs = $this->incubatorUtils->getSiteDirs("$drupalRootDir/sites");
    $siteNames = $this->incubatorUtils->getSiteNames($siteDirs);

    if (in_array('default', $siteNames, TRUE)) {
      return 'default';
    }

    if (count($siteNames) === 1) {
      return (string) reset($siteNames);
    }

    // @todo Or NULL?
    return 'default';
  }

  protected function getPhpVariants(array $package): array {
    $globalPhpVariants = (array) $this->drushConfig->get('marvin.php.variants');
    $packagePhpVariants = array_replace_recursive(
      (array) $this->drushConfig->get('marvin.mde.packages.default.phpunit.php.variants'),
      (array) $this->drushConfig->get("marvin.mde.packages.{$package['name']}.phpunit.php.variants"),
    );

    $packagePhpVariants = array_filter(
      $packagePhpVariants,
      new EnabledFilter(),
    );

    return array_intersect_key(
      $globalPhpVariants,
      $packagePhpVariants,
    );
  }

  protected function getDatabaseVariants(array $package): array {
    $globalPhpVariants = (array) $this->drushConfig->get('marvin.database.variants');
    $packagePhpVariants = array_replace_recursive(
      (array) $this->drushConfig->get('marvin.mde.packages.default.phpunit.database.variants'),
      (array) $this->drushConfig->get("marvin.mde.packages.{$package['name']}.phpunit.database.variants"),
    );

    $packagePhpVariants = array_filter(
      $packagePhpVariants,
      new EnabledFilter(),
    );

    return array_intersect_key(
      $globalPhpVariants,
      $packagePhpVariants,
    );
  }

  protected function getDatabaseUrl(
    string $siteName,
    array $databaseVariant,
  ): string {
    $composerInfo = ComposerInfo::create('.', $this->utils->getComposerJsonFileName());
    $drupalRootDir = $composerInfo->getDrupalRootDir();
    $projectRoot = $this->drushConfig->get('runtime.project');
    $outerSites = Path::join($projectRoot, $drupalRootDir, '..', 'sites');

    $replacements = [
      '{{ projectRoot }}' => $projectRoot,
      '{{ outerSites }}' => $outerSites,
      '{{ siteName }}' => $siteName,
      ...$this->utils->stringVariants($siteName, '{{ siteName', ' }}'),
      '{{ dbId }}' => $databaseVariant['id'],
      ...$this->utils->stringVariants($databaseVariant['id'], '{{ dbId', ' }}'),
    ];

    $pattern = $databaseVariant['connection']['database'];

    if ($databaseVariant['connection']['driver'] === 'sqlite') {
      return 'sqlite://' . strtr($pattern, $replacements);
    }

    return $this->stringUtils->buildUri([
      'scheme' => $databaseVariant['connection']['driver'],
      'user' => $databaseVariant['connection']['username'] ?? NULL,
      'pass' => $databaseVariant['connection']['password'] ?? NULL,
      'host' => $databaseVariant['connection']['host'] ?? NULL,
      'port' => $databaseVariant['connection']['port'] ?? NULL,
      'path' => strtr($pattern, $replacements),
    ]);
  }

}
