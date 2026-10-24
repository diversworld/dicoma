<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\ContributionClass;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MemberQualification;
use App\Entity\MembershipApplication;
use App\Entity\MembershipCharge;
use App\Entity\MembershipMessage;
use App\Entity\MembershipPolicy;
use App\Entity\Person;
use App\Entity\Qualification;
use App\Entity\SepaMandate;
use App\Entity\User;
use App\Enum\MembershipStatus;
use App\Enum\QualificationType;
use App\Service\MembershipAutomation;
use App\Service\MembershipFinanceService;
use App\Service\MembershipService;
use App\Service\PrivateMemberDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase3MembershipHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:');
        putenv('MAILER_DSN=null://null');
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Phase 3 Testverein');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testPublicApplicationCreatesNoIdentityOrMembershipBeforeManualDecision(): void
    {
        $this->policy();
        $crawler = $this->client->request('GET', '/membership/apply/' . $this->club->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="membership_application"]')->form();
        $this->client->submit($form, [
            'membership_application[firstname]' => 'Public', 'membership_application[lastname]' => 'Applicant',
            'membership_application[email]' => 'applicant@example.invalid', 'membership_application[birthday]' => '2000-01-01',
            'membership_application[street]' => 'Test Street 1', 'membership_application[postalCode]' => '00123',
            'membership_application[city]' => 'Test City', 'membership_application[privacy]' => '1',
        ]);
        self::assertResponseRedirects('/membership/apply');
        self::assertSame(1, $this->em->getRepository(MembershipApplication::class)->count([]));
        self::assertSame(0, $this->em->getRepository(Person::class)->count([]));
        self::assertSame(0, $this->em->getRepository(Member::class)->count([]));
        self::assertSame(2, $this->em->getRepository(MembershipMessage::class)->count(['state' => 'pending']));
        $this->client->request('POST', '/membership/apply/' . $this->club->getId(), ['membership_application' => ['firstname' => 'Forged', '_token' => 'invalid']]);
        self::assertSame(1, $this->em->getRepository(MembershipApplication::class)->count([]));
    }

    public function testVerifiedManualApprovalReusesGuestIdentityAndAccount(): void
    {
        $this->policy();
        $guest = (new User())->setUser('guest')->setEmail('guest@example.invalid')->setPassword('not-used')->setIsVerified(true);
        $person = (new Person())->setFirstname('Existing')->setLastname('Guest')->setBirthday(new \DateTime('2000-01-01'))->setEmail('guest@example.invalid');
        $guest->setPerson($person);
        $this->em->persist($guest);
        $application = $this->application();
        $service = static::getContainer()->get(MembershipService::class);
        $service->submit($application);
        $member = $service->approve($application, $person, 'superadmin', 'Ausweis und vorhandenes Gastkonto manuell geprüft.');
        self::assertSame($person->getId(), $member->getPerson()->getId());
        self::assertSame($member, $guest->getMember());
        self::assertSame('Existing', $person->getFirstname(), 'Unverified public data must not overwrite existing identity.');
        self::assertSame(1, $this->em->getRepository(Person::class)->count([]));
        self::assertSame('approved', $application->getStatus());
        self::assertContains('ROLE_MEMBER', $guest->getRoles());
        $this->expectException(\DomainException::class);
        $service->approve($application, $person, 'superadmin', 'Duplicate decision');
    }

    public function testContactSelfServiceRejectsAdministrativeFieldsAndPreservesAccountEmail(): void
    {
        $user = $this->memberUser('contact');
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/profile/contact');
        self::assertResponseIsSuccessful();
        $values = $crawler->filter('form[name="member_contact"]')->form()->getPhpValues();
        $values['member_contact']['membershipStatus'] = 'active';
        $values['member_contact']['clubFunctions'] = 'admin';
        $this->client->request('POST', '/profile/contact', $values);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.form-error-message, .invalid-feedback, ul');
        self::assertNull($this->em->find(Member::class, $user->getMember()->getId())->getClubFunctions());
        $crawler = $this->client->request('GET', '/profile/contact');
        $this->client->submit($crawler->filter('form[name="member_contact"]')->form(), [
            'member_contact[email]' => 'new-contact@example.invalid',
            'member_contact[postalCode]' => '00123',
            'member_contact[emergencyContactName]' => 'Emergency Contact',
            'member_contact[emergencyContactPhone]' => '+49 123456',
        ]);
        self::assertResponseRedirects('/profile/membership');
        $user = $this->em->find(User::class, $user->getId());
        self::assertSame('contact@example.invalid', $user->getEmail());
        self::assertSame('new-contact@example.invalid', $user->getPerson()->getEmail());
        self::assertSame('00123', $user->getPerson()->getPostalCode());
        self::assertSame('Emergency Contact', $user->getMember()->getEmergencyContactName());
    }

    public function testAdministrationRendersAndForeignClubObjectsAreForbidden(): void
    {
        $admin = $this->memberUser('admin', ['ROLE_ADMIN']);
        $foreignClub = (new Club())->setName('Foreign club');
        $this->em->persist($foreignClub);
        $foreign = $this->memberUser('foreign', ['ROLE_ADMIN'], $foreignClub);
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/membership/club/' . $this->club->getId());
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="membership_policy"]')->form(), [
            'membership_policy[cancellationCutoff]' => '09-30', 'membership_policy[membershipYearEnd]' => '12-31',
        ]);
        self::assertResponseRedirects('/admin/membership/club/' . $this->club->getId());
        $crawler = $this->client->request('GET', '/admin/membership/member/' . $admin->getMember()->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form[name="billing_assignment"]'));
        foreach (['/admin/membership/club/' . $foreignClub->getId(), '/admin/membership/member/' . $foreign->getMember()->getId()] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
        $charge = new MembershipCharge($this->em->find(Member::class, $foreign->getMember()->getId()), 'foreign-charge', 'Foreign', 1000, new \DateTimeImmutable());
        $this->em->persist($charge);
        $this->em->flush();
        $this->client->request('GET', '/admin/membership/charge/' . $charge->getId() . '/payment');
        self::assertResponseStatusCodeSame(403);
    }

    public function testFamilyAnnualContributionDiscountAndPartialPayments(): void
    {
        $payer = $this->memberUser('payer')->getMember();
        $child = $this->memberUser('child')->getMember();
        $class = new ContributionClass($this->club);
        $class->setName('Family');
        $class->setAnnualCents(12000);
        $class->setFamily(true);
        $this->em->persist($class);
        $this->em->flush();
        $finance = static::getContainer()->get(MembershipFinanceService::class);
        $finance->assign($payer, $class, null, 2000, 'Beschlossene Ermäßigung', 'admin');
        $finance->assign($child, null, $payer, 0, null, 'admin');
        try {
            $finance->annual($child, 2026, new \DateTimeImmutable('2026-12-01'), 'admin');
            self::fail('A family dependant must not receive a duplicate annual charge.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('Familienbeitrag', $exception->getMessage());
        }
        $charge = $finance->annual($payer, 2026, new \DateTimeImmutable('2026-12-01'), 'admin');
        self::assertSame(10000, $charge->getAmountCents());
        $finance->pay($charge, 4000, 'Bank statement 1', 'admin');
        self::assertSame(6000, $charge->getOutstandingCents());
        self::assertSame(1, $this->em->getRepository(MembershipCharge::class)->count([]));
        $this->expectException(\DomainException::class);
        $finance->annual($payer, 2026, new \DateTimeImmutable('2026-12-01'), 'admin');
    }

    public function testPaymentCannotExceedOutstandingAmountAndRollsBack(): void
    {
        $member = $this->memberUser('payment')->getMember();
        $finance = static::getContainer()->get(MembershipFinanceService::class);
        $charge = $finance->special($member, 'Special', 1000, new \DateTimeImmutable(), 'admin');
        try {
            $finance->pay($charge, 1001, 'overpayment', 'admin');
            self::fail('Overpayment must fail.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('übersteigt', $exception->getMessage());
        }
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT paid_cents FROM membership_charge WHERE id = ?', [$charge->getId()]));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM membership_payment'));
    }

    public function testTerminationRetainsAccessUntilEffectiveDateAndCanBeArchived(): void
    {
        $policy = $this->policy();
        $policy->setCancellationCutoff('09-30');
        $policy->setMembershipYearEnd('12-31');
        $this->em->flush();
        $user = $this->memberUser('termination');
        $member = $user->getMember();
        $service = static::getContainer()->get(MembershipService::class);
        $service->requestTermination($member, 'termination', new \DateTimeImmutable('2026-09-30'));
        self::assertSame('2026-12-31', $member->getTerminationDate()->format('Y-m-d'));
        self::assertContains('ROLE_MEMBER', $user->getRoles());
        $automation = static::getContainer()->get(MembershipAutomation::class);
        self::assertSame(0, $automation->run(new \DateTimeImmutable('2026-12-31')));
        self::assertSame(1, $automation->run(new \DateTimeImmutable('2027-01-01')));
        self::assertSame(MembershipStatus::FORMER, $member->getMembershipStatus());
        self::assertNotContains('ROLE_MEMBER', $user->getRoles());
        self::assertSame(0, $automation->run(new \DateTimeImmutable('2027-01-02')));
        $service->archive($member, 'admin');
        self::assertNotNull($member->getArchivedAt());
        self::assertSame(1, $this->em->getRepository(Person::class)->count([]));
    }

    public function testMedicalAndMissingDocumentRemindersAreDeduplicated(): void
    {
        $member = $this->memberUser('medical')->getMember();
        $policy = $this->policy();
        $policy->setRequiredDocuments(['medical']);
        $qualification = (new Qualification())->setName('Medical fitness')->setType(QualificationType::MEDICAL_FITNESS);
        $proof = (new MemberQualification())->setMember($member)->setQualification($qualification)
            ->setVerified(true)->setValidUntil(new \DateTime('2026-11-23'));
        $this->em->persist($qualification);
        $this->em->persist($proof);
        $this->em->flush();
        $automation = static::getContainer()->get(MembershipAutomation::class);
        $automation->run(new \DateTimeImmutable('2026-10-24'));
        self::assertSame(2, $this->em->getRepository(MembershipMessage::class)->count([]));
        $automation->run(new \DateTimeImmutable('2026-10-24'));
        self::assertSame(2, $this->em->getRepository(MembershipMessage::class)->count([]));
    }

    public function testSepaEncryptsIbanAndRequiresOwnProofAndConfiguredKey(): void
    {
        $member = $this->memberUser('sepa')->getMember();
        $policy = $this->policy();
        $policy->setCreditorIdentifier('DE98ZZZ09999999999');
        $temporary = tempnam(sys_get_temp_dir(), 'dicoma-sepa-test-');
        self::assertIsString($temporary);
        self::assertNotFalse(file_put_contents($temporary, "%PDF-1.4\n%%EOF\n"));
        $storage = static::getContainer()->get(PrivateMemberDocumentStorage::class);
        [$name, $mime, $size] = $storage->store(new UploadedFile($temporary, 'mandate.pdf', 'application/pdf', null, true));
        $proof = new MemberDocument($member, $name, 'mandate.pdf', $mime, $size);
        $this->em->persist($proof);
        $this->em->flush();
        $_ENV['DICOMA_MEMBERSHIP_BANK_KEY'] = $_SERVER['DICOMA_MEMBERSHIP_BANK_KEY'] = base64_encode(random_bytes(32));
        $finance = static::getContainer()->get(MembershipFinanceService::class);
        try {
            $finance->createMandate($member, 'TEST-001', 'Account Holder', 'DE89370400440532013000', new \DateTimeImmutable('2026-01-01'), $proof, 'admin');
            $mandate = $this->em->getRepository(SepaMandate::class)->findOneBy(['member' => $member]);
            self::assertInstanceOf(SepaMandate::class, $mandate);
            self::assertStringNotContainsString('DE89370400440532013000', $mandate->getEncryptedIban());
            self::assertSame('DE89370400440532013000', $finance->decryptIban($mandate));
            self::assertSame('**** 3000', $mandate->getMaskedIban());
            $mandate->revoke(new \DateTimeImmutable('2026-10-24'));
            self::assertNotNull($mandate->getRevokedAt());
            unset($_ENV['DICOMA_MEMBERSHIP_BANK_KEY'], $_SERVER['DICOMA_MEMBERSHIP_BANK_KEY']);
            putenv('DICOMA_MEMBERSHIP_BANK_KEY');
            $this->expectException(\DomainException::class);
            $finance->createMandate($member, 'TEST-002', 'Account Holder', 'DE89370400440532013000', new \DateTimeImmutable('2026-01-01'), $proof, 'admin');
        } finally {
            self::assertTrue(unlink($storage->path($name)));
        }
    }

    public function testRealContributionAndPaymentFormsUseExactCents(): void
    {
        $admin = $this->memberUser('finance-admin', ['ROLE_ADMIN']);
        $memberId = $admin->getMember()->getId();
        $clubPath = '/admin/membership/club/' . $this->club->getId();
        $memberPath = '/admin/membership/member/' . $memberId;
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', $clubPath);
        $this->client->submit($crawler->filter('form[name="contribution_class"]')->form(), [
            'contribution_class[name]' => 'Standard', 'contribution_class[annualCents]' => '123.45',
        ]);
        self::assertResponseRedirects($clubPath);
        $class = $this->em->getRepository(ContributionClass::class)->findOneBy(['name' => 'Standard']);
        self::assertInstanceOf(ContributionClass::class, $class);
        self::assertSame(12345, $class->getAnnualCents());
        $crawler = $this->client->request('GET', $memberPath);
        $this->client->submit($crawler->filter('form[name="billing_assignment"]')->form(), [
            'billing_assignment[class]' => (string) $class->getId(), 'billing_assignment[discount]' => '3.45',
            'billing_assignment[reason]' => 'Beschluss',
        ]);
        self::assertResponseRedirects($memberPath);
        $crawler = $this->client->request('GET', $memberPath);
        $this->client->submit($crawler->filter('form[name="annual_charge"]')->form(), ['annual_charge[year]' => '2026', 'annual_charge[due]' => '2026-12-01']);
        self::assertResponseRedirects($memberPath);
        $charge = $this->em->getRepository(MembershipCharge::class)->findOneBy(['member' => $memberId]);
        self::assertInstanceOf(MembershipCharge::class, $charge);
        self::assertSame(12000, $charge->getAmountCents());
        $crawler = $this->client->request('GET', '/admin/membership/charge/' . $charge->getId() . '/payment');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="membership_payment"]')->form(), [
            'membership_payment[amount]' => '20.01', 'membership_payment[reference]' => 'Bank-Test',
        ]);
        self::assertResponseRedirects($memberPath);
        $this->em->clear();
        self::assertSame(9999, $this->em->find(MembershipCharge::class, $charge->getId())->getOutstandingCents());
    }

    public function testDailyCommandDoesNotSendWithoutExplicitOptInAndReportsMissingSender(): void
    {
        $service = static::getContainer()->get(MembershipService::class);
        $service->queue('mail-test', 'recipient@example.invalid', 'Test', 'Test body');
        $this->em->flush();
        $tester = new \Symfony\Component\Console\Tester\CommandTester(static::getContainer()->get(\App\Command\MembershipDailyCommand::class));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count(['state' => 'pending']));
        unset($_ENV['MEMBERSHIP_MAIL_FROM'], $_SERVER['MEMBERSHIP_MAIL_FROM']);
        putenv('MEMBERSHIP_MAIL_FROM');
        self::assertSame(1, $tester->execute(['--send' => true]));
        self::assertStringContainsString('MEMBERSHIP_MAIL_FROM', $tester->getDisplay());
        $_ENV['MEMBERSHIP_MAIL_FROM'] = $_SERVER['MEMBERSHIP_MAIL_FROM'] = 'noreply@example.invalid';
        self::assertSame(0, $tester->execute(['--send' => true]));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count(['state' => 'sent']));
        self::assertSame(0, $tester->execute(['--send' => true]));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count(['state' => 'sent']));
    }

    public function testPublicApplicationApprovalUsesRealCsrfAndManualReviewForm(): void
    {
        $this->policy();
        $admin = $this->memberUser('review-admin', ['ROLE_ADMIN']);
        $application = $this->application();
        static::getContainer()->get(MembershipService::class)->submit($application);
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/membership/application/' . $application->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('select[name="membership_decision[person]"]'), 'A club admin must not receive a global person selector.');
        $this->client->submit($crawler->filter('form[name="membership_decision"]')->form(), [
            'membership_decision[decision]' => 'approve',
            'membership_decision[note]' => 'Identität und Antrag manuell anhand der Unterlagen geprüft.',
            'membership_decision[confirmed]' => '1',
        ]);
        self::assertResponseRedirects('/admin/membership/club/' . $this->club->getId());
        $this->em->clear();
        $stored = $this->em->find(MembershipApplication::class, $application->getId());
        self::assertSame('approved', $stored->getStatus());
        self::assertSame($this->club->getId(), $stored->getMember()->getClub()->getId());
        self::assertSame('00123', $stored->getMember()->getPerson()->getPostalCode());
        self::assertSame(2, $this->em->getRepository(Member::class)->count([]));
    }

    public function testSharedContactDoesNotAutomaticallyMergeOrDuplicateIdentity(): void
    {
        $this->policy();
        $person = (new Person())->setFirstname('Existing')->setLastname('Household')->setEmail('guest@example.invalid');
        $this->em->persist($person);
        $application = $this->application();
        $service = static::getContainer()->get(MembershipService::class);
        $service->submit($application);
        try {
            $service->approve($application, null, 'admin', 'Identity reviewed');
            self::fail('A shared contact requires an explicit identity decision.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('gemeinsam', $exception->getMessage());
        }
        self::assertSame('pending', $application->getStatus());
        self::assertSame(1, $this->em->getRepository(Person::class)->count([]));
        $member = $service->approve($application, null, 'superadmin', 'Andere Person im selben Haushalt anhand Ausweis bestätigt.', true);
        self::assertNotSame($person->getId(), $member->getPerson()->getId());
        self::assertSame(2, $this->em->getRepository(Person::class)->count([]));
    }

    public function testFinanceRejectsForeignClubAssignmentsAndCircularFamilyPayers(): void
    {
        $member = $this->memberUser('boundary')->getMember();
        $foreignClub = (new Club())->setName('Foreign finance club');
        $this->em->persist($foreignClub);
        $foreignPayer = $this->memberUser('foreign-payer', [], $foreignClub)->getMember();
        $foreignClass = new ContributionClass($foreignClub);
        $foreignClass->setName('Foreign family');
        $foreignClass->setAnnualCents(10000);
        $foreignClass->setFamily(true);
        $this->em->persist($foreignClass);
        $this->em->flush();
        $finance = static::getContainer()->get(MembershipFinanceService::class);
        $finance->assign($foreignPayer, $foreignClass, null, 0, null, 'admin');
        foreach ([[$foreignClass, null], [null, $foreignPayer], [null, $member]] as [$class, $payer]) {
            try {
                $finance->assign($member, $class, $payer, 0, null, 'admin');
                self::fail('Foreign or circular assignment must fail.');
            } catch (\DomainException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
        self::assertNull($member->getContributionClass());
        self::assertNull($member->getFamilyPayer());
    }

    public function testDepartedFamilyPayerQueuesAdministrativeReassignmentWarning(): void
    {
        $this->policy();
        $payer = $this->memberUser('departed-payer')->getMember();
        $child = $this->memberUser('remaining-family')->getMember();
        $class = new ContributionClass($this->club);
        $class->setName('Family exit');
        $class->setAnnualCents(10000);
        $class->setFamily(true);
        $this->em->persist($class);
        $this->em->flush();
        $finance = static::getContainer()->get(MembershipFinanceService::class);
        $finance->assign($payer, $class, null, 0, null, 'admin');
        $finance->assign($child, null, $payer, 0, null, 'admin');
        $payer->setTerminationDate(new \DateTimeImmutable('2026-12-31'));
        $this->em->flush();
        $automation = static::getContainer()->get(MembershipAutomation::class);
        self::assertSame(1, $automation->run(new \DateTimeImmutable('2027-01-01')));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count(['subject' => 'Familienbeitrag neu zuordnen']));
        $automation->run(new \DateTimeImmutable('2027-01-02'));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count(['subject' => 'Familienbeitrag neu zuordnen']));
        self::assertSame(0, $this->em->getRepository(MembershipCharge::class)->count([]));
    }

    public function testContributionClassCanBeEditedAndDeactivatedWithoutRewritingCharges(): void
    {
        $admin = $this->memberUser('class-admin', ['ROLE_ADMIN']);
        $class = new ContributionClass($this->club);
        $class->setName('Initial rate');
        $class->setAnnualCents(10000);
        $this->em->persist($class);
        $this->em->flush();
        $finance = static::getContainer()->get(MembershipFinanceService::class);
        $finance->assign($admin->getMember(), $class, null, 0, null, 'admin');
        $charge = $finance->annual($admin->getMember(), 2026, new \DateTimeImmutable('2026-12-01'), 'admin');
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/membership/contribution/' . $class->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="contribution_class"]')->form();
        $form['contribution_class[active]']->untick();
        $this->client->submit($form, ['contribution_class[name]' => 'Archived rate', 'contribution_class[annualCents]' => '150.00']);
        self::assertResponseRedirects('/admin/membership/club/' . $this->club->getId());
        $this->em->clear();
        self::assertFalse($this->em->find(ContributionClass::class, $class->getId())->isActive());
        self::assertSame(10000, $this->em->find(MembershipCharge::class, $charge->getId())->getAmountCents());
    }

    public function testLegacyDeleteLeadsToArchiveWithoutRemovingMembership(): void
    {
        $user = $this->memberUser('legacy-archive', ['ROLE_SUPER_ADMIN']);
        $memberId = $user->getMember()->getId();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/membership/member/' . $memberId);
        self::assertResponseIsSuccessful();
        $stack = static::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = static::getContainer()->get('security.csrf.token_manager')->getToken('delete' . $memberId)->getValue();
            $this->client->getRequest()->getSession()->save();
        } finally {
            $stack->pop();
        }
        $this->client->request('POST', '/member/' . $memberId, ['_token' => $token]);
        self::assertResponseRedirects('/admin/membership/member/' . $memberId);
        self::assertSame(1, $this->em->getRepository(Member::class)->count([]));
        $this->client->request('POST', '/member/' . $memberId, ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->em->getRepository(Member::class)->count([]));
    }

    private function memberUser(string $name, array $roles = [], ?Club $club = null): User
    {
        $member = (new Member())->setFirstname('Test')->setLastname($name)
            ->setBirthday(new \DateTime('2000-01-01'))->setEmail($name . '@example.invalid')
            ->setClub($club ?? $this->club)->setMemberNumber(strtoupper($name));
        $user = (new User())->setUser($name)->setEmail($name . '@example.invalid')
            ->setPassword('not-used')->setRoles($roles)->setIsVerified(true)->setMember($member);
        $this->em->persist($member);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function policy(): MembershipPolicy
    {
        $policy = new MembershipPolicy($this->club);
        $policy->setApplicationsEnabled(true);
        $policy->setContactEmail('membership-admin@example.invalid');
        $this->em->persist($policy);
        $this->em->flush();
        return $policy;
    }

    private function application(): MembershipApplication
    {
        $application = new MembershipApplication($this->club);
        $application->setFirstname('Public');
        $application->setLastname('Guest');
        $application->setEmail('guest@example.invalid');
        $application->setBirthday(new \DateTimeImmutable('2000-01-01'));
        $application->setStreet('Street 1');
        $application->setPostalCode('00123');
        $application->setCity('Test City');
        return $application;
    }
}
