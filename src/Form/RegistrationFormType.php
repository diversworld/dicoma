<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname',TextType::class, [
                'label' => 'Vorname',
                'attr' => [
                    'placeholder' => 'Vorname',
                    'class' => 'form-control',
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nachname',
                'attr' => [
                    'placeholder' => 'Nachname',
                    'class' => 'form-control',
                ],
            ])
            ->add('email', EmailType::class,[
                'label' => 'E-Mail Adresse',
                'attr' => [
                    'placeholder' => 'E-Mail Adresse',
                    'class' => 'form-control',
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'Benutzername',
                'attr' => [
                    'placeholder' => 'Benutzername',
                    'class' => 'form-control',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => true,
                'mapped' => false,
                'first_options'  => [
                    'label' => 'Passwort',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'form-control',
                    ],
                ],
                'second_options' => [
                    'label' => 'Passwort wiederholen',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'form-control',
                    ],
                ],
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 12,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        // max length allowed by Symfony for security reasons
                        'max' => 40,
                    ]),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Ich akzeptiere die Nutzungsbedingungen ',
                'attr' => [
                    'class' => 'form-check',
                ],
                'constraints' => [
                    new IsTrue([
                        'message' => 'Du musst die Nutzungsbedingungen akzeptieren.',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class,[
                'label' => 'Registrieren',
                'attr' => [
                    'class' =>'btn btn-main'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
