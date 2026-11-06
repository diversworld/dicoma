<?php

declare(strict_types=1);

namespace App\Tests\Http;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait SelectAdminClub
{
    private function selectAdminClub(KernelBrowser $client, int $clubId): void
    {
        $crawler = $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $forms = $crawler->filter('form[name="admin_club"]');
        self::assertGreaterThan(0, $forms->count());
        $client->submit($forms->first()->form(), ['admin_club[club]' => (string) $clubId]);
        self::assertResponseStatusCodeSame(303);
    }
}
