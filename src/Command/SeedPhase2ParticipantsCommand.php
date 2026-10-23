<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CourseParticipant;
use App\Entity\Courses;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:phase2:seed-participants', description: 'Complete existing Phase 2 course participant fixtures in isolated DDEV test database.')]
final class SeedPhase2ParticipantsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('confirm-test-db', null, InputOption::VALUE_NONE, 'Confirm writing to isolated db_test.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || !$input->getOption('confirm-test-db')
            || getenv('DICOMA_TEST_EXPECTED_DATABASE') !== 'db_test') {
            $output->writeln('<error>Abbruch: APP_ENV=test, --confirm-test-db und DICOMA_TEST_EXPECTED_DATABASE=db_test sind erforderlich.</error>');
            return Command::FAILURE;
        }

        $connection = $this->em->getConnection();
        $params = $connection->getParams();
        if (($params['host'] ?? null) !== 'db_test'
            || (string) $connection->fetchOne('SELECT DATABASE()') !== 'db_test') {
            $output->writeln('<error>Abbruch: Verbindung muss auf Server db_test und Datenbank db_test zeigen.</error>');
            return Command::FAILURE;
        }

        $fixtures = [
            'A' => ['phase2-isolation-admin-a@example.invalid', 'Phase 2 Isolation Kurs A'],
            'B' => ['phase2-isolation-admin-b@example.invalid', 'Phase 2 Isolation Kurs B'],
        ];
        $participants = [];
        foreach ($fixtures as $key => [$email, $title]) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            $course = $this->em->getRepository(Courses::class)->findOneBy(['title' => $title]);
            $member = $user?->getMember();
            if (!$user || !$course || !$member || !$member->getClub() || !$course->getClub()
                || $member->getClub()->getId() !== $course->getClub()->getId()) {
                $output->writeln('<error>Abbruch: Fixture-Zuordnung für Verein '.$key.' fehlt oder ist inkonsistent. Keine Änderungen.</error>');
                return Command::FAILURE;
            }
            $participants[$key] = [$course, $member];
        }

        $existing = [];
        foreach ($participants as $key => [$course, $member]) {
            $existing[$key] = $this->em->getRepository(CourseParticipant::class)
                ->findOneBy(['course' => $course, 'member' => $member]);
        }
        if (($existing['A'] === null) !== ($existing['B'] === null)) {
            $output->writeln('<error>Abbruch: Nur ein Teilnehmer-Fixture existiert. Bitte Zustand manuell prüfen.</error>');
            return Command::FAILURE;
        }

        if ($existing['A'] === null) {
            $connection->beginTransaction();
            try {
                foreach ($participants as $key => [$course, $member]) {
                    $participant = (new CourseParticipant())->setCourse($course)->setMember($member);
                    $this->em->persist($participant);
                    $existing[$key] = $participant;
                }
                $this->em->flush();
                $connection->commit();
                $output->writeln('<info>Beide Kursteilnehmer-Fixtures angelegt.</info>');
            } catch (\Throwable $exception) {
                $connection->rollBack();
                $output->writeln('<error>Fehler: '.$exception->getMessage().'</error>');
                return Command::FAILURE;
            }
        } else {
            $output->writeln('<info>Beide Kursteilnehmer-Fixtures sind bereits vorhanden. Keine Änderungen.</info>');
        }

        $output->writeln('DICOMA_TEST_CLUB_A_PARTICIPANT_ID='.$existing['A']->getId());
        $output->writeln('DICOMA_TEST_CLUB_B_PARTICIPANT_ID='.$existing['B']->getId());
        return Command::SUCCESS;
    }
}
