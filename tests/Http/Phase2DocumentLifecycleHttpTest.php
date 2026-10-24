<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\MemberTankCheckBooking;
use App\Entity\TankCheck;
use App\Entity\TuvDocument;
use App\Entity\User;
use App\Service\PrivateMemberDocumentStorage;
use App\Service\TuvDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class Phase2DocumentLifecycleHttpTest extends WebTestCase
{
    public function testMemberUploadDownloadAndDeletionRespectOwnershipAndCsrf(): void
    {
        [$client, $em, $users] = $this->context();
        $owner = $users['a']->getMember();
        self::assertNotNull($owner);
        $storage = static::getContainer()->get(PrivateMemberDocumentStorage::class);
        $originalName = 'phase2-document-' . bin2hex(random_bytes(8)) . '.pdf';
        $temporary = $this->pdf();
        $connection = $em->getConnection();

        try {
            $client->loginUser($users['a']);
            $before = (int) $connection->fetchOne('SELECT COUNT(*) FROM member_document');
            $client->request('POST', '/profile/documents', ['_token' => 'invalid'], [
                'document' => new UploadedFile($temporary, $originalName, 'application/pdf', null, true),
            ]);
            self::assertResponseStatusCodeSame(403);
            self::assertSame($before, (int) $connection->fetchOne('SELECT COUNT(*) FROM member_document'));

            $crawler = $client->request('GET', '/profile');
            self::assertResponseIsSuccessful();
            $forms = $crawler->filter('form[action="/profile/documents"]');
            self::assertCount(1, $forms);
            $values = $forms->form()->getPhpValues();
            self::assertNotEmpty($values['_token']);
            $client->request('POST', '/profile/documents', $values, [
                'document' => new UploadedFile($temporary, $originalName, 'application/pdf', null, true),
            ]);
            self::assertResponseRedirects('/profile');

            $row = $connection->fetchAssociative('SELECT * FROM member_document WHERE original_name = ?', [$originalName]);
            self::assertIsArray($row);
            self::assertSame($owner->getId(), (int) $row['member_id']);
            $id = (int) $row['id'];
            $path = $storage->path($row['storage_name']);
            self::assertFileExists($path);
            $checksum = hash_file('sha256', $path);

            $client->request('GET', '/profile/documents/' . $id . '/download');
            $this->assertPrivateDownload($checksum);
            $client->request('GET', '/admin/member/' . $owner->getId() . '/document/' . $id . '/download');
            $this->assertPrivateDownload($checksum);

            $client->loginUser($users['b']);
            $client->request('GET', '/profile/documents/' . $id . '/download');
            self::assertResponseStatusCodeSame(404);
            $client->request('GET', '/admin/member/' . $owner->getId() . '/document/' . $id . '/download');
            self::assertResponseStatusCodeSame(403);
            $client->request('POST', '/profile/documents/' . $id . '/delete', ['_token' => 'invalid']);
            self::assertResponseStatusCodeSame(404);
            self::assertFileExists($path);

            $client->loginUser($users['a']);
            $client->request('POST', '/profile/documents/' . $id . '/delete', ['_token' => 'invalid']);
            self::assertResponseStatusCodeSame(403);
            self::assertFileExists($path);
            $crawler = $client->request('GET', '/profile');
            self::assertResponseIsSuccessful();
            $forms = $crawler->filter('form[action="/profile/documents/' . $id . '/delete"]');
            self::assertCount(1, $forms);
            $client->submit($forms->form());
            self::assertResponseRedirects('/profile');
            self::assertFalse($connection->fetchOne('SELECT id FROM member_document WHERE id = ?', [$id]));
            self::assertFileDoesNotExist($path);
        } finally {
            foreach ($connection->fetchAllAssociative('SELECT * FROM member_document WHERE original_name = ? AND member_id = ?', [$originalName, $owner->getId()]) as $row) {
                $connection->delete('member_document', ['id' => $row['id']]);
                $path = $storage->path($row['storage_name']);
                if (is_file($path)) {
                    self::assertTrue(unlink($path));
                }
            }
            if (is_file($temporary)) {
                self::assertTrue(unlink($temporary));
            }
        }
    }

    public function testTuvDocumentsArePrivateToOwnerAndSuperAdmin(): void
    {
        [$client, $em, $users] = $this->context();
        $owner = $users['a']->getMember();
        self::assertNotNull($owner);
        $storage = static::getContainer()->get(TuvDocumentStorage::class);
        $temporary = $this->pdf();
        $check = (new TankCheck())->setCheckDate(new \DateTime('2026-10-24'));
        $booking = (new MemberTankCheckBooking())->setMember($owner)->setTankCheck($check);
        $document = null;
        $path = null;
        $connection = $em->getConnection();

        try {
            [$name, $original, $mime, $size, $checksum] = $storage->store(
                new UploadedFile($temporary, 'phase2-tuv-test.pdf', 'application/pdf', null, true),
            );
            $path = $storage->path($name);
            $document = new TuvDocument($booking, $name, $original, $mime, $size, $checksum, 'phase2-test');
            $em->persist($check);
            $em->persist($booking);
            $em->persist($document);
            $em->flush();
            $id = $document->getId();
            self::assertNotNull($id);

            $client->request('GET', '/my/equipment/tuv/document/' . $id);
            self::assertResponseRedirects('/login');
            $client->loginUser($users['a']);
            $client->request('GET', '/my/equipment/tuv/document/' . $id);
            $this->assertPrivateDownload($checksum);
            $client->request('GET', '/admin/tuv/document/' . $id);
            self::assertResponseStatusCodeSame(403);
            $client->loginUser($users['b']);
            $client->request('GET', '/my/equipment/tuv/document/' . $id);
            self::assertResponseStatusCodeSame(404);
            $client->loginUser($users['super']);
            $client->request('GET', '/admin/tuv/document/' . $id);
            self::assertResponseIsSuccessful();
            self::assertInstanceOf(BinaryFileResponse::class, $client->getResponse());
            self::assertSame($checksum, hash_file('sha256', $client->getResponse()->getFile()->getPathname()));
        } finally {
            if ($document?->getId() !== null) {
                $connection->delete('tuv_document', ['id' => $document->getId()]);
            }
            if ($booking->getId() !== null) {
                $connection->delete('member_tank_check_booking', ['id' => $booking->getId()]);
            }
            if ($check->getId() !== null) {
                $connection->delete('tank_check', ['id' => $check->getId()]);
            }
            foreach ([$path, $temporary] as $file) {
                if ($file !== null && is_file($file)) {
                    self::assertTrue(unlink($file));
                }
            }
        }
    }

    private function assertPrivateDownload(string $checksum): void
    {
        self::assertResponseIsSuccessful();
        $response = static::getClient()->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame($checksum, hash_file('sha256', $response->getFile()->getPathname()));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
    }

    private function pdf(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dicoma-document-test-');
        self::assertIsString($path);
        self::assertNotFalse(file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"));
        return $path;
    }

    private function context(): array
    {
        if (getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1'
            || getenv('DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED') !== '1') {
            self::markTestSkipped('Explicit isolated database and write-test confirmations required.');
        }
        self::assertSame('db_test', getenv('DICOMA_TEST_EXPECTED_DATABASE'));
        $client = static::createClient(['environment' => 'test']);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        self::assertSame('db_test', $connection->getParams()['host'] ?? null);
        self::assertSame('db_test', $connection->fetchOne('SELECT DATABASE()'));
        $users = [];
        foreach (['a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL', 'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL', 'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL'] as $key => $variable) {
            $email = getenv($variable);
            self::assertIsString($email);
            self::assertStringEndsWith('@example.invalid', $email);
            $users[$key] = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            self::assertInstanceOf(User::class, $users[$key]);
        }
        self::assertNotNull($users['a']->getMember()?->getClub()?->getId());
        self::assertNotNull($users['b']->getMember()?->getClub()?->getId());
        self::assertNotSame($users['a']->getMember()->getClub()->getId(), $users['b']->getMember()->getClub()->getId());
        foreach (['a', 'b'] as $key) {
            self::assertContains('ROLE_MEMBER', $users[$key]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$key]->getRoles());
        }
        self::assertContains('ROLE_SUPER_ADMIN', $users['super']->getRoles());
        return [$client, $em, $users];
    }
}
