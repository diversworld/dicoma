<?php

declare(strict_types=1);

namespace App\Controller;

trait CompressorAccessTrait
{
    private function denyUnlessCompressorViewer(): void
    {
        if (!$this->isGranted('ROLE_EQUIPMENT_MANAGER') && !$this->isGranted('ROLE_COMPRESSOR_OPERATOR')) {
            throw $this->createAccessDeniedException();
        }
    }
}
