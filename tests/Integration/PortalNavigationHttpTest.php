<?php

declare(strict_types=1);
namespace App\Tests\Integration;

use App\Entity\{BarCashEntry, BarCashSession, BarDutyAssignment, BarDutyPolicy, BarShift, BarShiftDefinition, Club, CourseParticipant, Courses, EquipmentCategory, EquipmentItem, Member, MemberDocument, MemberQualification, MemberTank, MemberTankCheckBooking, Person, Qualification, Sport, TankCheck, TankCheckArticle, Team, TeamMember, User};
use App\Enum\CourseParticipantStatus;
use App\Enum\MembershipStatus;
use App\Enum\TeamMemberRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\{DataProvider, PreserveGlobalState, RunTestsInSeparateProcesses};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PortalNavigationHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'PUBLIC_SITE_CLUB_ID' => '1', 'SITE_THEME' => 'airspace'] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value; putenv($name.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->em->getConnection()->executeStatement('CREATE TABLE equipment_cash_correction (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INT NOT NULL, source_type VARCHAR(20), source_id INT, direction VARCHAR(10), amount_cents INT, reason VARCHAR(500), created_by VARCHAR(180), created_at DATETIME)');
        $this->club = (new Club())->setName('Portal club');
        $this->em->persist($this->club); $this->em->flush();
    }

    #[DataProvider('roles')]
    public function testCommonLoginChoosesTheCorrectArea(array $roles, string $target): void
    {
        $user = $this->account('login-user', $roles);
        $this->client->loginUser($user);
        $this->client->request('GET', '/login');
        self::assertResponseRedirects($target);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        if (str_starts_with($target, '/admin')) {
            self::assertSelectorExists('aside.sidebar');
            self::assertSelectorNotExists('#frontendNavbar');
            $this->assertNoAreaCrossing(true);
        } else {
            self::assertSelectorExists('#frontendNavbar');
            self::assertSelectorExists('body[data-site-theme="airspace"]');
            $this->assertNoAreaCrossing(false);
        }
    }

    public static function roles(): iterable
    {
        yield 'member' => [[], '/my'];
        yield 'instructor' => [[User::ROLE_INSTRUCTOR], '/my'];
        yield 'equipment manager' => [[User::ROLE_EQUIPMENT_MANAGER], '/my'];
        yield 'board' => [[User::ROLE_BOARD], '/admin'];
        yield 'administrator' => [[User::ROLE_ADMIN], '/admin'];
        yield 'training manager' => [[User::ROLE_TRAINING_MANAGER], '/admin/de/training-overview'];
        yield 'course administrator' => [[User::ROLE_COURSE_ADMIN], '/admin/training'];
        yield 'clubhouse manager' => [[User::ROLE_BAR_MANAGER], '/admin/bar-duty'];
        yield 'press officer' => [[User::ROLE_PRESS_OFFICER], '/admin/de/editorial'];
        yield 'explicit frontend and backend role' => [[User::ROLE_ADMIN, User::ROLE_INSTRUCTOR], '/my'];
    }

    #[DataProvider('membershipNavigationCases')]
    public function testMembershipApplicationNavigationIsHiddenOnlyForActiveMembers(string $case, bool $visible): void
    {
        if ($case === 'guest') {
            $person = (new Person())->setFirstname('Guest')->setLastname('Synthetic')->setEmail('guest@example.invalid');
            $user = (new User())->setUser('guest')->setEmail('guest@example.invalid')->setPassword('unused')
                ->setIsVerified(true)->setPerson($person);
            $this->em->persist($user); $this->em->flush();
            $this->client->loginUser($user);
        } elseif ($case !== 'anonymous') {
            $user = $this->account('member', $case === 'staff-member' ? [User::ROLE_INSTRUCTOR] : [User::ROLE_MEMBER]);
            if ($case === 'former') {
                $user->getMember()->setMembershipStatus(MembershipStatus::FORMER);
                $this->em->flush();
            }
            $this->client->loginUser($user);
        }
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        foreach (['#frontendNavbar', 'footer'] as $container) {
            if ($visible) {
                self::assertSelectorExists($container.' a[href="/membership/apply"]');
            } else {
                self::assertSelectorNotExists($container.' a[href="/membership/apply"]');
            }
        }
    }

    public static function membershipNavigationCases(): iterable
    {
        yield 'anonymous visitor' => ['anonymous', true];
        yield 'signed-in guest' => ['guest', true];
        yield 'former member' => ['former', true];
        yield 'active member' => ['member', false];
        yield 'active member with function' => ['staff-member', false];
    }

    #[DataProvider('credentialLogins')]
    public function testCredentialLoginUsesRoleDestinationAndPreservesSavedTarget(array $roles, ?string $savedTarget, string $expected): void
    {
        $user = $this->account('credential-login', $roles);
        $user->setPassword(static::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class)->hashPassword($user, 'Synthetic-Test-Password-2026!'));
        $this->em->flush();
        if ($savedTarget !== null) {
            $this->client->request('GET', $savedTarget);
            self::assertResponseRedirects('/login');
        }
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form(), [
            '_username' => 'credential-login', '_password' => 'Synthetic-Test-Password-2026!',
        ]);
        if ($savedTarget !== null) {
            self::assertSame($expected, parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_PATH));
        } else {
            self::assertResponseRedirects($expected);
        }
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->assertNoAreaCrossing(str_starts_with($expected, '/admin'));
    }

    public static function credentialLogins(): iterable
    {
        yield 'pure administrator' => [[User::ROLE_ADMIN], null, '/admin'];
        yield 'frontend instructor' => [[User::ROLE_INSTRUCTOR], null, '/my'];
        yield 'saved personal destination' => [[], '/my/bookings', '/my/bookings'];
        yield 'saved backend destination' => [[User::ROLE_BAR_MANAGER], '/admin/bar-duty/settings', '/admin/bar-duty/settings'];
    }

    #[DataProvider('frontendPages')]
    public function testAllFrontendRolesKeepTheSameThemeAndNeverLinkToBackend(string $path): void
    {
        $user = $this->account('frontend-staff', [User::ROLE_INSTRUCTOR, User::ROLE_EQUIPMENT_MANAGER, User::ROLE_COMPRESSOR_OPERATOR]);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body[data-site-theme="airspace"] #frontendNavbar');
        self::assertSelectorNotExists('aside.sidebar');
        self::assertSelectorExists('link[href="/css/pages/portal-navigation.css"]');
        $ids = $crawler->filter('[id]')->extract(['id']);
        self::assertSame(count($ids), count(array_unique($ids)));
        $this->assertNoAreaCrossing(false);
    }

    public static function frontendPages(): iterable
    {
        foreach (['/my', '/my/bookings', '/training', '/profile', '/profile/membership', '/equipment-manager',
            '/equipment-manager/loans', '/equipment-manager/inventory', '/equipment-manager/cashbook',
            '/equipment-manager/compressor-evenings', '/equipment-manager/fill-billing',
            '/instructor-portal', '/instructor-portal/courses', '/instructor-portal/tasks', '/bar-duty', '/bar-duty/cash', '/calendar'] as $path) {
            yield $path => [$path];
        }
    }

    public function testProfileLanguagePersistsForEmptyAndPopulatedStates(): void
    {
        $user = $this->account('profile-translations');
        $memberId = $user->getMember()->getId();
        $clubId = $this->club->getId();
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['Mein Mitgliederbereich', 'Noch keine Qualifikationen hinterlegt.', 'Abgelaufen', 'Läuft bald ab', 'Gültig', 'Unbefristet', 'Trainer/in', 'Dokument wirklich löschen?', 'Bitte eine Datei auswaehlen.'],
            'en' => ['My member area', 'No qualifications recorded yet.', 'Expired', 'Expires soon', 'Valid', 'No expiry date', 'Coach', 'Really delete this document?', 'Please select a file.'],
            'fr' => ['Mon espace membre', 'Aucune qualification enregistrée pour le moment.', 'Expirée', 'Expire bientôt', 'Valide', 'Sans expiration', 'Entraîneur / entraîneuse', 'Voulez-vous vraiment supprimer ce document ?', 'Veuillez sélectionner un fichier.'],
            'es' => ['Mi área de socio', 'Aún no hay cualificaciones registradas.', 'Caducada', 'Caduca pronto', 'Válida', 'Sin vencimiento', 'Entrenador/a', '¿Eliminar realmente este documento?', 'Seleccione un archivo.'],
        ];
        foreach ($expectations as $locale => [$heading, $empty]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/profile');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $heading);
            self::assertSelectorTextContains('body', $empty);
        }

        $member = $this->em->find(Member::class, $memberId);
        $club = $this->em->find(Club::class, $clubId);
        foreach (['-1 day', '+10 days', '+60 days', null] as $index => $expiry) {
            $qualification = (new Qualification())->setName('Fixture qualification '.$index);
            $record = (new MemberQualification())->setMember($member)->setQualification($qualification)
                ->setValidUntil($expiry === null ? null : new \DateTimeImmutable($expiry));
            $this->em->persist($qualification);
            $this->em->persist($record);
        }
        $sport = (new Sport())->setClub($club)->setName('Fixture sport');
        $team = (new Team())->setSport($sport)->setName('Fixture team');
        $assignment = (new TeamMember())->setTeam($team)->setMember($member)->setRole(TeamMemberRole::TRAINER);
        $document = new MemberDocument($member, str_repeat('a', 40).'.pdf', 'fixture.pdf', 'application/pdf', 100);
        foreach ([$sport, $team, $assignment, $document] as $entity) { $this->em->persist($entity); }
        $this->em->flush();

        foreach ($expectations as $locale => [$heading, $empty, $expired, $soon, $valid, $unlimited, $role, $confirm, $error]) {
            $this->client->request('GET', '/portal/'.$locale);
            $crawler = $this->client->request('GET', '/profile');
            self::assertResponseIsSuccessful();
            foreach ([$heading, $expired, $soon, $valid, $unlimited, $role, 'fixture.pdf'] as $text) {
                self::assertSelectorTextContains('body', $text);
            }
            $deleteForm = $crawler->filter('form[action$="/delete"]');
            self::assertSame('return confirm('.json_encode($confirm).')', $deleteForm->attr('onsubmit'));
            $form = $crawler->filter('form[action="/profile/documents"]')->form();
            $values = $form->getPhpValues();
            $this->client->request('POST', '/profile/documents', $values);
            self::assertResponseRedirects('/profile');
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="status"]', $error);
        }
    }

    public function testFirstLocaleSelectionPersistsForAnonymousVisitor(): void
    {
        $this->client->request('GET', '/portal/fr');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="fr"]');
    }

    public function testMemberDashboardIsTranslatedForEmptyAndPopulatedStates(): void
    {
        $user = $this->account('dashboard-translations');
        $memberId = $user->getMember()->getId();
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['Mein Mitgliederbereich', 'Du hast noch keine Flasche zum TÜV angemeldet.', '1 Prüffrist(en) bald fällig oder überfällig', 'Beim Gerätewart'],
            'en' => ['My member area', 'You have not registered a cylinder for inspection yet.', '1 inspection deadline(s) approaching or overdue', 'With the equipment manager'],
            'fr' => ['Mon espace membre', 'Vous n’avez pas encore inscrit de bouteille au contrôle.', '1 échéance(s) de contrôle proche(s) ou dépassée(s)', 'Chez le responsable du matériel'],
            'es' => ['Mi área de socio', 'Aún no ha inscrito ninguna botella para inspección.', '1 plazo(s) de inspección próximo(s) o vencido(s)', 'Con el responsable del equipo'],
        ];
        foreach ($expectations as $locale => [$heading, $empty]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/my');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $heading);
            self::assertSelectorTextContains('main', $empty);
        }
        $member = $this->em->find(Member::class, $memberId);
        $tank = (new MemberTank())->setMember($member)->setLabel('Fixture cylinder')->setSerialNumber('FIXTURE-001')
            ->setNextInspectionAt(new \DateTimeImmutable('yesterday'));
        $check = (new TankCheck())->setCheckDate(new \DateTime('tomorrow'))->setIsPublished(true);
        $booking = (new MemberTankCheckBooking())->setMember($member)->setMemberTank($tank)->setTankCheck($check)->setStatus('received');
        foreach ([$tank, $check, $booking] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        foreach ($expectations as $locale => [$heading, $empty, $due, $status]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/my');
            self::assertResponseIsSuccessful();
            foreach ([$heading, $due, $status, 'Fixture cylinder'] as $text) {
                self::assertSelectorTextContains('main', $text);
            }
            self::assertStringNotContainsString($empty, $this->client->getCrawler()->filter('main')->text());
        }
    }

    public function testMemberEquipmentTranslationsAndValidationAcrossStates(): void
    {
        $user = $this->account('equipment-translations');
        $memberId = $user->getMember()->getId();
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['Meine Ausrüstung', 'Erste Flasche hinzufügen', 'Überfällig', 'Flasche bearbeiten', 'Bitte Bezeichnung, Seriennummer und Flaschengröße gültig eingeben.'],
            'en' => ['My equipment', 'Add your first cylinder', 'Overdue', 'Edit tank', 'Please enter a valid label, serial number and cylinder size.'],
            'fr' => ['Mon équipement', 'Ajouter votre première bouteille', 'En retard', 'Modifier la bouteille', 'Veuillez saisir une désignation, un numéro de série et une taille valides.'],
            'es' => ['Mi equipo', 'Añadir la primera botella', 'Vencida', 'Editar botella', 'Introduzca una denominación, número de serie y tamaño válidos.'],
        ];
        foreach ($expectations as $locale => [$heading, $empty]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/my/equipment');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $heading);
            self::assertSelectorTextContains('#meine-flaschen', $empty);
        }
        $member = $this->em->find(Member::class, $memberId);
        $tank = (new MemberTank())->setMember($member)->setLabel('Fixture cylinder')->setSerialNumber('FIXTURE-001')
            ->setVolume('12')->setWaterCapacityLiters('12.00')->setNextInspectionAt(new \DateTimeImmutable('yesterday'));
        $check = (new TankCheck())->setCheckDate(new \DateTime('tomorrow'))->setIsPublished(true);
        $booking = (new MemberTankCheckBooking())->setMember($member)->setMemberTank($tank)->setTankCheck($check)->setStatus('received');
        foreach ([$tank, $check, $booking] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $tankId = $tank->getId();
        $bookingId = $booking->getId();
        $details = [
            'de' => ['Meine TÜV-Anmeldung', 'Beim Gerätewart', 'Für diese Anmeldung sind keine Einzelpositionen gespeichert.', 'Noch keine Rechnung ausgestellt.'],
            'en' => ['My cylinder inspection registration', 'With the equipment manager', 'No individual items are recorded for this registration.', 'No invoice has been issued yet.'],
            'fr' => ['Mon inscription au contrôle de bouteille', 'Chez le responsable du matériel', 'Aucune prestation individuelle n’est enregistrée pour cette inscription.', 'Aucune facture n’a encore été émise.'],
            'es' => ['Mi inscripción a la inspección de la botella', 'Con el responsable del equipo', 'No hay partidas individuales registradas para esta inscripción.', 'Aún no se ha emitido ninguna factura.'],
        ];
        foreach ($expectations as $locale => [$heading, $empty, $due, $edit, $error]) {
            $this->client->request('GET', '/portal/'.$locale);
            $crawler = $this->client->request('GET', '/my/equipment');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#meine-flaschen', $due);
            self::assertSelectorTextContains('#meine-flaschen a[href$="/edit"]', $edit);
            self::assertSelectorTextContains('#meine-anmeldungen', 'Fixture cylinder');
            $token = $crawler->filter('form[action="/my/equipment/tanks"] input[name="_token"]')->attr('value');
            $this->client->request('POST', '/my/equipment/tanks', ['_token' => $token, 'label' => 'x']);
            self::assertResponseRedirects('/my/equipment', 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', $error);
            $this->client->request('GET', '/my/equipment/tanks/'.$tankId.'/edit');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('title', $edit);
            self::assertSelectorExists('select[name="volume"] option[value="12"]:checked');
            $this->client->request('GET', '/my/equipment/bookings/'.$bookingId);
            self::assertResponseIsSuccessful();
            foreach ($details[$locale] as $expected) {
                self::assertSelectorTextContains('section.section', $expected);
            }
        }
    }

    public function testTrainingOverviewIsTranslatedWithAndWithoutEnrollments(): void
    {
        $user = $this->account('training-translations');
        $personId = $user->getPerson()->getId();
        $clubId = $this->club->getId();
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['Ausbildungsportal', 'Noch keine Kursanmeldung vorhanden.', 'Angemeldet'],
            'en' => ['Training portal', 'No course registrations yet.', 'Registered'],
            'fr' => ['Portail de formation', 'Aucune inscription à un cours pour le moment.', 'Inscrit'],
            'es' => ['Portal de formación', 'Aún no hay inscripciones a cursos.', 'Inscrito'],
        ];
        foreach ($expectations as $locale => [$heading, $empty]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/training');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $heading);
            self::assertSelectorTextContains('main', $empty);
            self::assertSelectorExists('nav[aria-label="'.$heading.'"]');
        }
        $person = $this->em->find(Person::class, $personId);
        $club = $this->em->find(Club::class, $clubId);
        $course = (new Courses())->setTitle('Fixture training course')->setCategory('beginner')->setClub($club);
        $participant = (new CourseParticipant())->setCourse($course)->setPerson($person);
        $this->em->persist($course);
        $this->em->persist($participant);
        $this->em->flush();
        foreach ($expectations as $locale => [$heading, $empty, $status]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/training');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', 'Fixture training course');
            self::assertSelectorTextContains('main', $status);
            self::assertStringNotContainsString($empty, $this->client->getCrawler()->filter('main')->text());
        }
    }

    public function testMultipleInspectionRegistrationTranslationsForEmptyAndInvalidStates(): void
    {
        $user = $this->account('inspection-multiple-translations');
        $check = (new TankCheck())->setCheckDate(new \DateTime('tomorrow'))->setIsPublished(true);
        $this->em->persist($check);
        $this->em->flush();
        $checkId = $check->getId();
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['TÜV-Mehrfachanmeldung', 'Keine noch anmeldbaren eigenen Flaschen', 'Bitte 1 bis 30 eigene Flaschen auswählen.'],
            'en' => ['Multiple cylinder inspection registration', 'No eligible cylinders', 'Please select between 1 and 30 of your cylinders.'],
            'fr' => ['Inscription groupée au contrôle des bouteilles', 'Aucune de vos bouteilles inscrivables', 'Veuillez sélectionner entre 1 et 30 de vos bouteilles.'],
            'es' => ['Inscripción conjunta a la inspección de botellas', 'No hay botellas propias inscribibles', 'Seleccione entre 1 y 30 botellas propias.'],
        ];
        foreach ($expectations as $locale => [$heading, $empty, $error]) {
            $this->client->request('GET', '/portal/'.$locale);
            $crawler = $this->client->request('GET', '/my/equipment/checks/'.$checkId.'/multiple');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $heading);
            self::assertSelectorTextContains('section.section', $empty);
            $token = $crawler->filter('#tuv-multiple-form input[name="_token"]')->attr('value');
            $this->client->request('POST', '/my/equipment/checks/'.$checkId.'/multiple', ['_token' => $token]);
            self::assertResponseRedirects('/my/equipment/checks/'.$checkId.'/multiple', 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', $error);
        }
        $member = $this->em->find(Member::class, $user->getMember()->getId());
        $tank = (new MemberTank())->setMember($member)->setLabel('Fixture cylinder')->setSerialNumber('FIXTURE-SINGLE')
            ->setVolume('12')->setWaterCapacityLiters('12.00');
        $this->em->persist($tank);
        $this->em->flush();
        $tankId = $tank->getId();
        $single = [
            'de' => ['TÜV-Leistungen auswählen', 'Keine passenden Leistungen verfügbar.'],
            'en' => ['Select inspection services', 'No matching services available.'],
            'fr' => ['Sélectionner les prestations de contrôle', 'Aucune prestation correspondante disponible.'],
            'es' => ['Seleccionar servicios de inspección', 'No hay servicios correspondientes disponibles.'],
        ];
        foreach ($single as $locale => [$heading, $empty]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/my/equipment/checks/'.$checkId.'/tanks/'.$tankId.'/services');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $heading);
            self::assertSelectorTextContains('#tuv-booking-form', $empty);
            self::assertSelectorExists('#tuv-booking-form button[type="submit"]:disabled');
        }
    }

    public function testTrainingParticipantTranslationsAcrossRegistrationStates(): void
    {
        $user = $this->account('participant-translations');
        $course = (new Courses())->setTitle('Fixture participant course')->setCategory('beginner')->setClub($this->club);
        $participant = (new CourseParticipant())->setCourse($course)->setPerson($user->getPerson())
            ->setStatus(CourseParticipantStatus::WAITLISTED);
        $this->em->persist($course);
        $this->em->persist($participant);
        $this->em->flush();
        $participantId = $participant->getId();
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['Teilnahmebestätigung', 'Du stehst auf der Warteliste.', 'Noch keine Zahlungen gebucht.', 'Ausbildungsplan noch nicht hinterlegt.', 'Zur Erstattung offen'],
            'en' => ['Participation confirmation', 'You are on the waiting list.', 'No payments recorded yet.', 'No training plan has been added yet.', 'Refund outstanding'],
            'fr' => ['Confirmation de participation', 'Vous êtes sur la liste d’attente.', 'Aucun paiement enregistré pour le moment.', 'Aucun plan de formation n’a encore été défini.', 'Remboursement restant'],
            'es' => ['Confirmación de participación', 'Está en la lista de espera.', 'Aún no se han registrado pagos.', 'Aún no se ha establecido un plan de formación.', 'Reembolso pendiente'],
        ];
        foreach ($expectations as $locale => [$heading, $waiting, $payments, $plan]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/training/participant/'.$participantId);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $heading);
            self::assertSelectorTextContains('main', $waiting);
            self::assertSelectorTextContains('main', $payments);
            self::assertSelectorTextContains('main', $plan);
        }
        $participant = $this->em->find(CourseParticipant::class, $participantId);
        $participant->setStatus(CourseParticipantStatus::CANCELLED);
        $this->em->flush();
        foreach ($expectations as $locale => [$heading, $waiting, $payments, $plan, $refund]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/training/participant/'.$participantId);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', $refund);
            self::assertStringNotContainsString($waiting, $this->client->getCrawler()->filter('main')->text());
        }
    }

    public function testInspectionRegistrationSuccessMessagesInFourLanguages(): void
    {
        $user = $this->account('inspection-success-translations');
        $check = (new TankCheck())->setCheckDate(new \DateTime('tomorrow'))->setIsPublished(true);
        $article = (new TankCheckArticle())->setTitle('Fixture service')->setPriceNetto('10.00')->setSelectionMode('required');
        $check->addArticle($article);
        $this->em->persist($check);
        $this->em->persist($article);
        $tanks = [];
        for ($i = 0; $i < 8; ++$i) {
            $tank = (new MemberTank())->setMember($user->getMember())->setLabel('Fixture '.$i)->setSerialNumber('FIXTURE-SUCCESS-'.$i)
                ->setVolume('12')->setWaterCapacityLiters('12.00');
            $this->em->persist($tank);
            $tanks[] = $tank;
        }
        $this->em->flush();
        $checkId = $check->getId();
        $tankIds = array_map(static fn (MemberTank $tank) => $tank->getId(), $tanks);
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['TÜV-Anmeldung gespeichert:', '1 Flasche(n) gemeinsam zum TÜV angemeldet.'],
            'en' => ['Inspection registration saved:', '1 cylinder(s) registered together for inspection.'],
            'fr' => ['Inscription au contrôle enregistrée :', '1 bouteille(s) inscrite(s) ensemble au contrôle.'],
            'es' => ['Inscripción a la inspección guardada:', '1 botella(s) inscrita(s) conjuntamente a la inspección.'],
        ];
        foreach (array_keys($expectations) as $index => $locale) {
            $this->client->request('GET', '/portal/'.$locale);
            $singleId = $tankIds[$index * 2];
            $crawler = $this->client->request('GET', '/my/equipment/checks/'.$checkId.'/tanks/'.$singleId.'/services');
            self::assertResponseIsSuccessful();
            $token = $crawler->filter('#tuv-booking-form input[name="_token"]')->attr('value');
            $this->client->request('POST', '/my/equipment/checks/'.$checkId.'/book', ['_token' => $token, 'member_tank_id' => (string) $singleId]);
            self::assertResponseRedirects('/my/equipment', 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', $expectations[$locale][0]);

            $crawler = $this->client->request('GET', '/my/equipment/checks/'.$checkId.'/multiple');
            self::assertResponseIsSuccessful();
            $token = $crawler->filter('#tuv-multiple-form input[name="_token"]')->attr('value');
            $this->client->request('POST', '/my/equipment/checks/'.$checkId.'/multiple', ['_token' => $token, 'tanks' => [(string) $tankIds[$index * 2 + 1]]]);
            self::assertResponseRedirects('/my/equipment', 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', $expectations[$locale][1]);
        }
    }

    public function testReservationTranslationsForEmptyValidationAndSavedStates(): void
    {
        $user = $this->account('reservation-translations');
        $category = (new EquipmentCategory())->setClub($this->club)->setName('Fixture category')->setCode('fixture');
        $asset = (new EquipmentItem())->setClub($this->club)->setCategory($category)->setName('Fixture equipment')->setInventoryNumber('FIXTURE-RESERVE');
        $this->em->persist($category);
        $this->em->persist($asset);
        $this->em->flush();
        $assetId = $asset->getId();
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['Reservierungssystem', 'Es wurden noch keine Reservierungen vorgenommen.', 'Bitte Artikel und einen gültigen Zeitraum auswählen', 'erfolgreich gespeichert', 'Reserviert – Ausgabe ausstehend'],
            'en' => ['Reservation system', 'No reservations have been made yet.', 'Select items and a valid period', 'saved:', 'Reserved – awaiting issue'],
            'fr' => ['Système de réservation', 'Aucune réservation n’a encore été effectuée.', 'Sélectionnez des articles et une période valide', 'enregistrée :', 'Réservé – remise en attente'],
            'es' => ['Sistema de reservas', 'Aún no se han realizado reservas.', 'Seleccione artículos y un período válido', 'guardada:', 'Reservado – pendiente de entrega'],
        ];
        foreach ($expectations as $locale => [$heading, $empty, $error]) {
            $this->client->request('GET', '/portal/'.$locale);
            $crawler = $this->client->request('GET', '/my/reservations');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', $heading);
            self::assertSelectorTextContains('#meine-reservierungen', $empty);
            $token = $crawler->filter('#reservation-form input[name="_token"]')->attr('value');
            $this->client->request('POST', '/my/reservations', ['_token' => $token]);
            self::assertResponseRedirects('/my/reservations');
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', $error);
        }
        foreach (array_values($expectations) as $index => [$heading, $empty, $error, $success, $status]) {
            $locale = array_keys($expectations)[$index];
            $this->client->request('GET', '/portal/'.$locale);
            $crawler = $this->client->request('GET', '/my/reservations');
            $token = $crawler->filter('#reservation-form input[name="_token"]')->attr('value');
            $start = (new \DateTimeImmutable('today'))->modify('+'.($index * 2 + 1).' days');
            $this->client->request('POST', '/my/reservations', [
                '_token' => $token, 'items' => ['item:'.$assetId],
                'start' => $start->format('Y-m-d'), 'end' => $start->modify('+1 day')->format('Y-m-d'),
            ]);
            self::assertResponseRedirects();
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="status"]', $success);
            self::assertSelectorTextContains('#meine-reservierungen', $status);
            self::assertSelectorTextContains('#meine-reservierungen', 'Fixture equipment');
        }
    }

    public function testBarCashOverviewIsTranslatedWithAndWithoutAssignments(): void
    {
        $user = $this->account('bar-cash-translations');
        $memberId = $user->getMember()->getId();
        $clubId = $this->club->getId();
        $this->client->loginUser($user);
        $expectations = [
            'de' => ['Thekenkasse', 'Keine aktive eigene Diensteinteilung vorhanden.'],
            'en' => ['Bar cash register', 'You have no active duty assignment.'],
            'fr' => ['Caisse du bar', 'Vous n’avez aucune permanence active.'],
            'es' => ['Caja del bar', 'No tiene ningún turno activo asignado.'],
        ];
        foreach ($expectations as $locale => [$heading, $empty]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/bar-duty/cash');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h2', $heading);
            self::assertSelectorTextContains('main', $empty);
        }
        $club = $this->em->find(Club::class, $clubId);
        $member = $this->em->find(Member::class, $memberId);
        $definition = new BarShiftDefinition($club, 'Fixture duty', 'Clubhouse', '18:00', 180, 2);
        $shift = new BarShift($definition, new \DateTimeImmutable('tomorrow 18:00'));
        $assignment = new BarDutyAssignment($shift, $member, 'fixture');
        foreach ([$definition, $shift, $assignment] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        foreach ($expectations as $locale => [$heading, $empty]) {
            $this->client->request('GET', '/portal/'.$locale);
            $this->client->request('GET', '/bar-duty/cash');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h2', $heading);
            self::assertSelectorTextContains('main', 'Fixture duty');
            self::assertSelectorExists('main a[href="/bar-duty/'.$shift->getId().'#thekenkasse"]');
            self::assertStringNotContainsString($empty, $this->client->getCrawler()->filter('main')->text());
        }
    }

    public function testBackendBarCalendarIsTranslatedInAllLocales(): void
    {
        $user = $this->account('calendar-admin', [User::ROLE_SUPER_ADMIN]);
        $date = new \DateTimeImmutable('tomorrow 18:00');
        $definition = new BarShiftDefinition($this->club, 'Test shift', 'Clubhouse', '18:00', 180, 2);
        $shift = new BarShift($definition, $date);
        $assignment = new BarDutyAssignment($shift, $user->getMember(), 'fixture');
        foreach ([$definition, $shift, $assignment] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $this->client->loginUser($user);
        foreach ([
            'de' => ['Dienstkalender', 'Montag', '1 / 2 besetzt', '(du)', 'Noch keine Einteilungen.'],
            'en' => ['Duty calendar', 'Monday', '1 / 2 filled', '(you)', 'No notifications.'],
            'fr' => ['Calendrier des permanences', 'Lundi', '1 / 2 occupées', '(vous)', 'Aucune notification.'],
            'es' => ['Calendario de turnos', 'Lunes', '1 / 2 ocupadas', '(usted)', 'No hay notificaciones.'],
        ] as $locale => [$heading, $monday, $capacity, $you, $empty]) {
            $this->client->request('GET', '/admin/'.$locale);
            self::assertResponseIsSuccessful();
            $this->client->request('GET', '/admin/bar-duty?month='.$date->format('Y-m'));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('[data-bar-duty-calendar]', $heading);
            self::assertSelectorTextContains('[data-bar-duty-calendar] th', $monday);
            self::assertSelectorTextContains('[data-bar-event]', $capacity);
            self::assertSelectorTextContains('[data-bar-event]', $you);
            if ($locale !== 'de') {
                self::assertSelectorTextContains('body', $empty);
            }
        }
    }

    public function testFrontendManagementIsForbiddenAndBackendPlanningKeepsItsOwnRoutes(): void
    {
        $manager = $this->account('clubhouse', [User::ROLE_BAR_MANAGER]);
        $this->client->loginUser($manager);
        foreach (['/bar-duty/plan', '/bar-duty/settings', '/bar-duty/report'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        }
        foreach (['/admin/bar-duty', '/admin/bar-duty/plan', '/admin/bar-duty/settings', '/admin/bar-duty/report', '/admin/calendar'] as $url) {
            $this->client->request('GET', $url); self::assertResponseIsSuccessful();
            self::assertSelectorExists('aside.sidebar');
            $this->assertNoAreaCrossing(true);
        }
    }

    public function testAssignedMemberCanOperateOnlyTheirOwnCashAndClosingIsImmutable(): void
    {
        $user = $this->account('cashier');
        $stranger = $this->account('stranger');
        $definition = new BarShiftDefinition($this->club, 'Own service', 'Clubhouse', '18:00', 180, 2);
        $shift = new BarShift($definition, new \DateTimeImmutable('tomorrow 18:00'));
        $foreignShift = new BarShift($definition, new \DateTimeImmutable('+2 days 18:00'));
        $assignment = new BarDutyAssignment($shift, $user->getMember(), 'Synthetic assignment');
        $policy = new BarDutyPolicy($this->club, (int) $shift->getStartsAt()->format('Y'));
        $policy->configure(0, true, 'fixture', 'Synthetic policy configuration');
        foreach ([$definition, $shift, $foreignShift, $assignment, $policy] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/bar-duty/'.$shift->getId());
        self::assertSelectorExists('#thekenkasse');
        $this->postCash('cash-open', $shift->getId(), ['amount' => '10', 'reason' => 'Initial float counted in person']);
        self::assertResponseRedirects('/bar-duty/'.$shift->getId(), 303);
        $session = $this->em->getRepository(BarCashSession::class)->findOneBy([]);
        self::assertNotNull($session);
        $this->postCash('cash-book', $session->getId(), ['direction' => 'in', 'amount' => '2.50', 'reason' => 'Drinks paid during own shift', 'reference' => 'OWN-1']);
        self::assertResponseRedirects('/bar-duty', 303);
        $income = $this->em->getRepository(BarCashEntry::class)->findOneBy([]);
        self::assertSame(250, $income->getAmountCents());
        $this->postCash('cash-reverse', $session->getId(), ['entry' => $income->getId(), 'reason' => 'Correction of own shift cash receipt']);
        self::assertSame(2, $this->em->getRepository(BarCashEntry::class)->count([]));
        $this->client->request('GET', '/bar-duty/cash/'.$session->getId().'.csv');
        self::assertResponseIsSuccessful();
        $this->postCash('cash-close', $session->getId(), ['amount' => '10.00', 'reason' => 'Counted drawer after own service']);
        self::assertNotNull($this->em->find(BarCashSession::class, $session->getId())->getClosedAt());
        $this->postCash('cash-book', $session->getId(), ['direction' => 'in', 'amount' => '1', 'reason' => 'Must not change closed cash', 'reference' => 'OWN-2']);
        self::assertSame(2, $this->em->getRepository(BarCashEntry::class)->count([]));
        $this->postCash('cash-open', $foreignShift->getId(), ['amount' => '0', 'reason' => 'Must not open an unassigned shift']);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($stranger);
        $this->client->request('GET', '/bar-duty/'.$shift->getId());
        self::assertSelectorNotExists('#thekenkasse');
        $this->client->request('GET', '/bar-duty/cash/'.$session->getId().'.csv');
        self::assertResponseStatusCodeSame(403);
        $this->postCash('cash-book', $session->getId(), ['direction' => 'in', 'amount' => '1']);
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('cashAccessRevocations')]
    public function testCashAccessRequiresCurrentActiveAssignmentAndClub(string $case): void
    {
        $user = $this->account('cashier', $case === 'unassigned-manager' ? [User::ROLE_BAR_MANAGER] : []);
        $definition = new BarShiftDefinition($this->club, 'Restricted service', 'Clubhouse', '18:00', 180, 2);
        $shift = new BarShift($definition, new \DateTimeImmutable('tomorrow 18:00'));
        $assignment = new BarDutyAssignment($shift, $user->getMember(), 'fixture');
        if ($case === 'cancelled') { $assignment->cancel('fixture', 'Withdrawn before the planned service'); }
        if ($case === 'absent') { $assignment->confirm('absent', 0, 'Confirmed absence from service', 'fixture'); }
        if ($case === 'former') { $user->getMember()->setMembershipStatus(MembershipStatus::FORMER); }
        if ($case === 'foreign-club') {
            $other = (new Club())->setName('Other club');
            $this->em->persist($other);
            $user->getMember()->setClub($other);
        }
        foreach ([$definition, $shift] as $entity) { $this->em->persist($entity); }
        if ($case !== 'unassigned-manager') { $this->em->persist($assignment); }
        $this->em->flush();
        $this->client->loginUser($user);
        $this->postCash('cash-open', $shift->getId(), ['amount' => '0', 'reason' => 'Must not open unauthorized cash']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em->getRepository(BarCashSession::class)->count([]));
    }

    public static function cashAccessRevocations(): iterable
    {
        foreach (['cancelled', 'absent', 'former', 'foreign-club', 'unassigned-manager'] as $case) { yield $case => [$case]; }
    }

    public function testInstructorSeesOnlyOwnClubAndAssignedCoursesWithFrontendAssessment(): void
    {
        $instructor = $this->account('instructor', [User::ROLE_INSTRUCTOR]);
        $other = $this->account('other-instructor', [User::ROLE_INSTRUCTOR]);
        $foreignClub = (new Club())->setName('Foreign training club');
        $this->em->persist($foreignClub);
        $courses = [];
        foreach (['Own lead course', 'Own session course', 'Other instructor course', 'Foreign club course'] as $title) {
            $course = (new \App\Entity\Courses())->setTitle($title)->setCategory('beginner')
                ->setClub($title === 'Foreign club course' ? $foreignClub : $this->club)
                ->setLeadInstructor($title === 'Other instructor course' ? $other->getMember() : $instructor->getMember());
            if ($title === 'Own session course') { $course->setLeadInstructor($other->getMember()); }
            $this->em->persist($course); $courses[$title] = $course;
        }
        $session = (new \App\Entity\Schedule())->setTitle('Assigned training session')
            ->setInstructor($instructor->getMember())->setCourses($courses['Own session course']);
        $this->em->persist($session); $this->em->flush();
        $this->client->loginUser($instructor);
        $this->client->request('GET', '/instructor-portal/courses');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Own lead course');
        self::assertSelectorTextContains('main', 'Own session course');
        self::assertSelectorTextNotContains('main', 'Other instructor course');
        self::assertSelectorTextNotContains('main', 'Foreign club course');
        $this->assertNoAreaCrossing(false);
        $this->client->request('GET', '/my-training/assess/'.$session->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#frontendNavbar');
        $this->assertNoAreaCrossing(false);
        $this->client->request('GET', '/admin/training/assess/'.$session->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorNotExists('#frontendNavbar');
        $this->assertNoAreaCrossing(true);
    }

    public function testBookingsOverviewContainsOnlyOwnBookings(): void
    {
        $user = $this->account('own-booking');
        $other = $this->account('foreign-booking');
        $check = (new \App\Entity\TankCheck())->setCheckDate(new \DateTime('tomorrow'));
        $this->em->persist($check);
        $bookings = [];
        foreach ([$user, $other] as $account) {
            $booking = (new \App\Entity\MemberTankCheckBooking())->setMember($account->getMember())->setTankCheck($check);
            $this->em->persist($booking); $bookings[] = $booking;
        }
        $this->em->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/my/bookings');
        self::assertResponseIsSuccessful();
        $router = static::getContainer()->get('router');
        self::assertSelectorExists('main a[href="'.$router->generate('member_tank_check_booking_detail', ['id' => $bookings[0]->getId()]).'"]');
        self::assertSelectorNotExists('main a[href="'.$router->generate('member_tank_check_booking_detail', ['id' => $bookings[1]->getId()]).'"]');
        $this->assertNoAreaCrossing(false);
    }

    private function postCash(string $operation, int $id, array $data): void
    {
        $this->client->request('GET', '/bar-duty');
        $stack = static::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = static::getContainer()->get('security.csrf.token_manager')->getToken('bar_duty_'.$operation.'_'.$id)->getValue();
            $this->client->getRequest()->getSession()->save();
        } finally { $stack->pop(); }
        $this->client->request('POST', '/bar-duty/actions/'.$operation.'/'.$id, ['_token' => $token] + $data);
    }

    private function assertNoAreaCrossing(bool $backend): void
    {
        $crawler = $this->client->getCrawler();
        foreach ($crawler->filter('a[href], form[action]')->each(static fn ($node) => $node->attr($node->nodeName() === 'a' ? 'href' : 'action')) as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            if (!is_string($path) || !str_starts_with($path, '/') || $path === '/logout' || str_starts_with($path, '/_')) { continue; }
            if ($backend) { self::assertStringStartsWith('/admin', $path, 'Backend must not link to '.$url); }
            else { self::assertFalse(str_starts_with($path, '/admin'), 'Frontend must not link to '.$url); }
        }
    }

    private function account(string $name, array $roles = []): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Synthetic')->setEmail($name.'@example.invalid');
        $member = (new Member())->setPerson($person)->setClub($this->club)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setIsVerified(true)->setMember($member)->setRoles($roles);
        $this->em->persist($user); $this->em->flush();
        return $user;
    }
}
