<?php

namespace App\Controller;

use App\Entity\Member;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
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
            $member->setPostal($form->get('postal')->getData());
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

    #[Route('/register/install/{adminUser}', name: 'app_register_install', methods: ['GET', 'POST'])]
    public function install(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MailerInterface $mailer, ?bool $adminUser = null): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($adminUser !== null) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            if ($adminUser !== null) {
                $user->setRoles(['ROLE_ADMIN']);
                $user->setIsVerified(true);
            }

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
            $member->setPostal($form->get('postal')->getData());
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

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $this->getUser());
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_register');
    }
}