<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Controller\Admin\CourseParticipantCrudController;
use App\Entity\CourseParticipant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Read-only checks: foreign participant detail/edit and own participant access. */
final class Phase2CourseParticipantIsolationHttpTest extends WebTestCase
{
    public function testParticipantDetailAndEditRespectClubBoundaries(): void
    {
        $config = $this->fixtures();
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = (string) $em->getConnection()->fetchOne('SELECT DATABASE()');
        self::assertContains($db, ['h20292_dicoma_test', 'db_test'], 'Only explicitly allowed test databases may be used.');
        self::assertSame($config['DICOMA_TEST_EXPECTED_DATABASE'], $db);

        $users = [];
        foreach (['a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL', 'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL', 'ordinary' => 'DICOMA_TEST_ROLE_USER_EMAIL'] as $key => $env) {
            $users[$key] = $em->getRepository(User::class)->findOneBy(['email' => $config[$env]]);
            self::assertInstanceOf(User::class, $users[$key]);
        }
        $participants = [];
        foreach (['a' => 'DICOMA_TEST_CLUB_A_PARTICIPANT_ID', 'b' => 'DICOMA_TEST_CLUB_B_PARTICIPANT_ID'] as $key => $env) {
            $participants[$key] = $em->getRepository(CourseParticipant::class)->find((int) $config[$env]);
            self::assertInstanceOf(CourseParticipant::class, $participants[$key]);
            $clubId = $users[$key]->getMember()?->getClub()?->getId();
            self::assertNotNull($clubId);
            self::assertSame($clubId, $participants[$key]->getCourse()?->getClub()?->getId());
            self::assertSame($clubId, $participants[$key]->getMember()?->getClub()?->getId());
        }
        self::assertNotSame($users['a']->getMember()?->getClub()?->getId(), $users['b']->getMember()?->getClub()?->getId());
        foreach (['a', 'b'] as $actor) {
            self::assertContains('ROLE_ADMIN', $users[$actor]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$actor]->getRoles());
        }
        self::assertContains('ROLE_SUPER_ADMIN', $users['super']->getRoles());
        self::assertNotContains('ROLE_ADMIN', $users['ordinary']->getRoles());

        foreach ([Action::DETAIL, Action::EDIT] as $action) {
            foreach (['a' => 'b', 'b' => 'a'] as $actor => $foreign) {
                $client->loginUser($users[$actor]);
                $client->request('GET', $this->url($action, $participants[$foreign]->getId()));
                self::assertResponseStatusCodeSame(403, 'Foreign participant must not be accessible.');
            }
            foreach (['a', 'b'] as $actor) {
                $client->loginUser($users[$actor]);
                $client->request('GET', $this->url($action, $participants[$actor]->getId()));
                self::assertResponseIsSuccessful();
            }
            foreach ($participants as $participant) {
                $client->loginUser($users['super']);
                $client->request('GET', $this->url($action, $participant->getId()));
                self::assertResponseIsSuccessful();
                $client->loginUser($users['ordinary']);
                $client->request('GET', $this->url($action, $participant->getId()));
                self::assertResponseStatusCodeSame(403);
            }
        }
    }

    private function url(string $action, ?int $id): string
    {
        self::assertNotNull($id);
        $generator = static::getContainer()->get(AdminUrlGenerator::class);
        return (clone $generator)->unsetAll()->setController(CourseParticipantCrudController::class)
            ->setAction($action)->setEntityId($id)->generateUrl();
    }

    /** @return array<string, string> */
    private function fixtures(): array
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1') {
            self::markTestSkipped('Requires APP_ENV=test and explicit test database confirmation.');
        }
        $keys = ['DICOMA_TEST_EXPECTED_DATABASE', 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'DICOMA_TEST_CLUB_B_ADMIN_EMAIL', 'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'DICOMA_TEST_ROLE_USER_EMAIL', 'DICOMA_TEST_CLUB_A_PARTICIPANT_ID',
            'DICOMA_TEST_CLUB_B_PARTICIPANT_ID'];
        $config = [];
        foreach ($keys as $key) {
            $config[$key] = trim((string) getenv($key));
            if ($config[$key] === '') {
                self::markTestSkipped('Missing participant fixture configuration: '.$key);
            }
        }
        self::assertContains($config['DICOMA_TEST_EXPECTED_DATABASE'], ['h20292_dicoma_test', 'db_test'], 'Expected database must be an allowed test database.');
        foreach (['DICOMA_TEST_CLUB_A_PARTICIPANT_ID', 'DICOMA_TEST_CLUB_B_PARTICIPANT_ID'] as $key) {
            self::assertTrue(ctype_digit($config[$key]) && (int) $config[$key] > 0);
        }
        self::assertNotSame($config['DICOMA_TEST_CLUB_A_PARTICIPANT_ID'], $config['DICOMA_TEST_CLUB_B_PARTICIPANT_ID']);
        return $config;
    }
}
