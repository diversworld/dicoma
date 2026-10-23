<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\User;
use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class Phase2TankCheckArticleAuthorizationHttpTest extends WebTestCase
{
    public function testNonSuperAdminsCannotAccessTankCheckArticles(): void
    {
        [$client, $users] = $this->context();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Temporäre Datensätze ausschließlich in der bestätigten Testdatenbank.
        $tankCheck = new TankCheck();

        $article = new TankCheckArticle();
        $article->setTitle('Phase2 Security Test Article');
        $article->setPriceNetto('10.00');
        $article->setTankCheck($tankCheck);

        $em->persist($tankCheck);
        $em->persist($article);
        $em->flush();

        $articleId = $article->getId();
        $tankCheckId = $tankCheck->getId();

        self::assertNotNull($articleId);
        self::assertNotNull($tankCheckId);

        try {
            foreach (['a', 'b', 'user'] as $actor) {
                $client->loginUser($users[$actor]);

                foreach ([
                    '/tank/check/article/',
                    '/tank/check/article/new/'.$tankCheckId,
                    '/tank/check/article/'.$articleId,
                    '/tank/check/article/'.$articleId.'/edit',
                ] as $path) {
                    $client->request('GET', $path);

                    self::assertResponseStatusCodeSame(
                        403,
                        sprintf('%s darf %s nicht aufrufen.', $actor, $path)
                    );
                }

                foreach ([
                    '/tank/check/article/new/'.$tankCheckId,
                    '/tank/check/article/'.$articleId.'/edit',
                    '/tank/check/article/'.$articleId,
                ] as $path) {
                    $client->request('POST', $path);

                    self::assertResponseStatusCodeSame(
                        403,
                        sprintf('%s darf %s nicht verändern.', $actor, $path)
                    );
                }
            }
        } finally {
            // Nach HTTP-Requests können Doctrine-Entities detached sein.
            $em->clear();

            $storedTankCheck = $em->getRepository(TankCheck::class)
                ->find($tankCheckId);

            if ($storedTankCheck !== null) {
                // TankCheck entfernt die zugehörigen Artikel per Cascade.
                $em->remove($storedTankCheck);
                $em->flush();
            }
        }
    }

    public function testSuperAdminCanReadTankCheckArticleIndex(): void
    {
        [$client, $users] = $this->context();

        $client->loginUser($users['super']);

        $client->request('GET', '/tank/check/article/');

        self::assertResponseIsSuccessful(
            'Super-Admin muss die TÜV-Preisartikel aufrufen können.'
        );
    }

    private function context(): array
    {
        if (
            ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1'
        ) {
            self::markTestSkipped(
                'Requires APP_ENV=test and explicit staging database confirmation.'
            );
        }

        $config = [];

        foreach ([
            'DICOMA_TEST_EXPECTED_DATABASE',
            'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'DICOMA_TEST_ROLE_USER_EMAIL',
        ] as $key) {
            $config[$key] = trim((string) getenv($key));

            if ($config[$key] === '') {
                self::markTestSkipped('Missing fixture: '.$key);
            }
        }

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $database = (string) $em->getConnection()
            ->fetchOne('SELECT DATABASE()');

        self::assertSame(
            $config['DICOMA_TEST_EXPECTED_DATABASE'],
            $database
        );
        self::assertNotSame('h20292_dicoma', $database);

        $users = [];

        foreach ([
            'a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'user' => 'DICOMA_TEST_ROLE_USER_EMAIL',
        ] as $actor => $key) {
            $users[$actor] = $em->getRepository(User::class)
                ->findOneBy(['email' => $config[$key]]);

            self::assertInstanceOf(User::class, $users[$actor]);
        }

        foreach (['a', 'b'] as $actor) {
            self::assertContains('ROLE_ADMIN', $users[$actor]->getRoles());
            self::assertNotContains(
                'ROLE_SUPER_ADMIN',
                $users[$actor]->getRoles()
            );
        }

        self::assertContains(
            'ROLE_SUPER_ADMIN',
            $users['super']->getRoles()
        );

        self::assertNotContains(
            'ROLE_SUPER_ADMIN',
            $users['user']->getRoles()
        );

        return [$client, $users];
    }
}
