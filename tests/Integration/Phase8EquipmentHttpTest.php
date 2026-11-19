<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\QueueEquipmentInspectionRemindersCommand;
use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\EquipmentAssetRecord;
use App\Entity\EquipmentCategory;
use App\Entity\EquipmentItem;
use App\Entity\EquipmentLocation;
use App\Entity\Member;
use App\Entity\MembershipMessage;
use App\Entity\Person;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase8EquipmentHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private User $manager;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
            'CLUB_FUNCTION_MEMBERSHIP_ENABLED' => '1',
        ] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Geräteverein');
        $this->em->persist($this->club);
        $person = (new Person())->setFirstname('Geräte')->setLastname('Wart');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $this->manager = (new User())->setUser('manager')->setEmail('manager@example.invalid')->setPassword('unused')->setMember($member);
        $function = new ClubFunction($this->club, 'Gerätewart');
        $function->setPermissions([User::ROLE_EQUIPMENT_MANAGER]);
        $this->em->persist($this->manager);
        $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $this->manager));
        $this->em->flush();
    }

    public function testManagedLocationAndQrAreClubProtected(): void
    {
        $category = (new EquipmentCategory())->setClub($this->club)->setName('Tauchausrüstung')->setCode('dive');
        $asset = (new EquipmentItem())->setClub($this->club)->setCategory($category)
            ->setInventoryNumber('EQ-001')->setName('Tarierjacket');
        $location = (new EquipmentLocation($this->club))->setName('Geräteraum A');
        $record = new EquipmentAssetRecord($asset);
        $record->setStructuredLocation($location);
        foreach ([$category, $asset, $location, $record] as $entity) { $this->em->persist($entity); }
        $this->em->flush();

        $this->client->loginUser($this->manager);
        $this->client->request('GET', '/equipment-manager/inventory/equipment/'.$asset->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="form[structuredLocation]"] option[value="'.$location->getId().'"]');
        self::assertSelectorExists('a[href="/equipment-manager/inventory/equipment/'.$asset->getId().'/qr"]');
        $this->client->request('GET', '/equipment-manager/inventory/equipment/'.$asset->getId().'/qr');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<svg', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('image/svg+xml', (string) $this->client->getResponse()->headers->get('Content-Type'));

        $foreign = (new Club())->setName('Fremdverein');
        $foreignLocation = new EquipmentLocation($foreign);
        $foreignLocation->setName('Fremd');
        $this->em->persist($foreign);
        $this->em->persist($foreignLocation);
        $this->em->flush();
        try {
            $record->setStructuredLocation($foreignLocation);
            self::fail('Foreign location accepted.');
        } catch (\DomainException) {
            self::assertSame('Geräteraum A', $record->getLocationLabel());
        }
    }

    public function testDashboardIncludesNonTankAssetsAndRemindersAreIdempotent(): void
    {
        $category = (new EquipmentCategory())->setClub($this->club)->setName('Instrumente')->setCode('instrument');
        $asset = (new EquipmentItem())->setClub($this->club)->setCategory($category)
            ->setInventoryNumber('EQ-REM')->setName('Tiefenmesser');
        $record = new EquipmentAssetRecord($asset);
        $record->setNextInspectionAt(new \DateTimeImmutable('+10 days'));
        foreach ([$category, $asset, $record] as $entity) { $this->em->persist($entity); }
        $this->em->flush();

        $this->client->loginUser($this->manager);
        $this->client->request('GET', '/equipment-manager');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tiefenmesser');
        self::assertSelectorTextContains('body', 'Inventargegenstände gesamt');

        $command = new CommandTester(static::getContainer()->get(QueueEquipmentInspectionRemindersCommand::class));
        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertStringContainsString('1 Prüfhinweise vorbereitet', $command->getDisplay());
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count([]));
        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count([]));
    }

    public function testForeignClubQrLookupIsDenied(): void
    {
        $other = (new Club())->setName('Anderer Verein');
        $category = (new EquipmentCategory())->setClub($other)->setName('Fremd')->setCode('foreign');
        $asset = (new EquipmentItem())->setClub($other)->setCategory($category)
            ->setInventoryNumber('F-1')->setName('Geheimgerät');
        $this->em->persist($other);
        $this->em->persist($category);
        $this->em->persist($asset);
        $this->em->flush();
        $this->client->loginUser($this->manager);
        $this->client->request('GET', '/equipment-manager/inventory/equipment/'.$asset->getId().'/qr');
        self::assertResponseStatusCodeSame(404);
    }
}
