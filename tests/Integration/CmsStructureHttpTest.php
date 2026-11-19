<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\Sport;
use App\Entity\User;
use App\Entity\WebsitePublication;
use App\Service\PublicWebsite;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CmsStructureHttpTest extends KernelTestCase
{
    public function testPageTreeAndArticleDivisionInheritanceAreResolved(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
        ] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }
        self::bootKernel(['environment' => 'test']);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $club = (new Club())->setName('CMS Verein');
        $division = (new Sport())->setClub($club)->setName('Tauchen');
        $page = $this->page($club, 'Tauchen im Verein');
        $page->setDivision($division);
        $child = $this->page($club, 'Ausbildung');
        $child->setParent($page);
        $article = $this->page($club, 'Neue Saison');
        $article->setKind('article');
        $article->setParent($page);
        foreach ([$club, $division, $page, $child, $article] as $entity) { $em->persist($entity); }
        $em->flush();

        self::assertSame($page->getDivision(), $article->getEffectiveDivision());
        self::assertSame($page->getDivision(), $em->find(WebsitePublication::class, $article->getId())->getEffectiveDivision());
        $website = static::getContainer()->get(PublicWebsite::class);
        self::assertSame([], $website->articlesForPage($child));
        self::assertContains($article, $website->articlesForPage($page));
    }

    private function page(Club $club, string $title): WebsitePublication
    {
        $page = new WebsitePublication($club);
        $page->setKind('page');
        $page->setTitle($title);
        $page->setSummary('Zusammenfassung');
        $page->setBody('Inhalt');
        $page->setPublished(true);
        return $page;
    }
}
