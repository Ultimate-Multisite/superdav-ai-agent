<?php
/**
 * Plugin class
 *
 * @since 1.0.0
 * @version 1.0.0
 */

namespace Pondermatic\ComposerArchiveProject;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

/**
 * The main plugin class.
 *
 * @since 1.0.0
 */
class Plugin implements Capable, PluginInterface
{
    /**
     * @inheritDoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $composer->getArchiveManager()->addArchiver(new ZipArchiverProject());
        $composer->getArchiveManager()->addArchiver(new PharArchiverProject());
    }

    /**
     * @inheritDoc
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * @inheritDoc
     */
    public function getCapabilities()
    {
        return [
            'Composer\Plugin\Capability\CommandProvider' => 'Pondermatic\ComposerArchiveProject\ArchiveProjectCommand',
        ];
    }

    /**
     * @inheritDoc
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }
}
