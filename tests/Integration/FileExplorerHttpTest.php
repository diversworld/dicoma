<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use App\Enum\MembershipStatus;
use App\Service\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FileExplorerHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private string $root;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'PUBLIC_SITE_CLUB_ID' => '1'] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $this->root = sys_get_temp_dir().'/dicoma-explorer-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        mkdir($this->root.'/0', 0700);
        mkdir($this->root.'/0/empty', 0700);
        file_put_contents($this->root.'/0/ä <test>.txt', 'course image test');
        file_put_contents($this->root.'/0/back\\slash.txt', 'backslash name');
        file_put_contents($this->root.'/root.txt', 'root file');
        symlink(sys_get_temp_dir(), $this->root.'/outside');
        symlink($this->root.'/0', $this->root.'/inside');
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        static::getContainer()->set(FileManager::class, new FileManager($this->root));
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Explorer test');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            foreach ([$this->root.'/inside', $this->root.'/outside', $this->root.'/0/ä <test>.txt', $this->root.'/0/back\\slash.txt', $this->root.'/root.txt'] as $path) {
                if (is_link($path) || is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($this->root.'/0/empty')) { rmdir($this->root.'/0/empty'); }
            if (is_dir($this->root.'/0')) { rmdir($this->root.'/0'); }
            if (is_dir($this->root)) { rmdir($this->root); }
        }
        parent::tearDown();
    }

    public function testBrowsingDownloadingAndPathBoundaries(): void
    {
        $admin = $this->account('explorer-admin', [User::ROLE_ADMIN]);
        $press = $this->account('explorer-press', [User::ROLE_PRESS_OFFICER]);
        $member = $this->account('explorer-member', []);
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/files');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorTextContains('h1', 'Dateiverwaltung');
        self::assertSelectorTextContains('body', 'Kursbilder können hier nur angesehen und heruntergeladen werden.');
        self::assertSelectorExists('form[action="/admin/media/upload"]');
        self::assertSelectorTextContains('table', 'root.txt');
        self::assertSelectorTextNotContains('table', 'outside');
        self::assertSelectorTextNotContains('table', 'inside');
        self::assertSelectorExists('a[href*="legacy_path=0"]');
        self::assertSelectorTextContains('#legacy-folder-tree', 'root.txt');
        self::assertSelectorTextNotContains('#legacy-folder-tree', 'outside');
        $this->client->request('GET', '/admin/media', ['legacy_path' => '0']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#legacy-folder-tree details[data-legacy-path="0"][open] > summary a[aria-current="page"]');
        self::assertSelectorTextContains('#legacy-folder-tree details[data-legacy-path="0"]', 'ä <test>.txt');
        self::assertSelectorTextContains('#legacy-folder-tree details[data-legacy-path="0"]', 'empty');
        self::assertSelectorTextContains('table', 'ä <test>.txt');
        self::assertSelectorNotExists('table test');
        self::assertSelectorTextContains('.breadcrumb', '0');
        $this->client->request('GET', '/admin/media', ['legacy_path' => '0/empty']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Dieser Ordner ist leer.');
        $this->client->request('GET', '/admin/files/download', ['path' => '0/back\\slash.txt']);
        self::assertResponseIsSuccessful();
        self::assertSame('backslash name', file_get_contents($this->client->getResponse()->getFile()->getPathname()));
        $this->client->request('GET', '/admin/files/download', ['path' => '0/ä <test>.txt']);
        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('course image test', file_get_contents($response->getFile()->getPathname()));
        self::assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach (['../', '0/../root.txt', 'outside', 'inside/ä <test>.txt'] as $path) {
            $this->client->request('GET', '/admin/files/download', ['path' => $path]);
            self::assertResponseStatusCodeSame(400);
        }
        $this->client->request('GET', '/admin/media', ['legacy_path' => 'does-not-exist']);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/media', ['legacy_path' => '../']);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/admin/files');
        self::assertResponseStatusCodeSame(405);
        $this->client->loginUser($press);
        $this->client->request('GET', '/admin/media');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#legacy-course-files');
        $this->client->request('GET', '/admin/media', ['legacy_path' => '']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/files/download', ['path' => 'root.txt']);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($member);
        $this->client->request('GET', '/admin/files');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/files/download', ['path' => 'root.txt']);
        self::assertResponseStatusCodeSame(403);
    }

    /** @param list<string> $roles */
    private function account(string $name, array $roles): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Test')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($this->club)->setPerson($person)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setIsVerified(true)->setMember($member)->setRoles($roles);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
}
