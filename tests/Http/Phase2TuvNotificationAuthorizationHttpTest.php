<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\User;
use App\Entity\MemberTankCheckBooking;
use App\Entity\TuvNotification;
use App\Entity\TankCheck;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


final class Phase2TuvNotificationAuthorizationHttpTest extends WebTestCase
{
    public function testNonSuperAdminsCannotAccessNotificationAdministration(): void
    {
        [$client, $users] = $this->context();

        $paths = [
            '/admin/de/tuv-notification',
            '/admin/de/settings/tuv-notifications',
            '/admin/tuv/message-templates',
        ];

        foreach (['a', 'b', 'user'] as $actor) {
            foreach ($paths as $path) {
                $client->loginUser($users[$actor]);
                $client->request('GET', $path);

                self::assertResponseStatusCodeSame(
                    403,
                    sprintf('%s darf %s nicht aufrufen.', $actor, $path)
                );
            }

            $client->loginUser($users[$actor]);
            $client->request(
                'POST',
                '/admin/de/settings/tuv-notifications/save',
                [
                    'kind' => 'confirmation',
                    'subject' => 'UNAUTHORIZED_TEST',
                    'body' => 'UNAUTHORIZED_TEST',
                    '_token' => 'invalid',
                ]
            );

            self::assertResponseStatusCodeSame(
                403,
                sprintf('%s darf Nachrichtenvorlagen nicht speichern.', $actor)
            );
        }
    }

    public function testSuperAdminCanReadNotificationAdministration(): void
    {
        [$client, $users] = $this->context();

        foreach ([
            '/admin/de/tuv-notification',
            '/admin/de/settings/tuv-notifications',
        ] as $path) {
            $client->loginUser($users['super']);
            $client->request('GET', $path);

            self::assertResponseIsSuccessful();
        }

        $client->loginUser($users['super']);
        $client->request('GET', '/admin/tuv/message-templates');

        self::assertResponseRedirects();
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

        $database = (string) $em->getConnection()->fetchOne('SELECT DATABASE()');

        self::assertSame($config['DICOMA_TEST_EXPECTED_DATABASE'], $database);
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
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$actor]->getRoles());
        }

        self::assertContains('ROLE_SUPER_ADMIN', $users['super']->getRoles());
        self::assertNotContains('ROLE_ADMIN', $users['user']->getRoles());
        self::assertNotContains('ROLE_SUPER_ADMIN', $users['user']->getRoles());

        return [$client, $users];
    }
    
    public function testOnlySuperAdminCanAccessRetryEndpoint(): void
    {
        [$client, $users] = $this->context();

        foreach (['a', 'b', 'user'] as $actor) {
            $client->loginUser($users[$actor]);

            $client->request(
                'POST',
                '/admin/tuv-notification/999999/retry',
                ['_token' => 'invalid']
            );

            self::assertResponseStatusCodeSame(
                403,
                sprintf('%s darf keinen TÜV-Retry auslösen.', $actor)
            );
        }

        $client->loginUser($users['super']);

        $client->request(
            'POST',
            '/admin/tuv-notification/999999/retry',
            ['_token' => 'invalid']
        );

        self::assertResponseStatusCodeSame(
            403,
            'Ein Super-Administrator benötigt einen gültigen CSRF-Token.'
        );
    }

    public function testSuperAdminCanRetryFailedNotification(): void
    {
        [$client, $users] = $this->context();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Ausschließlich temporäre Testdaten erzeugen.
        $tankCheck = new TankCheck();
        $booking = (new MemberTankCheckBooking())
            ->setTankCheck($tankCheck);

        $notification = new TuvNotification(
            $booking,
            'phase2-retry-test-'.bin2hex(random_bytes(12)),
            'retry-test@example.invalid',
            'Phase2 Retry Test',
            'Diese Nachricht darf nicht versendet werden.'
        );

        $notification->markFailed('Simulierter Versandfehler');

        $em->persist($tankCheck);
        $em->persist($booking);
        $em->persist($notification);
        $em->flush();

        $id = $notification->getId();

        self::assertNotNull($id);
        self::assertSame('failed', $notification->getState());

        try {
            $client->loginUser($users['super']);

            // Die Bestätigungsseite erzeugt den CSRF-Token
            // innerhalb einer regulären HTTP-Anfrage.
            $crawler = $client->request(
                'GET',
                '/admin/de/tuv-notification/'.$id
            );

            self::assertResponseIsSuccessful();

            // EasyAdmin-Detailseite aufrufen und anschließend
            // die Retry-Bestätigungsaktion verwenden.
            $retryLink = $crawler->filter('a')
                ->reduce(
                    static fn ($node) => str_contains(
                        $node->attr('href') ?? '',
                        'confirmRetry'
                    )
                );

            self::assertGreaterThan(
                0,
                $retryLink->count(),
                'Die Retry-Aktion muss für fehlgeschlagene Nachrichten sichtbar sein.'
            );

            $confirmationCrawler = $client->click($retryLink->link());

            self::assertResponseIsSuccessful();

            // Nur das Formular für den Retry-POST auswählen.
            $retryForm = $confirmationCrawler->filter(
                'form[action*="/admin/tuv-notification/"][action$="/retry"]'
            );

            self::assertCount(
                1,
                $retryForm,
                'Die Bestätigungsseite muss genau ein Retry-Formular enthalten.'
            );

            // Prüfen, ob das versteckte CSRF-Feld vorhanden ist.
            $tokenInput = $retryForm->filter('input[name="_token"]');

            self::assertCount(
                1,
                $tokenInput,
                'Das Retry-Formular benötigt ein CSRF-Token.'
            );

            self::assertNotEmpty(
                $tokenInput->attr('value'),
                'Das CSRF-Token darf nicht leer sein.'
            );

            // Formular mit dem tatsächlich generierten Token absenden.
            $client->submit($retryForm->form());

            self::assertResponseRedirects();

            // Nach dem HTTP-Request einen frischen EntityManager verwenden.
            $verificationEm = static::getContainer()
                ->get(EntityManagerInterface::class);

            $verificationEm->clear();

            $updated = $verificationEm->find(TuvNotification::class, $id);

            self::assertInstanceOf(TuvNotification::class, $updated);
            self::assertSame('pending', $updated->getState());
            self::assertNull($updated->getLastError());
            self::assertNull($updated->getSentAt());
        } finally {
            // Nach einem Kernel-Neustart nicht den alten EntityManager verwenden.
            $cleanupEm = static::getContainer()
                ->get(EntityManagerInterface::class);

            $cleanupEm->clear();

            // Abhängigkeiten in umgekehrter Reihenfolge entfernen.
            $cleanupNotification = $cleanupEm->find(TuvNotification::class, $id);

            if ($cleanupNotification !== null) {
                $cleanupEm->remove($cleanupNotification);
                $cleanupEm->flush();
            }

            $cleanupBooking = $cleanupEm->find(
                MemberTankCheckBooking::class,
                $booking->getId()
            );

            if ($cleanupBooking !== null) {
                $cleanupEm->remove($cleanupBooking);
                $cleanupEm->flush();
            }

            $cleanupTankCheck = $cleanupEm->find(
                TankCheck::class,
                $tankCheck->getId()
            );

            if ($cleanupTankCheck !== null) {
                $cleanupEm->remove($cleanupTankCheck);
                $cleanupEm->flush();
            }
        }
    }
}
