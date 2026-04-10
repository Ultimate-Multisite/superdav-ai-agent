<?php
/**
 * ZipArchiverProject class
 *
 * @since 1.0.0
 * @version 1.0.0
 */

namespace Pondermatic\ComposerArchiveProject;

use Composer\Package\Archiver\ArchivableFilesFinder;
use Composer\Package\Archiver\ZipArchiver;
use Composer\Util\Filesystem;
use ZipArchive;

/**
 * A ZIP archiver with a root project directory.
 *
 * This is similar to the {@see \Composer\Package\Archiver\ZipArchiver Composer zip archiver},
 * but adds a root directory with the project name from the package name.
 * For example, if the `name` field in `composer.json` is `pondermatic/xapi`, then the archive hierarchy would be
 *     .
 *     |-- xapi/
 *     |   |-- src/
 *     |   |   |-- main.php
 *     |   |-- composer.json
 *     |   |-- readme.md
 * instead of
 *     .
 *     |-- src/
 *     |   |-- main.php
 *     |-- composer.json
 *     |-- readme.md
 * @see https://github.com/composer/composer/blob/2.4.1/src/Composer/Package/Archiver/ZipArchiver.php
 * @since 1.0.0
 */
class ZipArchiverProject extends ZipArchiver
{
    /**
     * @inheritDoc
     * @since 1.0.0
     */
    public function archive(string $sources, string $target, string $format, array $excludes = [], bool $ignoreFilters = false): string
    {
        $fs = new Filesystem();
        $sources = $fs->normalizePath($sources);
		$packageName = ArchiveProjectCommand::getPackageName();
        $projectName = substr($packageName, strrpos($packageName, '/') + 1);

        $zip = new ZipArchive();
        $res = $zip->open($target, ZipArchive::CREATE);
        if ($res === true) {
            $files = new ArchivableFilesFinder($sources, $excludes, $ignoreFilters);
            foreach ($files as $file) {
                /** @var \SplFileInfo $file */
                $filepath = strtr($file->getPath() . '/' . $file->getFilename(), '\\', '/');
                $localname = $filepath;
                if (strpos($localname, $sources . '/') === 0) {
                    $localname = $projectName . '/' . substr($localname, strlen($sources . '/'));
                }
                if ($file->isDir()) {
                    $zip->addEmptyDir($localname);
                } else {
                    $zip->addFile($filepath, $localname);
                }

                /**
                 * setExternalAttributesName() is only available with libzip 0.11.2 or above
                 */
                if (method_exists($zip, 'setExternalAttributesName')) {
                    $perms = fileperms($filepath);

                    /**
                     * Ensure to preserve the permission umasks for the filepath in the archive.
                     */
                    $zip->setExternalAttributesName($localname, ZipArchive::OPSYS_UNIX, $perms << 16);
                }
            }
            if ($zip->close()) {
                return $target;
            }
        }
        $message = sprintf(
            "Could not create archive '%s' from '%s': %s",
            $target,
            $sources,
            $zip->getStatusString()
        );
        throw new \RuntimeException($message);
    }

    /**
     * Returns true if Zip compression is available, else false.
     *
     * This is a copy of {@see ZipArchiver::compressionAvailable()}
     * because it is a private method and used by {@see ArchiverInterface::supports()}.
     * @since 1.0.0
     */
    private function compressionAvailable(): bool
    {
        return class_exists('ZipArchive');
    }

    /**
     * @inheritDoc
     */
    public function supports(string $format, ?string $sourceType): bool
    {
        $format = substr($format, strlen('project-'));
        return isset(static::$formats[$format]) && $this->compressionAvailable();
    }
}
