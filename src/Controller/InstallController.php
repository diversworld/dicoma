<?php
// src/Controller/InstallController.php

namespace App\Controller;

use App\Security\InstallationGuard;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Range;

class InstallController extends AbstractController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    #[Route('/install', name: 'app_install', methods: ['GET', 'POST'])]
    public function install(Request $request, Filesystem $filesystem, InstallationGuard $installationGuard): Response
    {
        $installationGuard->denyUnlessExplicitlyEnabled();
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
                 $data['db_driver'], rawurlencode((string) $data['db_user']), rawurlencode((string) $data['db_pass']), $data['db_host'],
                $data['db_port'], rawurlencode((string) $data['db_name']), rawurlencode((string) $data['db_version'])
            );

            // Reject control characters and quotes before writing a dotenv value.
            if (preg_match('/[\x00-\x1F\x7F"\\\\]/', $databaseUrl)) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid database configuration.');
            }

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
                echo htmlspecialchars($this->translator->trans('install.configuration_write_failed', [
                    '%path%' => $exception->getPath(),
                ]), ENT_QUOTES);
                // or return error message to the user using flash messages or other technique
            }
        }

        // Render DB parameters form
        return $this->render('install/db_config.html.twig', [
            'form' => $form->createView(),
        ]);
    }

}
