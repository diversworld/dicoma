<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\CommunicationTemplate;
use App\Entity\ContributionClass;
use App\Entity\InternalMessage;
use App\Entity\Member;
use App\Entity\MembershipCharge;
use App\Entity\MembershipMessage;
use App\Entity\Person;
use App\Entity\User;
use App\Service\MembershipBillingService;
use App\Service\MembershipHistoryRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PhaseBMembershipCommunicationHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
            'CLUB_FUNCTION_MEMBERSHIP_ENABLED' => '0',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Phase B Club');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testMonthlyContributionIsIdempotentAndKeepsAnnualDiscountMonthlyProrated(): void
    {
        $class = new ContributionClass($this->club);
        $class->setName('Jugend monatlich');
        $class->setActive(true);
        $class->setBillingPeriod('monthly');
        $class->setMonthlyCents(1500);
        $class->setMemberCategory('youth');
        $member = $this->member('monthly', [], $class);
        $member->setContributionDiscountCents(1200);
        $this->em->flush();
        $billing = static::getContainer()->get(MembershipBillingService::class);
        $first = $billing->runMonthly($this->club, '2026-11', new \DateTimeImmutable('2026-11-30'), 'tester');
        self::assertSame(1, $first['charges']);
        self::assertSame(1400, $first['amountCents']);
        $this->em->flush();
        $second = $billing->runMonthly($this->club, '2026-11', new \DateTimeImmutable('2026-11-30'), 'tester');
        self::assertSame(0, $second['charges']);
        self::assertSame(1, $this->em->getRepository(MembershipCharge::class)->count([]));
    }

    public function testGroupTemplateCampaignQueuesPersonalizedMessagesAndAuditsSend(): void
    {
        $admin = $this->member('communication-admin', [User::ROLE_ADMIN]);
        $this->member('board-1', [User::ROLE_BOARD]);
        $this->member('board-2', [User::ROLE_BOARD]);
        $template = new CommunicationTemplate($this->club, 'invite', 'Vorstandseinladung', 'Einladung {{ firstname }}', 'Hallo {{ firstname }}, Termin am Freitag.');
        $this->em->persist($template);
        $this->em->flush();
        $this->client->loginUser($admin->getUser());
        $crawler = $this->client->request('GET', '/admin/communication');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form input[name="_token"]')->eq(1)->attr('value');
        $this->client->request('POST', '/admin/communication', [
            '_token' => $token,
            'operation' => 'send',
            'audience' => 'board',
            'templateId' => (string) $template->getId(),
        ]);
        self::assertResponseRedirects('/admin/communication');
        self::assertSame(1, $this->em->getRepository(InternalMessage::class)->count([]));
        $mails = $this->em->getRepository(MembershipMessage::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $mails);
        self::assertStringContainsString('Board 1', $mails[0]->getSubject());
        self::assertStringContainsString('Hallo Board 1', $mails[0]->getBody());
        self::assertSame(1, $this->em->getRepository(\App\Entity\AuditEvent::class)->count(['action' => 'communication.sent']));
    }

    public function testStructuredHistoryRecordsOnlyChangedFields(): void
    {
        $member = $this->member('history');
        $history = static::getContainer()->get(MembershipHistoryRecorder::class);
        $history->record($member, 'tester', 'contact_updated', [
            'email' => ['from' => 'old@example.invalid', 'to' => 'new@example.invalid'],
            'phone' => ['from' => '123', 'to' => '123'],
        ]);
        $this->em->flush();
        $entry = $this->em->getRepository(\App\Entity\MembershipHistory::class)->findOneBy(['member' => $member]);
        self::assertSame(['email' => ['from' => 'old@example.invalid', 'to' => 'new@example.invalid']], $entry->getChanges());
    }

    public function testMemberCanUploadAndPrivatelyDisplayProfileImage(): void
    {
        $member = $this->member('profile-image');
        $this->client->loginUser($member->getUser());
        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action="/profile/image"] input[name="_token"]')->attr('value');
        $path = tempnam(sys_get_temp_dir(), 'dicoma-avatar-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        $file = new UploadedFile($path, 'avatar.png', 'image/png', null, true);
        $this->client->request('POST', '/profile/image', ['_token' => $token], ['image' => $file]);
        self::assertResponseRedirects('/profile');
        $this->em->clear();
        $stored = $this->em->find(Member::class, $member->getId());
        self::assertNotNull($stored->getPerson()->getProfileImageStorageName());
        $this->client->request('GET', '/profile/image/'.$stored->getId());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
    }

    private function member(string $name, array $roles = [], ?ContributionClass $class = null): Member
    {
        $person = (new Person())->setFirstname(ucwords(str_replace('-', ' ', $name)))->setLastname('Test')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        if ($class !== null) { $member->setContributionClass($class); $this->em->persist($class); }
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setRoles($roles)->setMember($member);
        foreach ([$person, $member, $user] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        return $member;
    }
}
