<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\Club;
use App\Entity\PrivacyPolicy;
use Doctrine\ORM\EntityManagerInterface;

final class AuditLogger
{
    private const SENSITIVE_KEYS = ['password', 'iban', 'bic', 'token', 'secret', 'key'];

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function record(Club $club, string $actor, string $action, object|string $subject, array $context = []): void
    {
        $policy = $this->em->getRepository(PrivacyPolicy::class)->findOneBy(['club' => $club]);
        $years = $policy?->getAuditRetentionYears() ?? 3;
        $type = is_object($subject) ? $subject::class : $subject;
        $id = is_object($subject) && method_exists($subject, 'getId') ? $subject->getId() : null;
        $this->em->persist(new AuditEvent(
            $club,
            $actor,
            $action,
            $type,
            $id === null ? null : (string) $id,
            self::redact($context),
            new \DateTimeImmutable('+'.$years.' years'),
        ));
    }

    private static function redact(array $value): array
    {
        foreach ($value as $key => &$item) {
            $sensitive = false;
            foreach (self::SENSITIVE_KEYS as $needle) {
                if (is_string($key) && str_contains(strtolower($key), $needle)) {
                    $sensitive = true;
                    break;
                }
            }
            if ($sensitive) {
                $item = '[redacted]';
            } elseif (is_array($item)) {
                $item = self::redact($item);
            }
        }
        return $value;
    }
}
