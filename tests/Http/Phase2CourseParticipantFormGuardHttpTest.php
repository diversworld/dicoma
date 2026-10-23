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

/** DDEV-only HTTP checks: real form CSRF, choice scoping and forged association IDs. */
final class Phase2CourseParticipantFormGuardHttpTest extends WebTestCase
{
    public function testForeignChoicesAndForgedAssociationsCannotBePersisted(): void
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1'
            || getenv('DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED') !== '1') {
            self::markTestSkipped('Requires APP_ENV=test and explicit database/write-test confirmations.');
        }

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        self::assertSame('db_test', (string) getenv('DICOMA_TEST_EXPECTED_DATABASE'));
        self::assertSame('db_test', (string) $connection->fetchOne('SELECT DATABASE()'));
        self::assertSame('db_test', $connection->getParams()['host'] ?? null, 'Must use isolated DDEV db_test server.');

        $users = [];
        $participants = [];
        foreach (['a', 'b'] as $key) {
            $email = trim((string) getenv('DICOMA_TEST_CLUB_'.strtoupper($key).'_ADMIN_EMAIL'));
            $id = trim((string) getenv('DICOMA_TEST_CLUB_'.strtoupper($key).'_PARTICIPANT_ID'));
            self::assertNotSame('', $email);
            self::assertTrue(ctype_digit($id) && (int) $id > 0);
            $users[$key] = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            $participants[$key] = $em->getRepository(CourseParticipant::class)->find((int) $id);
            self::assertInstanceOf(User::class, $users[$key]);
            self::assertInstanceOf(CourseParticipant::class, $participants[$key]);
            $club = $users[$key]->getMember()?->getClub()?->getId();
            self::assertNotNull($club);
            self::assertSame($club, $participants[$key]->getCourse()?->getClub()?->getId());
            self::assertSame($club, $participants[$key]->getMember()?->getClub()?->getId());
            self::assertContains('ROLE_ADMIN', $users[$key]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$key]->getRoles());
        }
        self::assertNotSame($users['a']->getMember()?->getClub()?->getId(), $users['b']->getMember()?->getClub()?->getId());

        foreach (['a' => 'b', 'b' => 'a'] as $actor => $foreign) {
            $client->loginUser($users[$actor]);
            $foreignCourseId = $participants[$foreign]->getCourse()?->getId();
            $foreignMemberId = $participants[$foreign]->getMember()?->getId();
            $ownCourseId = $participants[$actor]->getCourse()?->getId();
            $ownMemberId = $participants[$actor]->getMember()?->getId();
            self::assertNotNull($foreignCourseId);
            self::assertNotNull($foreignMemberId);
            self::assertNotNull($ownCourseId);
            self::assertNotNull($ownMemberId);

            // GET new: verify actual rendered choice options, not just backend QueryBuilder.
            $crawler = $client->request('GET', $this->url(Action::NEW));
            self::assertResponseIsSuccessful();
            $form = $this->participantForm($crawler);
            $fields = $form->getPhpValues();
            $name = $this->formRoot($fields);
            $this->assertRealCsrfToken($fields, $name);
            self::assertArrayHasKey('course', $fields[$name]);
            self::assertArrayHasKey('member', $fields[$name]);
            foreach (['course' => $foreignCourseId, 'member' => $foreignMemberId] as $field => $foreignId) {
                $options = $crawler->filter(sprintf('select[name="%s[%s]"] option', $name, $field));
                self::assertGreaterThan(0, $options->count(), 'Expected rendered '.$field.' choices.');
                $values = $options->each(static fn ($node): string => (string) $node->attr('value'));
                self::assertNotContains((string) $foreignId, $values, 'Foreign '.$field.' must not appear in choices.');
            }

            // Forge the otherwise filtered-out foreign course/member with a real form CSRF token.
            $countBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM course_participant');
            $fields[$name]['course'] = (string) $foreignCourseId;
            $fields[$name]['member'] = (string) $foreignMemberId;
            $client->request('POST', $this->url(Action::NEW), $fields);
            self::assertNotSame(500, $client->getResponse()->getStatusCode());
            self::assertSame($countBefore, (int) $connection->fetchOne('SELECT COUNT(*) FROM course_participant'), 'Forged NEW must not insert a participant.');

            // Forge edit of an existing own participant: associations must remain unchanged.
            $ownId = $participants[$actor]->getId();
            self::assertNotNull($ownId);
            $before = $this->snapshot($em, $ownId);
            $crawler = $client->request('GET', $this->url(Action::EDIT, $ownId));
            self::assertResponseIsSuccessful();
            $form = $this->participantForm($crawler);
            $fields = $form->getPhpValues();
            $name = $this->formRoot($fields);
            $this->assertRealCsrfToken($fields, $name);
            // Disabled association fields are intentionally absent from getPhpValues().
            // Inject them anyway to test server-side handling of forged POST data.
            $fields[$name]['course'] = (string) $foreignCourseId;
            $fields[$name]['member'] = (string) $foreignMemberId;
            $client->request('POST', $this->url(Action::EDIT, $ownId), $fields);
            self::assertNotSame(500, $client->getResponse()->getStatusCode());
            $em->clear();
            self::assertSame($before, $this->snapshot($em, $ownId), 'Forged EDIT must not change the existing participant row.');
        }
    }

    private function participantForm(\Symfony\Component\DomCrawler\Crawler $crawler): \Symfony\Component\DomCrawler\Form
    {
        foreach ($crawler->filter('form') as $index => $element) {
            $candidate = new \Symfony\Component\DomCrawler\Crawler($element);
            $names = $candidate->filter('input[name], select[name], textarea[name]')->each(
                static fn ($node): string => (string) $node->attr('name')
            );
            $hasCourse = false;
            $hasMember = false;
            foreach ($names as $name) {
                $hasCourse = $hasCourse || str_ends_with($name, '[course]');
                $hasMember = $hasMember || str_ends_with($name, '[member]');
            }
            if ($hasCourse && $hasMember) {
                // Use the original response crawler: it retains the request base URI.
                return $crawler->filter('form')->eq($index)->form();
            }
        }
        self::fail('No HTML form containing participant course and member fields found.');
    }

    /** @param array<string,mixed> $fields */
    private function assertRealCsrfToken(array $fields, string $name): void
    {
        self::assertArrayHasKey('_token', $fields[$name],
            'Participant form has no _token field. Check the actual Symfony form type and CSRF configuration; do not forge or bypass CSRF.');
        self::assertIsString($fields[$name]['_token']);
        self::assertNotSame('', $fields[$name]['_token']);
    }

    /** @param array<string,mixed> $values */
    private function formRoot(array $values): string
    {
        foreach ($values as $key => $value) {
            if (is_array($value) && array_key_exists('_token', $value)) {
                return (string) $key;
            }
        }
        self::fail('No participant form root with a CSRF token found.');
    }

    /** @return array<string,mixed> */
    private function snapshot(EntityManagerInterface $em, int $id): array
    {
        $row = $em->getConnection()->fetchAssociative('SELECT * FROM course_participant WHERE id = :id', ['id' => $id]);
        self::assertIsArray($row);
        return $row;
    }

    private function url(string $action, ?int $id = null): string
    {
        $generator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = (clone $generator)->unsetAll()->setController(CourseParticipantCrudController::class)->setAction($action);
        if ($id !== null) {
            $url->setEntityId($id);
        }
        return $url->generateUrl();
    }
}
