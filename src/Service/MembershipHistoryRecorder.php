<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Member;
use App\Entity\MembershipHistory;
use Doctrine\ORM\EntityManagerInterface;

final class MembershipHistoryRecorder
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @param array<string, array{from:mixed,to:mixed}> $changes */
    public function record(Member $member, string $actor, string $action, array $changes): void
    {
        $normalized = [];
        foreach ($changes as $field => $change) {
            if ($change['from'] !== $change['to']) {
                $normalized[$field] = ['from' => self::value($change['from']), 'to' => self::value($change['to'])];
            }
        }
        if ($normalized !== []) {
            $this->em->persist(new MembershipHistory($member, $actor, $action, $normalized));
        }
    }

    private static function value(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) { return $value->format('Y-m-d H:i:s'); }
        if (is_object($value) && method_exists($value, 'getId')) { return $value->getId(); }
        return is_scalar($value) || $value === null ? $value : (string) $value;
    }
}
