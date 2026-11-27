<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class GitVersionChecker
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire(service: 'cache.app')] private readonly CacheInterface $cache,
        #[Autowire(env: 'DICOMA_UPDATE_REPOSITORY')] private readonly string $repository,
    ) {}

    /** @return array{installed:string,latest:?string,tag:?string,commit:?string,available:bool,checked:bool,url:string,error:?string} */
    public function check(bool $refresh = false): array
    {
        $installed = $this->installedVersion();
        $url = 'https://github.com/'.$this->repository.'/tags';
        $cacheKey = 'dicoma_git_version_v2_'.hash('sha256', $this->repository);
        if ($refresh) {
            $this->cache->delete($cacheKey);
        }
        $result = $this->cache->get($cacheKey, function (ItemInterface $item): array {
            $item->expiresAfter(6 * 60 * 60);
            return $this->fetchLatestTag();
        });

        if (isset($result['error'])) {
            return [
                'installed' => $installed,
                'latest' => null,
                'tag' => null,
                'commit' => null,
                'available' => false,
                'checked' => false,
                'url' => $url,
                'error' => $result['error'],
            ];
        }

        $latest = (string) $result['version'];
        return [
            'installed' => $installed,
            'latest' => $latest,
            'tag' => $result['tag'],
            'commit' => $result['commit'],
            'available' => version_compare($latest, $installed, '>'),
            'checked' => true,
            'url' => $url,
            'error' => null,
        ];
    }

    public static function normalize(string $tag): ?string
    {
        if (!preg_match('/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)(?:\.(0|[1-9]\d*))?$/', trim($tag), $matches)) {
            return null;
        }
        return sprintf('%s.%s.%s', $matches[1], $matches[2], $matches[3] ?? '0');
    }

    /** @return array{version:string,tag:string,commit:string}|array{error:string} */
    private function fetchLatestTag(): array
    {
        if (!extension_loaded('curl')) {
            return ['error' => 'Die PHP-cURL-Erweiterung ist für die Versionsprüfung nicht verfügbar.'];
        }
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $this->repository)) {
            return ['error' => 'DICOMA_UPDATE_REPOSITORY ist ungültig. Erwartet wird owner/repository.'];
        }

        $handle = curl_init('https://api.github.com/repos/'.$this->repository.'/tags?per_page=100');
        if ($handle === false) {
            return ['error' => 'Die Versionsprüfung konnte nicht gestartet werden.'];
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: DiCoMa-Update-Checker',
                'X-GitHub-Api-Version: 2022-11-28',
            ],
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            return ['error' => $error !== '' ? 'GitHub war nicht erreichbar.' : sprintf('GitHub antwortete mit HTTP %d.', $status)];
        }

        try {
            $tags = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['error' => 'Die Antwort von GitHub konnte nicht gelesen werden.'];
        }
        if (!is_array($tags)) {
            return ['error' => 'GitHub lieferte keine gültige Tag-Liste.'];
        }

        $latest = null;
        foreach ($tags as $tag) {
            $normalized = is_array($tag) && is_string($tag['name'] ?? null) ? self::normalize($tag['name']) : null;
            $commit = is_array($tag) && is_array($tag['commit'] ?? null) ? ($tag['commit']['sha'] ?? null) : null;
            if ($normalized !== null && is_string($commit) && preg_match('/^[a-f0-9]{40}$/D', $commit)
                && ($latest === null || version_compare($normalized, $latest['version'], '>'))) {
                $latest = ['version' => $normalized, 'tag' => $tag['name'], 'commit' => $commit];
            }
        }
        return $latest === null
            ? ['error' => 'Im Repository wurde kein stabiler Versions-Tag wie 1.2.3 gefunden.']
            : $latest;
    }

    private function installedVersion(): string
    {
        $path = $this->projectDir.'/VERSION';
        if (!is_file($path)) {
            return '0.0.0';
        }
        return self::normalize((string) file_get_contents($path)) ?? '0.0.0';
    }
}
