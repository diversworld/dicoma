<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

class SoftwareReleaseArchive
{
    public function __construct(
        #[Autowire(env: 'DICOMA_UPDATE_REPOSITORY')] private readonly string $repository,
    ) {}

    /**
     * @return list<string> Validated release paths relative to $directory.
     */
    public function prepare(string $commit, string $directory): array
    {
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#D', $this->repository)
            || !preg_match('/^[a-f0-9]{40}$/D', $commit)) {
            throw new \RuntimeException('Ungültiges Update-Repository oder ungültiger Release-Commit.');
        }
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Die PHP-Erweiterung zip wird für das Update benötigt.');
        }
        $archive = $directory.'/release.zip';
        $stage = $directory.'/release';
        (new Filesystem())->mkdir($stage, 0700);
        $this->download($commit, $archive);

        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::CHECKCONS) !== true) {
            throw new \RuntimeException('Das Release-Archiv ist ungültig oder beschädigt.');
        }
        $files = [];
        $root = null;
        $totalSize = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $entry = $zip->statIndex($i);
                if ($entry === false || !isset($entry['name'], $entry['size'])) {
                    throw new \RuntimeException('Ein Eintrag des Release-Archivs ist ungültig.');
                }
                $name = $entry['name'];
                if (str_contains($name, '\\') || str_starts_with($name, '/') || str_contains($name, "\0")) {
                    throw new \RuntimeException('Das Release-Archiv enthält einen ungültigen Pfad.');
                }
                $parts = explode('/', rtrim($name, '/'));
                if (count($parts) < 1 || in_array('', $parts, true) || in_array('.', $parts, true) || in_array('..', $parts, true)) {
                    throw new \RuntimeException('Das Release-Archiv enthält einen ungültigen Pfad.');
                }
                $root ??= $parts[0];
                if ($parts[0] !== $root) {
                    throw new \RuntimeException('Das Release-Archiv enthält mehrere Stammverzeichnisse.');
                }
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                    && $opsys === \ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new \RuntimeException('Symbolische Links im Release-Archiv sind nicht zulässig.');
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (count($parts) < 2) {
                    throw new \RuntimeException('Das Release-Archiv enthält eine Datei außerhalb des Stammverzeichnisses.');
                }
                $relative = implode('/', array_slice($parts, 1));
                if (isset($files[$relative])) {
                    throw new \RuntimeException('Das Release-Archiv enthält doppelte Dateipfade.');
                }
                $size = (int) $entry['size'];
                $totalSize += $size;
                if ($size > 256 * 1024 * 1024 || $totalSize > 1024 * 1024 * 1024 || count($files) >= 50000) {
                    throw new \RuntimeException('Das Release-Archiv überschreitet die zulässige Größe.');
                }
                $target = $stage.'/'.$relative;
                (new Filesystem())->mkdir(dirname($target), 0700);
                $input = $zip->getStream($name);
                $output = fopen($target, 'xb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) { fclose($input); }
                    if (is_resource($output)) { fclose($output); }
                    throw new \RuntimeException('Eine Release-Datei konnte nicht vorbereitet werden.');
                }
                try {
                    if (stream_copy_to_stream($input, $output) !== $size) {
                        throw new \RuntimeException('Eine Release-Datei konnte nicht vollständig gelesen werden.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                $mode = $opsys === \ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0111) !== 0 ? 0755 : 0644;
                if (!chmod($target, $mode)) {
                    throw new \RuntimeException('Dateirechte des Release-Archivs konnten nicht gesetzt werden.');
                }
                $files[$relative] = true;
            }
        } finally {
            $zip->close();
        }
        foreach (['VERSION', 'composer.json', 'composer.lock', 'bin/console'] as $required) {
            if (!isset($files[$required])) {
                throw new \RuntimeException('Im Release-Archiv fehlt die benötigte Datei '.$required.'.');
            }
        }
        return array_keys($files);
    }

    protected function download(string $commit, string $destination): void
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('Die PHP-cURL-Erweiterung wird für das Update benötigt.');
        }
        $file = fopen($destination, 'xb');
        if ($file === false) {
            throw new \RuntimeException('Das Release-Archiv konnte nicht angelegt werden.');
        }
        $handle = curl_init('https://api.github.com/repos/'.$this->repository.'/zipball/'.$commit);
        if ($handle === false) {
            fclose($file);
            throw new \RuntimeException('Der Release-Download konnte nicht gestartet werden.');
        }
        $bytes = 0;
        try {
            if (!curl_setopt_array($handle, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'User-Agent: DiCoMa-Updater'],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $data) use ($file, &$bytes): int {
                    $bytes += strlen($data);
                    if ($bytes > 256 * 1024 * 1024) { return 0; }
                    return fwrite($file, $data) ?: 0;
                },
            ])) {
                throw new \RuntimeException('Der Release-Download konnte nicht konfiguriert werden.');
            }
            $ok = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($ok === false || $status < 200 || $status >= 300) {
                throw new \RuntimeException(sprintf('Release-Download fehlgeschlagen (HTTP %d).', $status));
            }
        } finally {
            curl_close($handle);
            fclose($file);
        }
    }
}
