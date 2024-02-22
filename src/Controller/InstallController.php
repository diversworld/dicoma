<?php

namespace App\Controller;

use App\Form\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Output\StreamOutput;

class InstallController extends AbstractController
{
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    #[Route('/install', name: 'app_install')]
    public function install(Request $request, UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->addFlash('success', 'Check Database.');
        var_dump('Check Database.');

        try{
            var_dump('try');
            // Überprüfen, ob die Installationsroutine bereits ausgeführt wurde
            if ($this->isInstalled()) {
                return $this->redirectToRoute('app_home'); // Weiterleitung an Startseite oder andere Route
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while running migrations: ' . $e->getMessage());
            var_dump('Fehler beim DB Check');
        }

        $this->addFlash('success', 'Start to create Database.');
        var_dump('Start to create Database.');

        // Führen Sie die Datenbankmigrationen aus
        $this->runMigrations();

        $this->addFlash('success', 'Create an Admin User.');
        var_dump('Create an Admin User.');
        // Führen Sie weitere Initialisierungsschritte aus
        $this->initializeApplication($request, $userPasswordHasher);

        // Markieren Sie die Installation als abgeschlossen
        $this->markAsInstalled();

        $this->addFlash('success', 'Installation done.');
        var_dump('Installation done.');

        return $this->redirectToRoute('app_home'); // Weiterleitung an Startseite oder andere Route
    }


    private function isInstalled(): bool
    {
        // Hier prüfen, ob die Installation bereits durchgeführt wurde
        // Zum Beispiel durch Überprüfung einer Konfigurationsvariable oder einer Datenbankeinstellung
    	$configPath = $this->getParameter('kernel.project_dir') . '/config/installation.yaml';

    	if (file_exists($configPath)) {
        	$config = Yaml::parseFile($configPath);
        	return isset($config['installed']) && $config['installed'] === true;
    	}
    	return false;
    }

	private function runMigrations(): void
	{
	    try {
	        $this->addFlash('success', 'Init run migration');
	        var_dump('Init run migration');

	        // Laden der Symfony-Anwendung
	        $application = new Application('App');

	        // Befehl zum Ausführen der Migrationen
	        $input = new ArrayInput([
	            'command' => 'doctrine:migrations:migrate',
	            '--no-interaction' => true, // Optional: Verhindert, dass der Befehl nach Bestätigung fragt
	        ]);

			// Bitte ersetzen Sie '/path/to/migration_output.txt' durch den tatsächlichen Dateipfad
			$outputFile = $this->getParameter('kernel.project_dir') . '/public/log/migration_output.txt';
        	$output = new StreamOutput(fopen($outputFile, 'w'));

			$this->addFlash('success', 'Start run migration');
	        var_dump('Start run migration');

	        // Ausgabevariable erstellen, ohne die Ausgabe des Befehls zu erfassen
	        //$output = new NullOutput(); // Ausgabeunterdrückung
			// Ausgabevariable erstellen, um die Ausgabe des Befehls zu erfassen
    		//$output = new StreamOutput();
			//Ausführen des Befehls
	        $application->run($input, $output);

			// Ausgabe abrufen und anzeigen
    		$migrationOutput = $output->fetch();
    		// Ausgabe anzeigen
    		echo $migrationOutput;
			var_dump($migrationOutput);

	        $this->addFlash('success', 'Database created successfully.');
			var_dump('Database created successfully.');

	    } catch (\Exception $e) {
   	    	// Behandlung von Ausnahmen
        	$this->addFlash('error', 'An error occurred while running migrations: ' . $e->getMessage());
			var_dump('Fehler beim ausführen der Migrations');
    	}
	}


    private function initializeApplication(Request $request, UserPasswordHasherInterface $userPasswordHasher): void
    {
        // Code für zusätzliche Initialisierungsschritte, z.B. Einfügen von Standarddaten
        $form = $this->createForm(UserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Erstellen Sie den neuen Benutzer mit der Rolle ROLE_ADMIN
            $userData = $form->getData();
            $user = new User();
            $user->setUsername($userData['username']);
            $user->setRoles(['ROLE_ADMIN']);
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            // Speichern Sie den Benutzer in der Datenbank
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            // Fügen Sie hier weitere Initialisierungsschritte hinzu, falls erforderlich

            $this->addFlash('success', 'Admin user created successfully.');
            $this->redirectToRoute('app_home');
            return;
        }

        $this->renderView('install/install.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function markAsInstalled(): void
    {
        $configPath = $this->getParameter('kernel.project_dir') . '/config/installation.yaml';
        file_put_contents($configPath, 'installed: true');
    }
}
