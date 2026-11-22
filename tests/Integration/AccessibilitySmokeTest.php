<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use App\Entity\Club;

final class AccessibilitySmokeTest extends WebTestCase
{
    public function testPublicShellAndLoginHaveBaselineWcagSemantics(): void
    {
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        $client = static::createClient(['environment' => 'test']);
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist((new Club())->setName('Accessibility'));
        $em->flush();
        foreach (['/', '/login'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSame('de', $crawler->filter('html')->attr('lang'));
            self::assertCount(1, $crawler->filter('#main-content'));
            self::assertCount(1, $crawler->filter('a.skip-link'));
            self::assertNotEmpty((string) $crawler->filter('title')->text());
            foreach ($crawler->filter('img') as $image) {
                self::assertTrue($image->hasAttribute('alt'), 'Images must have alternative text.');
            }
        }
        $crawler = $client->request('GET', '/login');
        foreach ($crawler->filter('input:not([type=hidden])') as $input) {
            $id = $input->getAttribute('id');
            self::assertNotEmpty($id);
            self::assertGreaterThan(0, $crawler->filter('label[for="'.$id.'"]')->count(), 'Every form control needs a label.');
        }
    }
}
