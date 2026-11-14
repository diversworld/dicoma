<?php

declare(strict_types=1);

namespace App\Service;

final class PrivateStorageMigration
{
    public static function activate(string $root, string $targetName, string $legacyPath, string $markerName, string $label): string
    {
        $parent = $root.'/files';
        $target = $parent.'/'.$targetName;
        foreach ([$parent, $target] as $directory) {
            if (is_link($directory)) {
                throw new \RuntimeException('Private Dateiablage darf kein symbolischer Link sein.');
            }
            if (!is_dir($directory) && !mkdir($directory, 0700) && !is_dir($directory)) {
                throw new \RuntimeException('Private Dateiablage nicht verfügbar.');
            }
            $permissions = fileperms($directory);
            if ($permissions === false || ($permissions & 0077) !== 0 || !is_writable($directory)) {
                throw new \RuntimeException('Private Dateiablage ist nicht schreibbar oder nicht ausreichend geschützt.');
            }
        }
        $marker = $parent.'/'.$markerName;
        if (is_link($marker) || (file_exists($marker) && !is_file($marker))) {
            throw new \RuntimeException('Ungültige Markierung der Datei-Umstellung.');
        }
        if (!is_file($marker)) {
            self::verifyLegacyFiles($root.'/'.$legacyPath, $target, $label);
            $handle = @fopen($marker, 'x');
            if ($handle === false) {
                if (!is_file($marker) || is_link($marker)) {
                    throw new \RuntimeException('Datei-Umstellung konnte nicht markiert werden.');
                }
            } else {
                fclose($handle);
            }
        }
        return $target;
    }

    private static function verifyLegacyFiles(string $legacy, string $target, string $label): void
    {
        if (is_link($legacy)) {
            throw new \RuntimeException('Das alte Dateiverzeichnis darf kein symbolischer Link sein.');
        }
        if (!file_exists($legacy)) {
            return;
        }
        if (!is_dir($legacy) || !is_readable($legacy)) {
            throw new \RuntimeException('Das alte Dateiverzeichnis ist nicht lesbar.');
        }
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($legacy, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($entries as $entry) {
            if ($entry->isLink() || (!$entry->isDir() && !$entry->isFile())) {
                throw new \RuntimeException('Ungültiger Eintrag im alten Dateiverzeichnis.');
            }
            $relative = substr($entry->getPathname(), strlen($legacy) + 1);
            $destination = $target;
            foreach (explode('/', $relative) as $part) {
                $destination .= '/'.$part;
                if (is_link($destination)) {
                    throw new \RuntimeException('Symbolischer Link im Dateiziel.');
                }
            }
            if ($entry->isDir()) {
                if (!is_dir($destination)) {
                    throw new \RuntimeException($label.' müssen vor der Umstellung vollständig übernommen werden.');
                }
                continue;
            }
            $sourceHash = hash_file('sha256', $entry->getPathname());
            if ($sourceHash === false || !is_file($destination) || hash_file('sha256', $destination) !== $sourceHash
                || hash_file('sha256', $entry->getPathname()) !== $sourceHash) {
                throw new \RuntimeException($label.' müssen vor der Umstellung vollständig und unverändert übernommen werden.');
            }
        }
    }
}
