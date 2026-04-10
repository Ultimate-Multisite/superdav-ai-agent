<?php
/**
 * ArchiveProjectCommand class
 *
 * @since 1.0.0
 * @version 1.0.0
 */

namespace Pondermatic\ComposerArchiveProject;

use Closure;
use Composer\Command\ArchiveCommand;
use Composer\Composer;
use Composer\Config;
use Composer\Console\Input\InputArgument;
use Composer\Console\Input\InputOption;
use Composer\Factory;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\Loop;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function count;
use function strlen;

/**
 * A Composer command that creates an archive of a composer package with a root project directory.
 *
 * @see https://github.com/composer/composer/blob/2.4.1/src/Composer/Command/ArchiveCommand.php
 * @since 1.0.0
 */
class ArchiveProjectCommand extends ArchiveCommand implements CommandProvider
{
    /**
     * Supported archive formats.
     *
     * This is a copy of {@see ArchiveCommand::FORMATS}
     * because it is a private constant and used by {@see ArchiveProjectCommand::configure()}.
     * @since 1.0.0
     */
    private const FORMATS = ['tar', 'tar.gz', 'tar.bz2', 'zip'];

    /**
     * The name from the composer.json package file.
     *
     * @since 1.0.0
     * @var string
     */
    protected static $packageName;

	/**
	 * An array of symlinks that will be swapped with real files during the archive process.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	protected $symlinks = [];

    /**
     * Constructor.
     *
     * Composer plugin command constructors are given an array of arguments,
     * unlike native Composer command constructors which accept a string name.
     *
     * @param array $args {
     *     Objects the plugin may need.
     *
     *     @type Composer  $composer The main Composer object.
     *     @type ConsoleIO $io       The Input/Output helper.
     *     @type Plugin    $plugin   The main plugin object.
     * }
     * @since 1.0.0
     */
    public function __construct($args = [])
    {
		if ( isset($args['composer'])) {
            self::$packageName = $args['composer']->getPackage()->getName();
        } else {
            parent::__construct();
		}
    }

    /**
     * @inheritdoc
     * @see ArchiveCommand::archive()
     * @since 1.0.0
     */
    protected function archive(IOInterface $io, Config $config, ?string $packageName, ?string $version, string $format, string $dest, ?string $fileName, bool $ignoreFilters, ?Composer $composer): int
    {
        if ($composer) {
            $archiveManager = $composer->getArchiveManager();
        } else {
            $factory = new Factory;
            $process = new ProcessExecutor();
            $httpDownloader = Factory::createHttpDownloader($io, $config);
            $downloadManager = $factory->createDownloadManager($io, $config, $httpDownloader, $process);
            $archiveManager = $factory->createArchiveManager($config, $downloadManager, new Loop($httpDownloader, $process));
        }

        if ($packageName) {
            $package = $this->selectPackage($io, $packageName, $version);

            if (!$package) {
                return 1;
            }
        } else {
            $package = $this->requireComposer()->getPackage();
        }

		$this->swapSymlinks($this->symlinks);

        $io->writeError('<info>Creating the archive into "'.$dest.'".</info>');
        $packagePath = $archiveManager->archive($package, "project-$format", $dest, $fileName, $ignoreFilters);
        $fs = new Filesystem;
        $fs->rename(
            $packagePath, 
            substr($packagePath, 0, strrpos($packagePath, 'project-')) . $format
        );
        $shortPath = $fs->findShortestPath(Platform::getCwd(), $packagePath, true);

        $io->writeError('Created: ', false);
        $io->write(strlen($shortPath) < strlen($packagePath) ? $shortPath : $packagePath);

		$this->restoreSymlinks($this->symlinks);

        return 0;
    }

    /**
     * @inheritdoc
     * @see ArchiveCommand::configure()
     * @since 1.0.0
     */
    protected function configure(): void
    {
        $this
            ->setName( 'archive-project' )
            ->setDescription( 'Creates an archive of this composer package with a root project directory' )
            ->setDefinition([
                new InputArgument('package', InputArgument::OPTIONAL, 'The package to archive instead of the current project', null, $this->suggestAvailablePackage()),
                new InputArgument('version', InputArgument::OPTIONAL, 'A version constraint to find the package to archive'),
                new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Format of the resulting archive: tar, tar.gz, tar.bz2 or zip (default tar)', null, self::FORMATS),
                new InputOption('dir', null, InputOption::VALUE_REQUIRED, 'Write the archive to this directory'),
                new InputOption('file', null, InputOption::VALUE_REQUIRED, 'Write the archive with the given file name.'
                    .' Note that the format will be appended.'),
                new InputOption('ignore-filters', null, InputOption::VALUE_NONE, 'Ignore filters when saving package'),
				new InputOption(
					'swap-symlink',
					null,
					InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
					'Temporarily renames the given symlink and then copies the real files to its place. Composer archive ignores symlinks.',
					[]
				)
            ])
            ->setHelp(
                <<<EOT
The <info>archive-project</info> command creates an archive of the specified format
containing the files and directories of the Composer project or the specified
package in the specified version and writes it to the specified directory.

<info>php composer.phar archive-project [--format=zip] [--dir=/foo] [--file=filename] [package [version]]</info>

Read more at https://getcomposer.org/doc/03-cli.md#archive
EOT
            )
        ;
    }

    /**
     * @inheritdoc
     * @see ArchiveCommand::execute()
     * @since 1.0.0
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->tryComposer();
        $config = null;

        if ($composer) {
            $config = $composer->getConfig();
            $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'archive-project', $input, $output);
            $eventDispatcher = $composer->getEventDispatcher();
            $eventDispatcher->dispatch($commandEvent->getName(), $commandEvent);
            $eventDispatcher->dispatchScript(ScriptEvents::PRE_ARCHIVE_CMD);
        }

        if (!$config) {
            $config = Factory::createConfig();
        }

        $format = $input->getOption('format') ?? $config->get('archive-format');
        $dir = $input->getOption('dir') ?? $config->get('archive-dir');

		$this->symlinks = $input->getOption('swap-symlink');

        $returnCode = $this->archive(
            $this->getIO(),
            $config,
            $input->getArgument('package'),
            $input->getArgument('version'),
            $format,
            $dir,
            $input->getOption('file'),
            $input->getOption('ignore-filters'),
            $composer
        );

        if (0 === $returnCode && $composer) {
            $composer->getEventDispatcher()->dispatchScript(ScriptEvents::POST_ARCHIVE_CMD);
        }

        return $returnCode;
    }

    /**
     * @inheritDoc
     * @since 1.0.0
     */
    public function getCommands()
    {
        return [
            new ArchiveProjectCommand(),
        ];
    }

    /**
     * Returns the name of the composer package being archived.
     *
     * @since 1.0.0
     * @return string
     */
    public static function getPackageName(): string
    {
        return self::$packageName;
    }

	/**
	 * Deletes the copied target files and restores the symlinks.
	 *
	 * @since 1.0.0
	 * @param string[] $symlinks
	 * @return void
	 */
	protected function restoreSymlinks(array $symlinks):void
	{
		$filesystem = new Filesystem();

		foreach ($symlinks as $symlink) {
			if (is_link($symlink) === true || file_exists("$symlink-symlink") === false) {
				continue;
			}

			$filesystem->removeDirectory($symlink);
			rename("$symlink-symlink", $symlink);
		}
	}

	/**
	 * Renames the given symlinks and copies the real files in their place.
	 *
	 * The Composer archive command ignores symlinks.
	 *
	 * @since 1.0.0
	 * @param string[] $symlinks
	 * @return void
	 */
	protected function swapSymlinks(array $symlinks):void
	{
		$filesystem = new Filesystem();

		foreach ($symlinks as $symlink) {
			if (is_link($symlink) === false) {
				continue;
			}

			$target = realpath($symlink);
			rename($symlink, "$symlink-symlink");
			$filesystem->copy($target, $symlink);
		}
	}

	/**
     * Suggest package names available on all configured repositories.
     *
     * This is a copy of {@see ArchiveCommand::suggestAvailablePackage()}
     * because it is a private method and used by {@see ArchiveProjectCommand::configure()}.
     * @since 1.0.0
     */
    private function suggestAvailablePackage(int $max = 99): Closure
    {
        return function (CompletionInput $input) use ($max): array {

            if ($max < 1) {
                return [];
            }

            $composer = $this->requireComposer();
            $repos    = new CompositeRepository($composer->getRepositoryManager()->getRepositories());

            $results     = [];
            $showVendors = false;
            if ( ! str_contains($input->getCompletionValue(), '/')) {
                $results     = $repos->search('^' . preg_quote($input->getCompletionValue()), RepositoryInterface::SEARCH_VENDOR);
                $showVendors = true;
            }

            // if we get a single vendor, we expand it into its contents already
            if (count($results) <= 1) {
                $results     = $repos->search('^' . preg_quote($input->getCompletionValue()), RepositoryInterface::SEARCH_NAME);
                $showVendors = false;
            }

            $results = array_column($results, 'name');

            if ($showVendors) {
                $results = array_map(static function (string $name): string {
                    return $name . '/';
                }, $results);

                // sort shorter results first to avoid auto-expanding the completion to a longer string than needed
                usort($results, static function (string $a, string $b) {
                    $lenA = strlen($a);
                    $lenB = strlen($b);
                    if ($lenA === $lenB) {
                        return $a <=> $b;
                    }

                    return $lenA - $lenB;
                });

                $pinned = [];

                // ensure if the input is an exact match that it is always in the result set
                $completionInput = $input->getCompletionValue() . '/';
                if (false !== ($exactIndex = array_search($completionInput, $results, true))) {
                    $pinned[] = $completionInput;
                    array_splice($results, $exactIndex, 1);
                }

                return array_merge($pinned, array_slice($results, 0, $max - count($pinned)));
            }

            return array_slice($results, 0, $max);
        };
    }
}
