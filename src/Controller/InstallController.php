<?php
// src/Controller/InstallController.php

namespace App\Controller;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Range;

class InstallController extends AbstractController
{
    #[Route('/install', name: 'app_install', methods: ['GET', 'POST'])]
    public function install(Request $request, Filesystem $filesystem): Response
    {
        $defaultData = [
            'db_driver' => 'mysql',
            'db_host' => 'localhost',
            'db_name' => 'my_database',
            'db_user' => 'root',
            'db_pass' => '',
            'db_port' => '3306',
        ];
        $form = $this->createFormBuilder($defaultData)
            ->add('db_driver', ChoiceType::class, [
                'choices' => ['mysql' => 'mysql', 'postgresql' => 'pgsql'],
            ])
            ->add('db_version', TextType::class)
            ->add('db_host', TextType::class)
            ->add('db_port', NumberType::class, [
                'constraints' => [new Range(['min' => 1025, 'max' => 65535])],
            ])
            ->add('db_name', TextType::class)
            ->add('db_user', TextType::class)
            ->add('db_pass', TextType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Fetch data from the form
            $data = $form->getData();

            // Parse data into DATABASE_URL format.
            $databaseUrl = sprintf('%s://%s:%s@%s:%d/%s?serverVersion=%s&charset=utf8mb4',
                $data['db_driver'], $data['db_user'], $data['db_pass'], $data['db_host'],
                $data['db_port'], $data['db_name'], $data['db_version']
            );

            // Write DATABASE_URL to .env.local
            $envFilePath = $this->getParameter('kernel.project_dir').'/.env.local';

            try {
                // Check if the .env.local file exists, and create it if not.
                if (!$filesystem->exists($envFilePath)) {
                    $filesystem->touch($envFilePath);
                }

                // Load the current content of the file.
                $envContent = file_get_contents($envFilePath);

                // Check and update the `DATABASE_URL`.
                if (strpos($envContent, 'DATABASE_URL') === false) {
                    // Append DATABASE_URL if it does not exist.
                    $envContent .= "\nDATABASE_URL=\"$databaseUrl\"";
                } else {
                    // Replace the existing DATABASE_URL entry.
                    $pattern = '/^DATABASE_URL=.*$/m';
                    $replacement = 'DATABASE_URL="' . $databaseUrl . '"';
                    $envContent = preg_replace($pattern, $replacement, $envContent);
                }

                // Overwrite the file with the new content.
                $filesystem->dumpFile($envFilePath, $envContent);

                // Redirect to a new route where you will create the admin user
                return $this->redirectToRoute('app_register_install');
            } catch (IOExceptionInterface $exception) {
                echo "An error occurred while creating your .env.local file at " . $exception->getPath();
                // or return error message to the user using flash messages or other technique
            }
        }

        // Render DB parameters form
        return $this->render('install/db_config.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/save_db_config', name: 'save_db_config', methods: ['GET', 'POST'])]
    public function saveDbConfig(Request $request): Response
    {
        $dbDriver = $request->request->get('db_driver');
        $dbUser = $request->request->get('db_user');
        $dbPass = $request->request->get('db_pass');
        $dbHost = $request->request->get('db_host');
        $dbPort = $request->request->get('db_port');
        $dbName = $request->request->get('db_name');
        $dbVersion = $request->request->get('db_version');

        // Write the DB config to .env.local
        $filesystem = new Filesystem();
        try {
            $databaseUrl = sprintf('DATABASE_URL="%s://%s:%s@%s:%d/%s?serverVersion=%s&charset=utf8mb4"',
                $dbDriver,
                $dbUser,
                $dbPass,
                $dbHost,
                $dbPort,
                $dbName,
                $dbVersion
            );

            // Dateienpfad festlegen
            $envFilePath = $this->getParameter('kernel.project_dir').'/.env.local';

            // Sicherstellen das die Datei existiert
            if (!$filesystem->exists($envFilePath)) {
                $filesystem->touch($envFilePath);
            }

            // Dateiinhalt laden
            $envContent = file_get_contents($envFilePath);

            // Überprüfen und aktualisieren
            if (strpos($envContent, 'DATABASE_URL') === false) {
                $envContent .= "\n$databaseUrl";
            } else {
                $envContent = preg_replace(
                    '/^DATABASE_URL=.*$/m',
                    $databaseUrl,
                    $envContent
                );
            }

            // Datei/das File überschreiben
            $filesystem->dumpFile($envFilePath, $envContent);

            // Arbeitsverzeichnis für die Prozesse
            $kernelProjectDir = $this->getParameter('kernel.project_dir');

            // Prozess zum Erstellen der Datenbank ausführen
            $process = new Process(['php', 'bin/console', 'doctrine:database:create'], $kernelProjectDir);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Prozess zum Aktualisieren des Schemas ausführen
            $process = new Process(['php', 'bin/console', 'doctrine:schema:update', '--force'], $kernelProjectDir);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Nach erfolgreichem Schreiben der Daten und der Ausführung der Prozesse, den Admin-Benutzer erstellen.
            return $this->redirectToRoute('app_register_install', ['adminUser' => true]);

        } catch (IOExceptionInterface $exception) {
            // Fehlerbehandlung beim Schreiben der Datei
            echo "An error occurred while creating your .env.local file at " . $exception->getPath();
            // Fehlernachricht an den Benutzer zurückgeben
        }

        // Weiterleitung zur Startseite oder zu einer anderen Seite nach der erfolgreichen Speicherung der Datenbankdetails
        return $this->redirectToRoute('app_home');
    }
}