<?php

namespace App\Controller;

use App\Entity\Member;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use App\Security\InstallationGuard;
use App\Security\BootstrapRegistrationLock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);

            // Create Member entity and link with User entity
            $member = new Member();
            $member->setUser($user);
            $member->setFirstname($form->get('firstname')->getData());
            $member->setLastname($form->get('lastname')->getData());
            $member->setBirthday($form->get('birthday')->getData());
            $member->setMobile($form->get('mobile')->getData());
            $member->setPhone($form->get('phone')->getData());
            $member->setStreet($form->get('street')->getData());
            $member->setPostalCode($form->get('postal')->getData());
            $member->setCity($form->get('city')->getData());
            $member->setCategory($form->get('category')->getData());
            $member->setStatus('active'); // Example status

            $entityManager->persist($member);
            $entityManager->flush();

            // Generate a signed URL and email it to the user
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('info@diversworld.eu', 'Diversworld'))
                    ->to($user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // Optionally send a notification email
            $email = (new TemplatedEmail())
                ->from(new Address('info@diversworld.eu', 'Diversworld'))
                ->to($user->getEmail())
                ->subject('New User Registration')
                ->htmlTemplate('registration/registration_email.html.twig');

            $mailer->send($email);

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/register/install', name: 'app_register_install', methods: ['GET', 'POST'])]
    public function install(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MailerInterface $mailer, InstallationGuard $installationGuard, UserRepository $users, BootstrapRegistrationLock $bootstrapLock): Response
    {
        $installationGuard->denyUnlessExplicitlyEnabled();
        // Bootstrap only: never allow a second account to be created via setup.
        // The installer must be used with a new, empty user database.
        if ($users->count([]) !== 0) {
            throw $this->createNotFoundException('Die Erstregistrierung ist bereits abgeschlossen.');
        }
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        $user->setRoles(['ROLE_SUPER_ADMIN']);

        if ($form->isSubmitted() && $form->isValid()) {
            // The lock covers the final user count AND the database flush.
            // The initial count above is only an early rejection for GET requests.
            $bootstrapLock->synchronized(function () use ($installationGuard, $users, $user, $form, $userPasswordHasher, $entityManager): void {
                $installationGuard->denyUnlessExplicitlyEnabled();
                if ($users->count([]) !== 0) {
                    throw $this->createNotFoundException('Die Erstregistrierung ist bereits abgeschlossen.');
                }

                $user->setPassword($userPasswordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
                $user->setRoles(['ROLE_SUPER_ADMIN']);
                $user->setIsVerified(true);
                $entityManager->persist($user);

                $member = new Member();
                $member->setUser($user);
                $member->setFirstname($form->get('firstname')->getData());
                $member->setLastname($form->get('lastname')->getData());
                $member->setBirthday($form->get('birthday')->getData());
                $member->setMobile($form->get('mobile')->getData());
                $member->setPhone($form->get('phone')->getData());
                $member->setStreet($form->get('street')->getData());
                $member->setPostalCode($form->get('postal')->getData());
                $member->setCity($form->get('city')->getData());
                $member->setCategory($form->get('category')->getData());
                $member->setStatus('active');
                $entityManager->persist($member);
                $entityManager->flush();
                // Persist the completion marker while the bootstrap lock is still held.
                $installationGuard->markCompleted();
            });

            // Generate a signed URL and email it to the user
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('info@diversworld.eu', 'Diversworld'))
                    ->to($user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // Optionally send a notification email
            $email = (new TemplatedEmail())
                ->from(new Address('info@diversworld.eu', 'Diversworld'))
                ->to($user->getEmail())
                ->subject('New User Registration')
                ->htmlTemplate('registration/registration_email.html.twig');

            $mailer->send($email);

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $users): Response
    {
        // Der signierte Link enthält die Benutzer-ID. Eine Anmeldung ist vor
        // der E-Mail-Bestätigung ausdrücklich nicht erforderlich.
        $id = $request->query->get('id');
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            throw $this->createNotFoundException('Ungültiger Bestätigungslink.');
        }

        $user = $users->find((int) $id);
        if ($user === null) {
            throw $this->createNotFoundException('Benutzer nicht gefunden.');
        }

        // Die Signatur bindet die URL an ID und aktuelle E-Mail-Adresse.
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_register');
    }
}