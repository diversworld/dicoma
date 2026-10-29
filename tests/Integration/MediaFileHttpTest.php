<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{BarDutyPolicy, Club, ClubEvent, ClubEventDocument, ClubEventRegistration, ContributionClass, MediaFile, Member, MembershipPolicy, Person, User, WebsiteDisplaySettings, WebsitePublication};
use App\Enum\MembershipStatus;
use App\Service\MediaFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunTestsInSeparateProcesses};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MediaFileHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private User $press;
    private User $admin;
    private User $member;
    private string $root;
    /** @var list<string> */
    private array $paths = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'PUBLIC_SITE_CLUB_ID' => '1'] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value; putenv($key.'='.$value);
        }
        $this->root = sys_get_temp_dir().'/dicoma-media-test-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->client = static::createClient(['environment' => 'test']); $this->client->disableReboot();
        static::getContainer()->set(MediaFileStorage::class, new MediaFileStorage($this->root));
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Media club'); $this->em->persist($this->club); $this->em->flush();
        $this->press = $this->account('press', [User::ROLE_PRESS_OFFICER]);
        $this->admin = $this->account('admin', [User::ROLE_ADMIN]);
        $this->member = $this->account('member', []);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) { if (is_file($path)) { unlink($path); } }
        foreach ([$this->root.'/var/private/website_media', $this->root.'/var/private', $this->root.'/var', $this->root] as $dir) {
            if (is_dir($dir)) { rmdir($dir); }
        }
        parent::tearDown();
    }

    public function testProtectedUploadDownloadValidationAndClubIsolation(): void
    {
        $this->client->loginUser($this->press);
        $png = $this->upload('bild.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l7sAAAAASUVORK5CYII=', true), 'Testbild');
        self::assertSame('image/png', $png->getMimeType());
        $this->client->request('GET', '/admin/media');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img[src="/admin/media/'.$png->getId().'"]');
        $this->client->request('GET', '/admin/media/'.$png->getId());
        self::assertResponseIsSuccessful();
        self::assertSame('nosniff', $this->client->getResponse()->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->request('GET', '/admin/media/'.$png->getId().'/edit');
        $this->client->submitForm('Angaben speichern', ['form[originalName]' => 'Neu.png', 'form[altText]' => 'Neuer Alternativtext']);
        self::assertResponseRedirects('/admin/media', 303);
        $this->em->clear();
        $png = $this->em->find(MediaFile::class, $png->getId());
        self::assertSame('Neuer Alternativtext', $png->getAltText());
        $this->client->request('GET', '/admin/media/'.$png->getId().'/edit');
        $this->client->submitForm('Angaben speichern', ['form[originalName]' => '../bad', 'form[altText]' => '']);
        self::assertResponseStatusCodeSame(422);
        $this->em->clear();
        $png = $this->em->find(MediaFile::class, $png->getId());
        self::assertSame('Neu.png', $png->getOriginalName());
        $pdf = $this->upload('document.pdf', "%PDF-1.4\n%%EOF\n");
        $this->client->request('GET', '/admin/media/'.$pdf->getId());
        self::assertStringStartsWith('attachment', $this->client->getResponse()->headers->get('Content-Disposition'));

        $this->postUpload('fake.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'media_upload');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Erlaubt sind');
        self::assertSame(2, $this->em->getRepository(MediaFile::class)->count([]));
        $this->postUpload('file.pdf', "%PDF-1.4\n%%EOF\n", 'invalid');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(2, $this->em->getRepository(MediaFile::class)->count([]));
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/admin/media/'.$png->getId());
        self::assertResponseStatusCodeSame(403);
        $other = (new Club())->setName('Other club'); $this->em->persist($other); $this->em->flush();
        $foreign = $this->account('foreign', [User::ROLE_ADMIN], $other);
        $this->client->loginUser($foreign);
        $this->client->request('GET', '/admin/media/'.$png->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testOfficeFilesAreValidatedAndRemainPrivateDownloads(): void
    {
        $this->client->loginUser($this->press);
        foreach (['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'] as $extension => $entry) {
            $path = tempnam($this->root, 'office-'); $this->paths[] = $path;
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path, \ZipArchive::OVERWRITE));
            $zip->addFromString('[Content_Types].xml', '<Types/>');
            $zip->addFromString($entry, '<document/>');
            $zip->close();
            $file = $this->upload('synthetic.'.$extension, file_get_contents($path));
            self::assertFalse($file->isImage());
            $this->client->request('GET', '/admin/media/'.$file->getId());
            self::assertResponseIsSuccessful();
            self::assertStringStartsWith('attachment', $this->client->getResponse()->headers->get('Content-Disposition'));
        }
        $this->postUpload('fake.docx', 'Not an office document', 'media_upload');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Erlaubt sind');
        $this->postUpload('large.pdf', "%PDF-1.4\n".str_repeat('x', 10 * 1024 * 1024), 'media_upload');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'größer als 10 MB');
        self::assertSame(3, $this->em->getRepository(MediaFile::class)->count([]));
    }

    public function testPublicationImageRespectsVisibilityAndUsedFileCannotBeDeleted(): void
    {
        $this->client->loginUser($this->press);
        $image = $this->upload('bild.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l7sAAAAASUVORK5CYII=', true), 'Bildbeschreibung');
        $imageId = $image->getId();
        $this->client->request('GET', '/admin/de/editorial/new');
        $this->client->submitForm('Beitrag speichern', [
            'website_publication[kind]' => 'article', 'website_publication[title]' => 'Bildartikel',
            'website_publication[summary]' => 'Zusammenfassung', 'website_publication[body]' => 'Text',
            'website_publication[publishAt]' => '2026-01-01T12:00', 'website_publication[image]' => (string) $imageId,
        ]);
        self::assertResponseStatusCodeSame(303);
        $publication = $this->em->getRepository(WebsitePublication::class)->findOneBy([]);
        $id = $publication->getId();
        $this->client->request('GET', '/news/'.$id.'/image');
        self::assertResponseStatusCodeSame(404);
        $this->post('/admin/media/'.$imageId.'/delete', 'media_delete_'.$imageId);
        self::assertNotNull($this->em->find(MediaFile::class, $imageId));
        $this->em->find(WebsitePublication::class, $id)->setPublished(true); $this->em->flush();
        $this->client->request('GET', '/news/'.$id);
        self::assertSelectorExists('img[src="/news/'.$id.'/image"][alt="Bildbeschreibung"]');
        $this->client->request('GET', '/news/'.$id.'/image');
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
        $this->client->restart();
        $this->client->request('GET', '/news/'.$id.'/image');
        self::assertResponseIsSuccessful();
        $this->client->loginUser($this->press);
        $this->em->find(WebsitePublication::class, $id)->setImage(null); $this->em->flush();
        $this->post('/admin/media/'.$imageId.'/delete', 'media_delete_'.$imageId);
        self::assertNull($this->em->find(MediaFile::class, $imageId));
        self::assertFileDoesNotExist($this->root.'/var/private/website_media/'.$image->getStorageName());
    }

    public function testBookedMemberEventImageCanChangeWithoutMakingItPublic(): void
    {
        $this->client->loginUser($this->admin);
        $image = $this->upload('bild.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l7sAAAAASUVORK5CYII=', true));
        $event = new ClubEvent($this->em->find(Club::class, $this->club->getId()));
        $event->setTitle('Members only'); $event->setDescription('Private event'); $event->setLocation('Club');
        $event->setStartsAt(new \DateTimeImmutable('+10 days')); $event->setEndsAt(new \DateTimeImmutable('+11 days')); $event->setPublished(true);
        $registration = new ClubEventRegistration($event, $this->em->find(Person::class, $this->member->getPerson()->getId()), 'registered', 0, 'member');
        $this->em->persist($event); $this->em->persist($registration); $this->em->flush();
        $id = $event->getId();
        $this->client->request('GET', '/admin/club-events/'.$id.'/image');
        $this->client->submitForm('Bild speichern', ['form[image]' => (string) $image->getId()]);
        self::assertResponseRedirects('/admin/club-events/'.$id, 303);
        $this->em->clear();
        self::assertSame('Members only', $this->em->find(ClubEvent::class, $id)->getTitle());
        self::assertNotNull($this->em->find(ClubEvent::class, $id)->getImage());
        $this->post('/admin/media/'.$image->getId().'/delete', 'media_delete_'.$image->getId());
        self::assertNotNull($this->em->find(MediaFile::class, $image->getId()));
        $this->client->restart();
        $this->client->request('GET', '/events/'.$id.'/image');
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/events/'.$id.'/image');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->em->find(ClubEvent::class, $id)->cancel(); $this->em->flush();
        $this->client->request('GET', '/events/'.$id.'/image');
        self::assertResponseStatusCodeSame(404);
    }

    public function testForeignFilesAndDocumentsCannotBeSelectedOrExposedAsImages(): void
    {
        $other = (new Club())->setName('Other club');
        $this->em->persist($other);
        $foreign = new MediaFile($other, str_repeat('a', 40).'.png', 'foreign.png', 'image/png', 10, '');
        $document = new MediaFile($this->club, str_repeat('b', 40).'.pdf', 'doc.pdf', 'application/pdf', 10, '');
        $this->em->persist($foreign); $this->em->persist($document); $this->em->flush();
        $this->client->loginUser($this->press);
        foreach ([$foreign, $document] as $file) {
            $this->client->request('GET', '/admin/de/editorial/new');
            self::assertSelectorNotExists('select[name="website_publication[image]"] option[value="'.$file->getId().'"]');
            $form = $this->client->getCrawler()->selectButton('Beitrag speichern')->form();
            $values = $form->getPhpValues();
            $values['website_publication'] = array_replace($values['website_publication'], [
                'kind' => 'article', 'title' => 'Forged', 'summary' => 'Summary', 'body' => 'Body',
                'publishAt' => '2026-01-01T12:00', 'image' => (string) $file->getId(),
            ]);
            $this->client->request('POST', '/admin/de/editorial/new', $values);
            self::assertResponseStatusCodeSame(422);
        }
        self::assertSame(0, $this->em->getRepository(WebsitePublication::class)->count([]));
        $publication = new WebsitePublication($this->em->find(Club::class, $this->club->getId()));
        $publication->setTitle('Test'); $publication->setSummary('Test'); $publication->setBody('Test');
        $publication->setPublished(true); $publication->setPublishAt(new \DateTimeImmutable('-1 hour'));
        $publication->setImage($this->em->find(MediaFile::class, $foreign->getId()));
        $this->em->persist($publication); $this->em->flush();
        $this->client->request('GET', '/news/'.$publication->getId().'/image');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCentralConfigurationRespectsRolesAndPersistsEachSection(): void
    {
        $manager = $this->account('bar-manager', [User::ROLE_BAR_MANAGER]);
        $super = $this->account('super', [User::ROLE_SUPER_ADMIN]);
        $other = (new Club())->setName('Other club'); $this->em->persist($other); $this->em->flush();
        $this->client->loginUser($this->press);
        $crawler = $this->client->request('GET', '/admin/configuration');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="website_display_settings"]');
        self::assertSelectorNotExists('form[name="membership_policy"]');
        self::assertSelectorNotExists('form[name="bar_duty_policy"]');
        $this->client->submit($crawler->filter('form[name="website_display_settings"]')->form(), [
            'website_display_settings[articlesPerPage]' => '4', 'website_display_settings[eventsPerPage]' => '7',
        ]);
        self::assertResponseRedirects();
        self::assertSame(4, $this->em->find(WebsiteDisplaySettings::class, $this->club->getId())->getArticlesPerPage());
        $this->client->request('POST', '/admin/configuration', ['bar_duty_policy' => ['_token' => $this->token('bar_duty_policy_0'), 'minutes' => '60']]);
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($manager);
        $this->client->request('GET', '/admin/configuration?year=2028');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="website_display_settings"]');
        self::assertSelectorNotExists('form[name="membership_policy"]');
        self::assertSelectorExists('input[name="year"][value="2028"].form-control');
        self::assertSelectorExists('input[name="bar_duty_policy[minutes]"].form-control');
        self::assertSelectorExists('input[name="bar_duty_policy[cash]"].form-check-input');
        $this->client->submitForm('Jahresregel speichern', [
            'bar_duty_policy[minutes]' => '120', 'bar_duty_policy[cash]' => '1', 'bar_duty_policy[reason]' => 'Beschluss des Vereins',
        ]);
        self::assertResponseRedirects('/admin/configuration?year=2028', 303);
        self::assertSame(120, $this->em->getRepository(BarDutyPolicy::class)->findOneBy(['club' => $this->club, 'year' => 2028])->getRequiredMinutes());
        $crawler = $this->client->request('GET', '/admin/configuration?year=2028');
        $this->client->submit($crawler->filter('form[name="bar_duty_policy"]')->form(), [
            'bar_duty_policy[minutes]' => '60001', 'bar_duty_policy[reason]' => 'Zu kurz',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#bar_duty_policy_minutes.is-invalid');
        self::assertSelectorExists('#bar_duty_policy_reason.is-invalid');
        self::assertSame(120, $this->em->getRepository(BarDutyPolicy::class)->findOneBy(['club' => $this->club, 'year' => 2028])->getRequiredMinutes());
        foreach (['0', '60000'] as $minutes) {
            $crawler = $this->client->request('GET', '/admin/configuration?year=2028');
            $this->client->submit($crawler->filter('form[name="bar_duty_policy"]')->form(), [
                'bar_duty_policy[minutes]' => $minutes, 'bar_duty_policy[reason]' => 'Beschluss des Vereins',
            ]);
            self::assertResponseRedirects('/admin/configuration?year=2028', 303);
            self::assertSame((int) $minutes, $this->em->getRepository(BarDutyPolicy::class)->findOneBy(['club' => $this->club, 'year' => 2028])->getRequiredMinutes());
        }
        $crawler = $this->client->request('GET', '/admin/configuration?year=2028');
        $this->client->submit($crawler->filter('form[name="bar_duty_policy"]')->form(), [
            'bar_duty_policy[minutes]' => '1.5', 'bar_duty_policy[reason]' => '         x         ',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#bar_duty_policy_minutes.is-invalid');
        self::assertSelectorExists('#bar_duty_policy_reason.is-invalid');
        self::assertSame(60000, $this->em->getRepository(BarDutyPolicy::class)->findOneBy(['club' => $this->club, 'year' => 2028])->getRequiredMinutes());
        $this->client->request('POST', '/admin/configuration?year=2028', ['bar_duty_policy' => ['_token' => 'invalid', 'minutes' => '10']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/configuration?year=1999');
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/admin/configuration');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="membership_policy"]')->form(), [
            'membership_policy[contactEmail]' => 'test@example.org',
            'membership_policy[cancellationCutoff]' => '09-30', 'membership_policy[membershipYearEnd]' => '12-31',
        ]);
        self::assertResponseRedirects();
        self::assertSame('09-30', $this->em->getRepository(MembershipPolicy::class)->findOneBy(['club' => $this->club])->getCancellationCutoff());
        $crawler = $this->client->request('GET', '/admin/configuration');
        $this->client->submit($crawler->filter('form[name="contribution_class"]')->form(), [
            'contribution_class[name]' => 'Standard', 'contribution_class[annualCents]' => '123.45',
        ]);
        self::assertResponseRedirects();
        $class = $this->em->getRepository(ContributionClass::class)->findOneBy(['club' => $this->club, 'name' => 'Standard']);
        self::assertSame(12345, $class->getAnnualCents());
        $crawler = $this->client->request('GET', '/admin/configuration');
        $name = 'contribution_class_'.$class->getId();
        $this->client->submit($crawler->filter('form[name="'.$name.'"]')->form(), [$name.'[annualCents]' => '150.00']);
        self::assertResponseRedirects();
        self::assertSame(15000, $this->em->find(ContributionClass::class, $class->getId())->getAnnualCents());
        $this->client->request('GET', '/admin/configuration?club='.$other->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($super);
        $crawler = $this->client->request('GET', '/admin/configuration?club='.$other->getId().'&year=2029');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Mitgliedschaft – Other club');
        self::assertSelectorTextContains('body', 'Thekendienst-Jahresregel – Media club');
        self::assertSelectorExists('select[name="club"].form-select');
        $this->client->submitForm('Jahr auswählen', ['year' => '2030'], 'GET');
        self::assertResponseIsSuccessful();
        self::assertSame('/admin/configuration?club='.$other->getId().'&year=2030', $this->client->getRequest()->getRequestUri());
        self::assertSelectorTextContains('body', 'Mitgliedschaft – Other club');
        $this->client->submitForm('Verein auswählen', ['club' => (string) $this->club->getId()], 'GET');
        self::assertResponseIsSuccessful();
        self::assertSame('/admin/configuration?club='.$this->club->getId().'&year=2030', $this->client->getRequest()->getRequestUri());
        $crawler = $this->client->request('GET', '/admin/configuration?club='.$other->getId().'&year=2029');
        $this->client->submit($crawler->filter('form[name="membership_policy"]')->form(), [
            'membership_policy[contactEmail]' => 'other@example.org',
        ]);
        self::assertResponseRedirects();
        self::assertSame('other@example.org', $this->em->getRepository(MembershipPolicy::class)->findOneBy(['club' => $other])->getContactEmail());
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/admin/configuration');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEventDocumentsAreScopedToConfirmedRegistrationsAndSeparateFromMedia(): void
    {
        $otherMember = $this->account('second', []);
        $waiting = $this->account('waiting', []);
        $ineligible = $this->account('ineligible', []);
        $guestPerson = (new Person())->setFirstname('Guest')->setLastname('Test')->setEmail('guest@example.invalid');
        $guest = (new User())->setUser('guest')->setEmail('guest@example.invalid')->setPassword('unused')->setIsVerified(true)->setPerson($guestPerson);
        $this->em->persist($guestPerson); $this->em->persist($guest);
        $otherClub = (new Club())->setName('Foreign'); $this->em->persist($otherClub); $this->em->flush();
        $foreignAdmin = $this->account('foreign-admin', [User::ROLE_ADMIN], $otherClub);
        $event = new ClubEvent($this->club);
        $event->setTitle('Document event'); $event->setDescription('Test'); $event->setLocation('Club');
        $event->setStartsAt(new \DateTimeImmutable('+10 days')); $event->setEndsAt(new \DateTimeImmutable('+11 days')); $event->setPublished(true);
        $this->em->persist($event); $this->em->flush();
        $registrations = [];
        foreach ([$this->member, $otherMember, $waiting, $ineligible, $guest] as $i => $user) {
            $r = new ClubEventRegistration($event, $user->getPerson(), $i === 2 ? 'waitlisted' : 'registered', 0, 'member');
            if ($i === 3) { $r->disqualify(); }
            $this->em->persist($r); $registrations[] = $r;
        }

        $this->em->flush();
        $id = $event->getId(); $first = $registrations[0]->getId(); $second = $registrations[1]->getId(); $third = $registrations[2]->getId();
        $fourth = $registrations[3]->getId(); $guestId = $registrations[4]->getId();
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/club-events/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="registration"] option[value="'.$first.'"]');
        $shared = $this->uploadEventDocument($id, 'all', '', 'all.pdf');
        $personal = $this->uploadEventDocument($id, 'individual', (string) $first, 'personal.pdf');
        $this->client->request('GET', '/admin/media');
        self::assertSelectorTextNotContains('body', 'personal.pdf');
        self::assertSame(0, $this->em->getRepository(MediaFile::class)->count([]));
        $this->client->request('GET', '/admin/club-events/'.$id.'/documents/'.$personal);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/admin/club-events/'.$id.'/documents', ['_token' => 'invalid', 'audience' => 'all']);
        self::assertResponseStatusCodeSame(403);
        $token = $this->token('event_document_upload_'.$id);
        $this->client->request('POST', '/admin/club-events/'.$id.'/documents', ['_token' => $token, 'audience' => 'individual', 'registration' => '999999']);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/admin/club-events/'.$id.'/documents', ['_token' => $token, 'audience' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($foreignAdmin);
        $this->client->request('GET', '/admin/club-events/'.$id.'/documents/'.$personal);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/admin/club-events/'.$id.'/documents/'.$personal);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/event-registrations/'.$first);
        self::assertSelectorTextContains('body', 'all.pdf');
        self::assertSelectorTextContains('body', 'personal.pdf');
        $this->client->request('GET', '/event-registrations/'.$first.'/documents/'.$personal);
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('attachment', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->loginUser($otherMember);
        $this->client->request('GET', '/event-registrations/'.$second.'/documents/'.$shared);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/event-registrations/'.$second.'/documents/'.$personal);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/event-registrations/'.$first.'/documents/'.$shared);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($waiting);
        $this->client->request('GET', '/event-registrations/'.$third.'/documents/'.$shared);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($ineligible);
        $this->client->request('GET', '/event-registrations/'.$fourth.'/documents/'.$shared);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($guest);
        $this->client->request('GET', '/event-registrations/'.$guestId.'/documents/'.$shared);
        self::assertResponseIsSuccessful();
        $this->em->find(ClubEventRegistration::class, $first)->cancel(); $this->em->flush();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/event-registrations/'.$first.'/documents/'.$shared);
        self::assertResponseStatusCodeSame(404);
        $this->em->find(ClubEvent::class, $id)->cancel(); $this->em->flush();
        $this->client->loginUser($otherMember);
        $this->client->request('GET', '/event-registrations/'.$second.'/documents/'.$shared);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->admin);
        $this->post('/admin/club-events/'.$id.'/documents/'.$personal.'/delete', 'event_document_delete_'.$personal);
        self::assertNull($this->em->find(ClubEventDocument::class, $personal));
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/event-registrations/'.$guestId.'/documents/'.$shared);
        self::assertResponseRedirects();
    }

    public function testEventDocumentUploadRejectsUnsupportedAndOversizedFiles(): void
    {
        $event = new ClubEvent($this->club);
        $event->setTitle('Document event'); $event->setDescription('Test'); $event->setLocation('Club');
        $event->setStartsAt(new \DateTimeImmutable('+10 days')); $event->setEndsAt(new \DateTimeImmutable('+11 days'));
        $this->em->persist($event); $this->em->flush();
        $id = $event->getId();
        $this->client->loginUser($this->admin);
        foreach (['bad.svg' => '<svg></svg>', 'large.pdf' => null] as $name => $content) {
            $path = tempnam($this->root, 'upload-'); $this->paths[] = $path;
            if ($content !== null) { file_put_contents($path, $content); }
            else {
                $stream = fopen($path, 'wb'); fwrite($stream, '%PDF-1.4'); ftruncate($stream, 10 * 1024 * 1024 + 1); fclose($stream);
            }
            $token = $this->token('event_document_upload_'.$id);
            $this->client->request('POST', '/admin/club-events/'.$id.'/documents', ['_token' => $token, 'audience' => 'all'],
                ['file' => new UploadedFile($path, $name, null, null, true)]);
            self::assertResponseRedirects('/admin/club-events/'.$id, 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('body', $content !== null ? 'Erlaubt sind' : 'größer als 10 MB');
            self::assertSame(0, $this->em->getRepository(ClubEventDocument::class)->count([]));
        }
    }

    private function uploadEventDocument(int $eventId, string $audience, string $registration, string $name): int
    {
        $path = tempnam($this->root, 'upload-'); $this->paths[] = $path;
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        $token = $this->token('event_document_upload_'.$eventId);
        $this->client->request('POST', '/admin/club-events/'.$eventId.'/documents', [
            '_token' => $token, 'audience' => $audience, 'registration' => $registration,
        ], ['file' => new UploadedFile($path, $name, null, null, true)]);
        self::assertResponseRedirects('/admin/club-events/'.$eventId, 303);
        $document = $this->em->getRepository(ClubEventDocument::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($document);
        $this->paths[] = $this->root.'/var/private/website_media/'.$document->file()->getStorageName();
        return $document->getId();
    }

    private function account(string $name, array $roles, ?Club $club = null): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Test')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($club ?? $this->club)->setPerson($person)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setIsVerified(true)->setMember($member)->setRoles($roles);
        $this->em->persist($user); $this->em->flush(); return $user;
    }

    private function upload(string $name, string $content, string $alt = ''): MediaFile
    {
        $this->postUpload($name, $content, 'media_upload', $alt);
        self::assertResponseRedirects('/admin/media', 303);
        $file = $this->em->getRepository(MediaFile::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($file);
        $this->paths[] = $this->root.'/var/private/website_media/'.$file->getStorageName();
        return $file;
    }

    private function postUpload(string $name, string $content, string $token, string $alt = ''): void
    {
        $path = tempnam($this->root, 'upload-');
        file_put_contents($path, $content); $this->paths[] = $path;
        $csrf = $token === 'invalid' ? 'invalid' : $this->token($token);
        $this->client->request('POST', '/admin/media/upload', ['_token' => $csrf, 'alt_text' => $alt],
            ['file' => new UploadedFile($path, $name, null, null, true)]);
    }

    private function post(string $url, string $token): void
    {
        $this->client->request('POST', $url, ['_token' => $this->token($token)]);
    }

    private function token(string $token): string
    {
        $this->client->request('GET', '/admin/media');
        $stack = static::getContainer()->get(RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $value = static::getContainer()->get('security.csrf.token_manager')->getToken($token)->getValue();
            $this->client->getRequest()->getSession()->save();
        } finally { $stack->pop(); }
        return $value;
    }
}
