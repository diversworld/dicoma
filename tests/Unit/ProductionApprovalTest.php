<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Club;
use App\Entity\ProductionApproval;
use PHPUnit\Framework\TestCase;

final class ProductionApprovalTest extends TestCase
{
    public function testApprovalRequiresAreaActorAndEvidence(): void
    {
        $approval = new ProductionApproval((new Club())->setName('Approval'));
        $approval->approve('sepa', 'bank-reviewer', 'Bankticket B-123');

        self::assertSame('bank-reviewer', $approval->getSepaApprovedBy());
        self::assertSame('Bankticket B-123', $approval->getSepaEvidence());
        self::assertNotNull($approval->getSepaApprovedAt());

        $this->expectException(\DomainException::class);
        $approval->approve('chart', '', '');
    }
}
