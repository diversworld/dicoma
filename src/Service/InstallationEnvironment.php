<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class InstallationEnvironment
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly Filesystem $filesystem,
    ) {}

    public function complete(int $clubId, string $mailFrom): void
    {
        $path = $this->projectDir.'/.env.local';
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        foreach ([
            'SITE_THEME' => 'airspace',
            'PUBLIC_SITE_CLUB_ID' => (string) $clubId,
            'BAR_DUTY_MAIL_FROM' => '"'.$this->escape($mailFrom).'"',
            'DICOMA_SINGLE_CLUB_MODE' => '1',
            'CONTACT_MAIL_FROM' => '"${BAR_DUTY_MAIL_FROM}"',
            'CLUB_FUNCTION_MEMBERSHIP_ENABLED' => '1',
            'DICOMA_INSTALL_ENABLED' => '1',
            'DICOMA_INSTALL_COMPLETED' => '1',
        ] as $name => $value) {
            $content = $this->replace($content, $name, $value);
        }
        if (!preg_match('/^DICOMA_MEMBERSHIP_BANK_KEY=/m', $content)) {
            $content = $this->replace(
                $content,
                'DICOMA_MEMBERSHIP_BANK_KEY',
                '"'.base64_encode(random_bytes(32)).'"',
            );
        }
        $this->filesystem->dumpFile($path, $content);
        $this->filesystem->chmod($path, 0600);
    }

    private function replace(string $content, string $name, string $value): string
    {
        $line = $name.'='.$value;
        if (preg_match('/^'.preg_quote($name, '/').'=.*$/m', $content)) {
            return (string) preg_replace('/^'.preg_quote($name, '/').'=.*$/m', $line, $content);
        }
        return rtrim($content)."\n".$line."\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
