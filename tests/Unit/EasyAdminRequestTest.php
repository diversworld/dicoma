<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\EasyAdminRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class EasyAdminRequestTest extends TestCase
{
    public function testMissingRequestOrAttributeIsNotEasyAdmin(): void
    {
        self::assertFalse(EasyAdminRequest::matches(null));
        self::assertFalse(EasyAdminRequest::matches(new Request()));
    }

    #[DataProvider('attributeValues')]
    public function testExistingAttributeKeepsPhpBooleanSemantics(mixed $value, bool $expected): void
    {
        $request = new Request();
        $request->attributes->set('routeCreatedByEasyAdmin', $value);
        self::assertSame($expected, EasyAdminRequest::matches($request));
    }

    public static function attributeValues(): iterable
    {
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield 'null' => [null, false];
        yield 'zero' => [0, false];
        yield 'one' => [1, true];
        yield 'zero string' => ['0', false];
        yield 'nonempty string' => ['false', true];
    }
}
