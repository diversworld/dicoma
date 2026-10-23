<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\User;
use App\Entity\Vendor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class Phase2VendorAuthorizationHttpTest extends WebTestCase
{
    public function testNonSuperAdminsCannotAccessVendorAdministration(): void
    {
        [$client, $users] = $this->context();

        $paths = [
            '/vendor/',
            '/vendor/new',
            '/admin/de/vendor',
            '/admin/de/vendor/new',
        ];

        foreach (['a', 'b', 'user'] as $actor) {
            $client->loginUser($users[$actor]);

            foreach ($paths as $path) {
                $client->request('GET', $path);

                self::assertResponseStatusCodeSame(
                    403,
                    sprintf('%s darf %s nicht aufrufen.', $actor, $path)
                );
            }

            // Auch schreibende Anfragen müssen bereits an der
            // Rollenprüfung scheitern, unabhängig vom CSRF-Token.
            $client->request('POST', '/vendor/new');

            self::assertResponseStatusCodeSame(
                403,
                sprintf('%s darf keine Lieferanten anlegen.', $actor)
            );
        }
    }

    public function testNonSuperAdminsCannotAccessExistingVendorActions(): void
    {
        [$client, $users] = $this->context();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Ausschließlich temporäre Daten in der bestätigten Testdatenbank.
        $vendor = new Vendor();
        $vendor->setName('Phase2 Security Test Vendor');
        $vendor->setEmail('phase2-vendor@example.invalid');
        $vendor->setPhone('0000000000');

        $em->persist($vendor);
        $em->flush();

        $id = $vendor->getId();

        self::assertNotNull($id);

        try {
            foreach (['a', 'b', 'user'] as $actor) {
                $client->loginUser($users[$actor]);

                foreach ([
                    '/vendor/'.$id,
                    '/vendor/'.$id.'/edit',
                    '/admin/de/vendor/'.$id,
                    '/admin/de/vendor/'.$id.'/edit',
                ] as $path) {
                    $client->request('GET', $path);

                    self::assertResponseStatusCodeSame(
                        403,
                        sprintf('%s darf %s nicht aufrufen.', $actor, $path)
                    );
                }

                $client->request('POST', '/vendor/'.$id);

                self::assertResponseStatusCodeSame(
                    403,
                    sprintf('%s darf Lieferanten nicht löschen.', $actor)
                );
            }
        } finally {
            // Testdatensatz auch bei fehlgeschlagener Assertion entfernen.
            $em->clear();

            $storedVendor = $em->getRepository(Vendor::class)->find($id);

            if ($storedVendor !== null) {
                $em->remove($storedVendor);
                $em->flush();
            }
        }
    }

    public function testSuperAdminCanReadVendorAdministration(): void
    {
        [$client, $users] = $this->context();

        $client->loginUser($users['super']);

        foreach ([
            '/vendor/',
            '/vendor/new',
            '/admin/de/vendor',
            '/admin/de/vendor/new',
        ] as $path) {
            $client->request('GET', $path);

            self::assertResponseIsSuccessful(
                sprintf('Super-Admin muss %s aufrufen können.', $path)
            );
        }
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
