<?php
/**
 * PharArchiverProject class
 *
 * @since 1.0.0
 * @version 1.0.0
 */

namespace Pondermatic\ComposerArchiveProject;

use Composer\Package\Archiver\ArchivableFilesFilter;
use Composer\Package\Archiver\ArchivableFilesFinder;
use Composer\Package\Archiver\PharArchiver;

/**
 * A tar archiver with a root project directory.
 *
 * This is similar to the {@see \Composer\Package\Archiver\PharArchiver Composer phar archiver},
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
 * @see https://github.com/composer/composer/blob/2.4.1/src/Composer/Package/Archiver/PharArchiver.php
 * @since 1.0.0
 */
class PharArchiverProject extends PharArchiver
{
    /**
     * @inheritDoc
     * @since 1.0.0
     */
    public function archive(string $sources, string $target, string $format, array $excludes = [], bool $ignoreFilters = false): string
    {
        // Remove 'project-'.
        $format = substr($format, strlen('project-'));
        $target = substr($target, 0, strrpos($target, 'project-')) . $format;
        
        $sources = realpath($sources);

        // Phar would otherwise load the file which we don't want
        if (file_exists($target)) {
            unlink($target);
        }

        try {
            $filename = substr($target, 0, strrpos($target, $format) - 1);

            // Check if compress format
            if (isset(static::$compressFormats[$format])) {
                // Current compress format supported base on tar
                $target = $filename . '.tar';
            }

            $phar = new \PharData(
                $target,
                \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO,
                '',
                static::$formats[$format]
            );
            $files = new ArchivableFilesFinder($sources, $excludes, $ignoreFilters);
            $filesOnly = new ArchivableFilesFilter($files);
            $phar->buildFromIterator($filesOnly, dirname($sources));
            $filesOnly->addEmptyDir($phar, $sources);

            if (isset(static::$compressFormats[$format])) {
                // Check can be compressed?
                if (!$phar->canCompress(static::$compressFormats[$format])) {
                    throw new \RuntimeException(sprintf('Can not compress to %s format', $format));
                }

                // Delete old tar
                unlink($target);

                // Compress the new tar
                $phar->compress(static::$compressFormats[$format]);

                // Make the correct filename
                $target = $filename . '.' . $format;
            }

            return $target;
        } catch (\UnexpectedValueException $e) {
            $message = sprintf(
                "Could not create archive '%s' from '%s': %s",
                $target,
                $sources,
                $e->getMessage()
            );

            throw new \RuntimeException($message, $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function supports(string $format, ?string $sourceType): bool
    {
        $format = substr($format, strlen('project-'));
        return isset(static::$formats[$format]);
    }
}
