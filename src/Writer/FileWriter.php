<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Writer;

use Juf\DoctrineImportBundle\Rendering\GeneratedFile;

/**
 * Writes generated classes in the directory of their namespace. Existing files are kept unless forced.
 */
final class FileWriter
{
    /**
     * @param list<GeneratedFile>  $files
     * @param list<OutputLocation> $locations
     *
     * @return list<WrittenFile>
     *
     * @throws UnmappedClassException
     * @throws WriteFailedException
     */
    public function write(array $files, array $locations, WriteOptions $options): array
    {
        $written = [];
        foreach ($files as $file) {
            $path = $this->path($file->className, $locations);
            $status = match (true) {
                !is_file($path) => FileStatus::Created,
                $options->force => FileStatus::Overwritten,
                default => FileStatus::Skipped,
            };

            if (!$options->dryRun && FileStatus::Skipped !== $status) {
                $this->save($path, $file->contents);
            }

            $written[] = new WrittenFile($file->className, $path, $status);
        }

        return $written;
    }

    /**
     * @param list<OutputLocation> $locations
     *
     * @throws UnmappedClassException
     */
    private function path(string $className, array $locations): string
    {
        foreach ($locations as $location) {
            $prefix = $location->namespace.'\\';
            if (str_starts_with($className, $prefix)) {
                return $location->directory.'/'.str_replace('\\', '/', substr($className, \strlen($prefix))).'.php';
            }
        }

        throw UnmappedClassException::forClass($className, array_map(static fn (OutputLocation $location): string => $location->namespace, $locations));
    }

    /**
     * @throws WriteFailedException
     */
    private function save(string $path, string $contents): void
    {
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            $this->assertWritable($this->closestExistingDirectory($directory), $path);
            mkdir($directory, recursive: true);
        }

        $this->assertWritable(is_file($path) ? $path : $directory, $path);
        file_put_contents($path, $contents);
    }

    /**
     * @throws WriteFailedException
     */
    private function assertWritable(string $target, string $path): void
    {
        if (!is_writable($target)) {
            throw WriteFailedException::forPath($path);
        }
    }

    private function closestExistingDirectory(string $directory): string
    {
        while (!is_dir($directory)) {
            $directory = \dirname($directory);
        }

        return $directory;
    }
}
