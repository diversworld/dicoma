<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Admin\DashboardController;
use App\Security\InstallationGuard;
use App\Service\InstallationBootstrap;
use App\Service\InstallationCatalog;
use App\Service\InstallationEnvironmentValue;
use App\Service\InstallationMigrator;
use App\Service\GitVersionChecker;
use App\Service\SingleClubMode;
use App\Service\SoftwareUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

final class InstallController extends AbstractController
{
    public function __construct(private readonly GitVersionChecker $versions) {}

    #[Route('/install', name: 'app_install', methods: ['GET', 'POST'])]
    public function database(Request $request, Filesystem $filesystem, InstallationGuard $guard): Response
    {
        $guard->denyUnlessExplicitlyEnabled();
        $form = $this->createFormBuilder([
            'db_driver' => 'mysql', 'db_host' => 'localhost', 'db_name' => 'dicoma',
            'db_user' => '', 'db_pass' => '', 'db_port' => 3306, 'db_version' => '11.2-MariaDB',
        ])
            ->add('db_driver', ChoiceType::class, ['label' => 'Datenbanksystem', 'choices' => ['MariaDB / MySQL' => 'mysql', 'PostgreSQL' => 'pgsql']])
            ->add('db_version', TextType::class, ['label' => 'Serverversion'])
            ->add('db_host', TextType::class, ['label' => 'Datenbankhost'])
            ->add('db_port', NumberType::class, ['label' => 'Port', 'constraints' => [new Assert\Range(min: 1, max: 65535)]])
            ->add('db_name', TextType::class, ['label' => 'Datenbankname'])
            ->add('db_user', TextType::class, ['label' => 'Benutzer'])
            ->add('db_pass', PasswordType::class, ['label' => 'Passwort', 'required' => false, 'always_empty' => false])
            ->getForm()->handleRequest($request);
        $error = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $charset = $data['db_driver'] === 'mysql' ? '&charset=utf8mb4' : '';
            $url = sprintf('%s://%s:%s@%s:%d/%s?serverVersion=%s%s',
                $data['db_driver'], rawurlencode((string) $data['db_user']), rawurlencode((string) $data['db_pass']),
                $data['db_host'], (int) $data['db_port'], rawurlencode((string) $data['db_name']),
                rawurlencode((string) $data['db_version']), $charset);
            if (preg_match('/[\x00-\x1F\x7F"\\\\]/', $url)) {
                throw $this->createAccessDeniedException('Ungültige Datenbankkonfiguration.');
            }
            try {
                $path = $this->getParameter('kernel.project_dir').'/.env.local';
                $content = is_file($path) ? (string) file_get_contents($path) : '';
                $line = 'DATABASE_URL="'.InstallationEnvironmentValue::databaseUrl($url).'"';
                $content = preg_match('/^DATABASE_URL=.*$/m', $content)
                    ? preg_replace('/^DATABASE_URL=.*$/m', $line, $content)
                    : rtrim($content)."\n".$line."\n";
                $secretLine = 'APP_SECRET="'.bin2hex(random_bytes(32)).'"';
                $content = preg_match('/^APP_SECRET=.*$/m', $content)
                    ? preg_replace('/^APP_SECRET=.*$/m', $secretLine, $content)
                    : rtrim($content)."\n".$secretLine."\n";
                $filesystem->dumpFile($path, (string) $content);
                $filesystem->chmod($path, 0600);
                return $this->redirectToRoute('app_install_setup');
            } catch (IOExceptionInterface) {
                $error = 'Die Konfiguration konnte nicht geschrieben werden. Prüfen Sie die Schreibrechte der Datei .env.local.';
            }
        }
        return $this->render('install/db_config.html.twig', ['form' => $form, 'error' => $error]);
    }

    #[Route('/install/setup', name: 'app_install_setup', methods: ['GET', 'POST'])]
    public function setup(Request $request, InstallationGuard $guard, InstallationMigrator $migrator, InstallationBootstrap $bootstrap): Response
    {
        $guard->denyUnlessExplicitlyEnabled();
        $form = $this->createFormBuilder()
            ->add('clubName', TextType::class, ['label' => 'Vereinsname', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('clubShortName', TextType::class, ['label' => 'Kurzname', 'required' => false])
            ->add('clubEmail', EmailType::class, ['label' => 'Vereins-E-Mail', 'constraints' => [new Assert\NotBlank()]])
            ->add('username', TextType::class, ['label' => 'Benutzername des Superadministrators', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 180)]])
            ->add('firstname', TextType::class, ['label' => 'Vorname', 'constraints' => [new Assert\NotBlank()]])
            ->add('lastname', TextType::class, ['label' => 'Nachname', 'constraints' => [new Assert\NotBlank()]])
            ->add('email', EmailType::class, ['label' => 'E-Mail des Superadministrators', 'constraints' => [new Assert\NotBlank()]])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class, 'invalid_message' => 'Die Passwörter stimmen nicht überein.',
                'first_options' => ['label' => 'Passwort'], 'second_options' => ['label' => 'Passwort wiederholen'],
                'constraints' => [new Assert\NotBlank(), new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)],
            ])
            ->getForm()->handleRequest($request);
        $error = null;
        $success = false;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $migrator->migrate();
                $result = $bootstrap->install($form->getData());
                $this->addFlash('success', sprintf('%s wurde eingerichtet. %d Grundfunktionen wurden installiert.', $result['club']->getName(), count($result['installed'])));
                $success = true;
            } catch (\Throwable $exception) {
                $error = $exception instanceof \DomainException || $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'Die Installation konnte nicht abgeschlossen werden. Prüfen Sie die Serverprotokolle und versuchen Sie es erneut.';
            }
        }
        return $this->render('install/setup.html.twig', ['form' => $form, 'error' => $error, 'success' => $success]);
    }

    #[Route('/admin/installation', name: 'admin_installation', methods: ['GET', 'POST'], defaults: DashboardController::ROUTE_DEFAULTS)]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function update(
        Request $request,
        SingleClubMode $clubs,
        InstallationCatalog $catalog,
        EntityManagerInterface $em,
        SoftwareUpdater $updater,
        LoggerInterface $logger,
    ): Response
    {
        $club = $clubs->club();
        $components = $catalog->components($club);
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('installation_update', (string) $request->request->get('_token'))) {
            if ($request->request->getString('operation') === 'check_version') {
                $status = $this->versions->check(true);
                $this->addFlash($status['checked'] ? 'success' : 'warning', $status['checked']
                    ? 'Versionsprüfung aktualisiert. Neuester Tag: '.$status['latest'].'.'
                    : 'Versionsprüfung nicht möglich: '.$status['error']);
                return $this->redirectToRoute('admin_installation');
            }
            if ($request->request->getString('operation') === 'install_version') {
                try {
                    $status = $this->versions->check(true);
                    if (!$status['checked']) {
                        throw new \RuntimeException('Versionsprüfung nicht möglich: '.$status['error']);
                    }
                    if (!$status['available'] || $status['latest'] === null) {
                        $this->addFlash('info', 'Die installierte Software ist bereits aktuell.');
                        return $this->redirectToRoute('admin_installation');
                    }
                    if ($request->request->getString('backup_confirmed') !== '1') {
                        throw new \DomainException('Der automatische Backup-Schritt muss bestätigt werden.');
                    }
                    $result = $updater->install($status['latest']);
                    $this->addFlash('success', sprintf(
                        'DiCoMa wurde auf Version %s aktualisiert. Sicherheits-Backup: %s (%.1f MB).',
                        $result['version'],
                        basename($result['backup']),
                        $result['backupSize'] / 1024 / 1024,
                    ));
                } catch (\Throwable $exception) {
                    $logger->error('Software update failed.', ['exception' => $exception]);
                    $this->addFlash('danger', $exception->getMessage());
                }
                return $this->redirectToRoute('admin_installation');
            }
            $component = (string) $request->request->get('component', '');
            $installed = $catalog->installMissing($club, $component === '' ? null : $component);
            $em->flush();
            $this->addFlash('success', count($installed) > 0 ? sprintf('%d neue Funktion(en) installiert.', count($installed)) : 'Alle Funktionen sind bereits aktuell.');
            return $this->redirectToRoute('admin_installation');
        }
        return $this->render('install/update.html.twig', [
            'components' => $components,
            'version' => InstallationCatalog::VERSION,
            'softwareVersion' => $this->versions->check(),
        ]);
    }
}
