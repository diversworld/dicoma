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
    #[Route('/install', name: 'app_install', methods: ['GET'])]
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
            // Fetch data from form
            $data = $form->getData();

            // Parse data into DATABASE_URL format.
            $databaseUrl = sprintf('%s://%s:%s@%s:%d/%s?serverVersion=%s&charset=utf8mb4',
                $data['db_driver'], $data['db_user'], $data['db_pass'], $data['db_host'],
                $data['db_port'], $data['db_name'], $data['db_version']
            );

            // Write DATABASE_URL to .env.local
            $accesstoDotEnv= $this->getParameter('kernel.project_dir').'/.env.local';

            $data = 'DATABASE_URL="'.$databaseUrl.'"';
            $filesystem->dumpFile($accesstoDotEnv, $data);

            // Redirect to a new route where you will create the admin user
            return $this->redirectToRoute('app_register_install');
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
                $dbVersion,
            );

            $filesystem->appendToFile(
                $this->getParameter(
                    'kernel.project_dir').'/.env.local',
                $databaseUrl
            );

            $kernelProjectDir = $this->getParameter('kernel.project_dir');

            $process = new Process(['php', 'bin/console', 'doctrine:database:create'], $kernelProjectDir);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $process = new Process(['php', 'bin/console', 'doctrine:schema:update', '--force'], $kernelProjectDir);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            // Nach dem erfolgreichen Schreiben der Daten in die Datei,
            // den Admin-Nutzer erstellen.
            return $this->redirectToRoute('app_register_install', ['adminUser' => true]);

        } catch (IOExceptionInterface $exception) {
            echo "An error occurred while creating your .env.local file at ".$exception->getPath();
            // or return error message to the user using flash messages or other technique
        }

        // Database details were saved, now redirect to home page (or any other page where you validate DB connection and create tables).
        return $this->redirectToRoute('app_home');
    }
}
