<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunTestsInSeparateProcesses};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ContactFormHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'PUBLIC_SITE_CLUB_ID' => '1',
            'MAILER_DSN' => $this->name() === 'testNullTransportDisablesTheForm' ? 'null://null' : 'smtp://localhost',
            'CONTACT_MAIL_FROM' => 'website@example.invalid',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Contact club')->setEmail('club@example.invalid');
        $em->persist($this->club);
        $em->flush();
    }

    public function testValidSubmissionAndRateLimitRejectInvalidOrExcessRequests(): void
    {
        $this->setClientIp('198.51.100.17');
        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(4))->method('send')->willReturnCallback(static function (Email $email) use (&$sent): void {
            $sent[] = $email;
        });
        static::getContainer()->set(MailerInterface::class, $mailer);

        $this->client->request('POST', '/contact', ['contact_message' => ['name' => 'Visitor', 'email' => 'visitor@example.invalid', 'message' => 'Hello']]);
        self::assertResponseStatusCodeSame(422);
        for ($i = 0; $i < 4; ++$i) {
            $this->client->request('GET', '/contact');
            $this->client->submitForm('Nachricht senden', [
                'contact_message[name]' => 'Visitor',
                'contact_message[email]' => 'visitor@example.invalid',
                'contact_message[message]' => 'Hello club',
            ]);
            self::assertResponseRedirects('/contact', 303);
        }
        self::assertCount(4, $sent);
        self::assertSame('website@example.invalid', $sent[0]->getFrom()[0]->getAddress());
        self::assertSame('club@example.invalid', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('visitor@example.invalid', $sent[0]->getReplyTo()[0]->getAddress());
        self::assertStringContainsString('Hello club', $sent[0]->getTextBody());
        $this->client->request('GET', '/contact');
        self::assertSelectorTextContains('[role="status"]', 'Mailserver');
        $this->client->submitForm('Nachricht senden', [
            'contact_message[name]' => 'Visitor',
            'contact_message[email]' => 'visitor@example.invalid',
            'contact_message[message]' => 'Excess',
        ]);
        self::assertResponseStatusCodeSame(429);
    }

    public function testTransportFailureIsReportedAndNotReportedAsSuccess(): void
    {
        $this->setClientIp('198.51.100.18');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new TransportException('Synthetic transport failure'));
        static::getContainer()->set(MailerInterface::class, $mailer);
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Nachricht senden', [
            'contact_message[name]' => 'Visitor',
            'contact_message[email]' => 'visitor@example.invalid',
            'contact_message[message]' => 'Hello',
        ]);
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('body', 'Die Nachricht konnte nicht gesendet werden.');
        self::assertSelectorNotExists('[role="status"]');
    }

    public function testMissingRecipientDisablesTheFormAndRejectsPosts(): void
    {
        $this->club->setEmail(null);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->client->request('GET', '/contact');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Kontaktformular ist derzeit nicht konfiguriert');
        self::assertSelectorNotExists('form[name="contact_message"]');
        $this->client->request('POST', '/contact');
        self::assertResponseStatusCodeSame(503);
    }

    public function testNullTransportDisablesTheForm(): void
    {
        $this->client->request('GET', '/contact');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="contact_message"]');
        $this->client->request('POST', '/contact');
        self::assertResponseStatusCodeSame(503);
    }

    public function testInvalidEmailAndInactiveClubNeverSendMessages(): void
    {
        $this->setClientIp('198.51.100.19');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        static::getContainer()->set(MailerInterface::class, $mailer);
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Nachricht senden', [
            'contact_message[name]' => 'Visitor',
            'contact_message[email]' => 'not-an-email',
            'contact_message[message]' => 'Hello',
        ]);
        self::assertResponseStatusCodeSame(422);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(Club::class, $this->club->getId())->setActive(false);
        $em->flush();
        $this->client->request('POST', '/contact');
        self::assertResponseStatusCodeSame(404);
    }

    private function setClientIp(string $ip): void
    {
        $this->client->setServerParameter('REMOTE_ADDR', $ip);
        $container = static::getContainer();
        $container->get('limiter.contact_form')
            ->create(hash_hmac('sha256', $ip, $container->getParameter('kernel.secret')))->reset();
    }
}
