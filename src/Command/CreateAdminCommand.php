<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Legt den ersten DiCoMa-Administrator an.'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'username',
                null,
                InputOption::VALUE_REQUIRED,
                'Benutzername'
            )
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'E-Mail-Adresse'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $helper = $this->getHelper('question');

        /*
         * Benutzername
         */
        $username = $input->getOption('username');

        if (!$username) {
            $question = new Question('Benutzername: ');
            $question->setValidator(
                static function (?string $value): string {
                    $value = trim((string) $value);

                    if ($value === '') {
                        throw new \RuntimeException(
                            'Der Benutzername darf nicht leer sein.'
                        );
                    }

                    return $value;
                }
            );

            $username = $helper->ask(
                $input,
                $output,
                $question
            );
        }

        /*
         * E-Mail
         */
        $email = $input->getOption('email');

        if (!$email) {
            $question = new Question('E-Mail-Adresse: ');
            $question->setValidator(
                static function (?string $value): string {
                    $value = trim((string) $value);

                    if (
                        $value === ''
                        || !filter_var($value, FILTER_VALIDATE_EMAIL)
                    ) {
                        throw new \RuntimeException(
                            'Bitte eine gültige E-Mail-Adresse eingeben.'
                        );
                    }

                    return $value;
                }
            );

            $email = $helper->ask(
                $input,
                $output,
                $question
            );
        }

        /*
         * Prüfen, ob Benutzer bereits existiert.
         */
        $repository = $this->entityManager
            ->getRepository(User::class);

        if ($repository->findOneBy(['user' => $username])) {
            $output->writeln(
                '<error>Dieser Benutzername existiert bereits.</error>'
            );

            return Command::FAILURE;
        }

        if ($repository->findOneBy(['email' => $email])) {
            $output->writeln(
                '<error>Diese E-Mail-Adresse wird bereits verwendet.</error>'
            );

            return Command::FAILURE;
        }

        /*
         * Passwort verdeckt abfragen.
         */
        $passwordQuestion = new Question('Passwort: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);

        $passwordQuestion->setValidator(
            static function (?string $password): string {
                if ($password === null || strlen($password) < 8) {
                    throw new \RuntimeException(
                        'Das Passwort muss mindestens 8 Zeichen lang sein.'
                    );
                }

                return $password;
            }
        );

        $password = $helper->ask(
            $input,
            $output,
            $passwordQuestion
        );

        /*
         * Passwort wiederholen.
         */
        $repeatQuestion = new Question('Passwort wiederholen: ');
        $repeatQuestion->setHidden(true);
        $repeatQuestion->setHiddenFallback(false);

        $repeatPassword = $helper->ask(
            $input,
            $output,
            $repeatQuestion
        );

        if ($password !== $repeatPassword) {
            $output->writeln(
                '<error>Die Passwörter stimmen nicht überein.</error>'
            );

            return Command::FAILURE;
        }

        /*
         * Benutzer erzeugen.
         */
        $user = new User();

        $user
            ->setUser($username)
            ->setEmail($email)
            ->setRoles([
                User::ROLE_SUPER_ADMIN,
            ])
            ->setIsVerified(true);

        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                $password
            )
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln('');
        $output->writeln(
            '<info>Der DiCoMa-Administrator wurde erfolgreich angelegt.</info>'
        );
        $output->writeln('');
        $output->writeln(
            sprintf('Benutzername: %s', $username)
        );
        $output->writeln(
            sprintf('E-Mail:       %s', $email)
        );
        $output->writeln('Rolle:        Super-Administrator');

        return Command::SUCCESS;
    }
}