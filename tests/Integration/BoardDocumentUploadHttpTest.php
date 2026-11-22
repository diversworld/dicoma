<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\ClubDocument;
use App\Entity\ClubDocumentVersion;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use App\Service\ClubDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BoardDocumentUploadHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private ClubDocumentStorage $storage;
    private User $board;
    private array $storedNames = [];

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
        $this->storage = static::getContainer()->get(ClubDocumentStorage::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $club = (new Club())->setName('Dokumenttest');
        $person = (new Person())->setFirstname('Board')->setLastname('Member')->setEmail('board@example.invalid');
        $member = (new Member())->setClub($club)->setPerson($person);
        $this->board = (new User())->setUser('board-documents')->setEmail('board@example.invalid')
            ->setPassword('unused')->setRoles([User::ROLE_BOARD])->setMember($member);
        foreach ([$club, $person, $member, $this->board] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->storedNames as $name) {
            $path = $this->storage->path($name);
            if (is_file($path)) { @unlink($path); }
        }
        parent::tearDown();
    }

    public function testNewDocumentAndSecondVersionUploadAreSuccessful(): void
    {
        $this->client->loginUser($this->board);
        $crawler = $this->client->request('GET', '/admin/documents');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[enctype="multipart/form-data"] input[name="_token"]')->attr('value');

        $this->upload($token, 'Vereinsordnung', 'other', $this->pdf('first', 'ordnung.pdf'), 'ordnung.pdf');
        self::assertResponseRedirects('/admin/documents');
        $document = $this->em->getRepository(ClubDocument::class)->findOneBy(['title' => 'Vereinsordnung']);
        self::assertInstanceOf(ClubDocument::class, $document);
        $first = $this->em->getRepository(ClubDocumentVersion::class)->findOneBy(['document' => $document, 'version' => 1]);
        self::assertInstanceOf(ClubDocumentVersion::class, $first);
        self::assertSame('ordnung.pdf', $first->getOriginalName());
        $this->storedNames[] = $first->getStorageName();

        $crawler = $this->client->request('GET', '/admin/documents');
        $token = $crawler->filter('form[enctype="multipart/form-data"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/documents', [
            '_token' => $token,
            'documentId' => (string) $document->getId(),
            'title' => '',
            'category' => 'other',
        ], [
            'document' => $this->pdf('second', 'ordnung-v2.pdf'),
        ]);
        self::assertResponseRedirects('/admin/documents');
        $this->em->clear();
        $document = $this->em->getRepository(ClubDocument::class)->find($document->getId());
        $second = $this->em->getRepository(ClubDocumentVersion::class)->findOneBy(['document' => $document, 'version' => 2]);
        self::assertInstanceOf(ClubDocumentVersion::class, $second);
        self::assertCount(2, $document->getVersions());
        $this->storedNames[] = $second->getStorageName();
    }

    public function testInvalidNewDocumentTitleReturnsHelpfulErrorAndDoesNotPersistFile(): void
    {
        $this->client->loginUser($this->board);
        $crawler = $this->client->request('GET', '/admin/documents');
        $token = $crawler->filter('form[enctype="multipart/form-data"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/documents', [
            '_token' => $token,
            'documentId' => '0',
            'title' => '',
            'category' => 'other',
        ], [
            'document' => $this->pdf('invalid', 'invalid.pdf'),
        ]);
        self::assertResponseRedirects('/admin/documents');
        self::assertSame(0, $this->em->getRepository(ClubDocument::class)->count([]));
    }

    private function upload(string $token, string $title, string $category, UploadedFile $file, string $expected): void
    {
        self::assertSame($expected, $file->getClientOriginalName());
        $this->client->request('POST', '/admin/documents', [
            '_token' => $token,
            'documentId' => '0',
            'title' => $title,
            'category' => $category,
        ], ['document' => $file]);
    }

    private function pdf(string $content, string $name = 'document.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'board-pdf-');
        file_put_contents($path, "%PDF-1.4\n% ".$content."\n%%EOF");
        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
