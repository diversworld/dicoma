<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class Phase2InvoiceSettingsAuthorizationHttpTest extends WebTestCase
{
    public function testClubAdminsAndOrdinaryUsersCannotAccessInvoiceSettings(): void
    {
        [$client, $users, $clubIds] = $this->context();

        foreach (['a', 'b', 'user'] as $actor) {
            foreach ([
                '/admin/de/invoice-settings',
                '/admin/de/invoice-settings/'.$clubIds['a'],
                '/admin/de/invoice-settings/'.$clubIds['b'],
                '/admin/de/invoice-agency-confirmations',
                '/admin/tuv/invoice/settings',
                '/admin/tuv/invoice/settings/'.$clubIds['a'],
                '/admin/tuv/invoice/settings/'.$clubIds['b'],
            ] as $path) {
                $client->loginUser($users[$actor]);
                $client->request('GET', $path);

                self::assertResponseStatusCodeSame(
                    403,
                    sprintf('%s darf GET %s nicht aufrufen.', $actor, $path)
                );
            }
        }
    }

    public function testClubAdminsCannotPostInvoiceSettings(): void
    {
        [$client, $users, $clubIds] = $this->context();

        foreach (['a', 'b', 'user'] as $actor) {
            foreach ($clubIds as $clubId) {
                foreach ([
                    '/admin/de/invoice-settings/'.$clubId,
                    '/admin/tuv/invoice/settings/'.$clubId,
                ] as $path) {
                    $client->loginUser($users[$actor]);

                    $client->request('POST', $path, [
                        'tax_mode' => 'invalid',
                        'invoice_note' => 'UNAUTHORIZED_TEST',
                        '_token' => 'invalid',
                    ]);

                    self::assertResponseStatusCodeSame(
                        403,
                        sprintf('%s darf POST %s nicht ausführen.', $actor, $path)
                    );
                }
            }
        }
    }

    public function testSuperAdminCanReadInvoiceSettings(): void
    {
        [$client, $users, $clubIds] = $this->context();

        foreach ([
            '/admin/de/invoice-settings',
            '/admin/de/invoice-agency-confirmations',
            '/admin/tuv/invoice/settings',
        ] as $path) {
            $client->loginUser($users['super']);
            $client->request('GET', $path);

            self::assertResponseIsSuccessful();
        }

        foreach ($clubIds as $clubId) {
            foreach ([
                '/admin/de/invoice-settings/'.$clubId,
                '/admin/tuv/invoice/settings/'.$clubId,
            ] as $path) {
                $client->loginUser($users['super']);
                $client->request('GET', $path);

                self::assertResponseIsSuccessful();
            }
        }
    }

    /**
     * @return array{
     *     0: KernelBrowser,
     *     1: array<string, User>,
     *     2: array<string, int>
     * }
     */
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
                self::markTestSkipped('Missing fixture configuration: '.$key);
            }
        }

        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $database = (string) $em->getConnection()->fetchOne('SELECT DATABASE()');

        self::assertSame(
            $config['DICOMA_TEST_EXPECTED_DATABASE'],
            $database,
            'Wrong test database.'
        );

        self::assertNotSame(
            'h20292_dicoma',
            $database,
            'Production database is forbidden.'
        );

        $users = [];

        foreach ([
            'a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'user' => 'DICOMA_TEST_ROLE_USER_EMAIL',
        ] as $actor => $key) {
            $users[$actor] = $em->getRepository(User::class)
                ->findOneBy(['email' => $config[$key]]);

            self::assertInstanceOf(
                User::class,
                $users[$actor],
                'Missing fixture user: '.$key
            );
        }

        $clubIds = [];

        foreach (['a', 'b'] as $actor) {
            $clubId = $users[$actor]->getMember()?->getClub()?->getId();

            self::assertNotNull($clubId);
            self::assertContains('ROLE_ADMIN', $users[$actor]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$actor]->getRoles());

            $clubIds[$actor] = $clubId;
        }

        self::assertNotSame($clubIds['a'], $clubIds['b']);

        self::assertContains('ROLE_SUPER_ADMIN', $users['super']->getRoles());
        self::assertNotContains('ROLE_ADMIN', $users['user']->getRoles());
        self::assertNotContains('ROLE_SUPER_ADMIN', $users['user']->getRoles());

        return [$client, $users, $clubIds];
    }
}
