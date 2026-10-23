<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Club;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:phase2:seed-club-isolation', description: 'Create isolated Phase 2 HTTP test fixtures in the designated test database.')]
final class SeedClubIsolationCommand extends Command
{
    //private const DATABASE = 'h20292_dicoma_test';
    private const DATABASE = 'db_test';
    private const PREFIX = 'phase2-isolation-';

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('confirm-test-db', null, InputOption::VALUE_NONE, 'Explicitly confirm writing to the isolated test database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test') {
            $output->writeln('<error>Abbruch: APP_ENV muss test sein.</error>');
            return Command::FAILURE;
        }
        if (!$input->getOption('confirm-test-db')) {
            $output->writeln('<error>Abbruch: --confirm-test-db fehlt.</error>');
            return Command::FAILURE;
        }

        $connection = $this->em->getConnection();
        $database = (string) $connection->fetchOne('SELECT DATABASE()');
        if ($database !== self::DATABASE) {
            $output->writeln('<error>Abbruch: Unerwartete Datenbank: '. $database .'</error>');
            return Command::FAILURE;
        }

        // Do not modify or reuse partially present fixtures.
        $existingUsers = (int) $connection->fetchOne("SELECT COUNT(*) FROM `user` WHERE user LIKE 'phase2-isolation-%'");
        $existingClubs = (int) $connection->fetchOne("SELECT COUNT(*) FROM club WHERE name LIKE 'Phase 2 Isolation %'");
        $existingCourses = (int) $connection->fetchOne("SELECT COUNT(*) FROM courses WHERE title LIKE 'Phase 2 Isolation %'");
        if ($existingUsers + $existingClubs + $existingCourses > 0) {
            $output->writeln('<error>Abbruch: Phase-2-Fixtures existieren bereits. Keine Änderungen vorgenommen.</error>');
            return Command::FAILURE;
        }

        $connection->beginTransaction();
        try {
            $clubA = (new Club())->setName('Phase 2 Isolation Verein A')->setShortName('P2-A');
            $clubB = (new Club())->setName('Phase 2 Isolation Verein B')->setShortName('P2-B');
            $this->em->persist($clubA);
            $this->em->persist($clubB);

            $users = [];
            foreach ([
                'a' => ['admin-a', 'Admin', 'Verein A', [User::ROLE_ADMIN], $clubA],
                'b' => ['admin-b', 'Admin', 'Verein B', [User::ROLE_ADMIN], $clubB],
                'super' => ['super-admin', 'Super', 'Administrator', [User::ROLE_SUPER_ADMIN], null],
                'user' => ['normal-user', 'Normal', 'Benutzer', [User::ROLE_USER], null],
            ] as $key => [$suffix, $first, $last, $roles, $club]) {
                $login = self::PREFIX . $suffix;
                $email = $login . '@example.invalid';
                // Password is intentionally random, not printed, and not usable for manual login.
                $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    throw new \RuntimeException('Password hashing failed.');
                }
                $user = (new User())->setUser($login)->setEmail($email)
                    ->setPassword($passwordHash)->setRoles($roles)->setIsVerified(true);
                $member = (new Member())->setFirstname($first)->setLastname($last)
                    ->setBirthday(new \DateTime('1990-01-01'))->setClub($club)->setUser($user);
                $user->setMember($member);
                $this->em->persist($user);
                $this->em->persist($member);
                $users[$key] = $user;
            }

            $courseA = (new Courses())->setTitle('Phase 2 Isolation Kurs A')->setCategory('test')->setClub($clubA);
            $courseB = (new Courses())->setTitle('Phase 2 Isolation Kurs B')->setCategory('test')->setClub($clubB);
            $this->em->persist($courseA);
            $this->em->persist($courseB);
            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $output->writeln('<error>Fixtures konnten nicht angelegt werden: '.$exception->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Phase-2-Fixtures erfolgreich angelegt.</info>');
        foreach (['a' => 'CLUB_A_ADMIN', 'b' => 'CLUB_B_ADMIN', 'super' => 'SUPER_ADMIN', 'user' => 'ROLE_USER'] as $key => $name) {
            $output->writeln('DICOMA_TEST_'.$name.'_EMAIL='.$users[$key]->getEmail());
        }
        $output->writeln('DICOMA_TEST_CLUB_A_COURSE_ID='.$courseA->getId());
        $output->writeln('DICOMA_TEST_CLUB_B_COURSE_ID='.$courseB->getId());
        $output->writeln('DICOMA_TEST_EXPECTED_DATABASE='.self::DATABASE);
        return Command::SUCCESS;
    }
}
