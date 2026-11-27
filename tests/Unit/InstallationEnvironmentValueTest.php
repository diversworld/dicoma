<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\InstallationEnvironmentValue;
use PHPUnit\Framework\TestCase;

final class InstallationEnvironmentValueTest extends TestCase
{
    public function testEncodedPasswordPercentSignsAreEscapedForSymfonyDotenvResolution(): void
    {
        $url = 'mysql://dicoma:08-Ei%26Gude%2B04@localhost:3306/dicoma?serverVersion=5.7.44-MySql&charset=utf8mb4';

        self::assertSame(
            'mysql://dicoma:08-Ei%%26Gude%%2B04@localhost:3306/dicoma?serverVersion=5.7.44-MySql&charset=utf8mb4',
            InstallationEnvironmentValue::databaseUrl($url),
        );
    }
}
