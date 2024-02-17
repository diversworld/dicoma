<?php

namespace App\Form;

use App\Entity\Student;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class StudentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class,[
                'label' => 'Vorname',
                'attr' => [
                    'placeholder' => 'Vorname',
                    'class' =>'form-control'
                ]
            ])
            ->add('lastname', TextType::class,[
                'label' => 'Nachname',
                'attr' => [
                    'placeholder' => 'Nachname',
                    'class' =>'form-control'
                ]
            ])
            ->add('street', TextType::class,[
                'label' => 'Straße',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Straße Hausnummer',
                    'class' =>'form-control'
                ]
            ])
            ->add('postal', IntegerType::class,[
                'label' => 'PLZ',
                'required' => false,
                'attr' => [
                    'placeholder' => '00000',
                    'class' =>'form-control'
                ]
            ])
            ->add('city', TextType::class,[
                'label' => 'Wohnort',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Wohnort',
                    'class' =>'form-control'
                ]
            ])
            ->add('birthdate', BirthdayType::class,[
                'label' => 'Geburtsdatum',
                'attr' => [
                    'placeholder' => 'Geburtsdatum',
                    'class' =>'form-control'
                ]
            ])
            ->add('mobile', TextType::class,[
                'label' => 'Mobil',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Mobilnummer',
                    'class' =>'form-control'
                ]
            ])
            ->add('phone', TextType::class,[
                'label' => 'Telefon',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Telefonnummer',
                    'class' =>'form-control'
                ]
            ])
            ->add('email', EmailType::class,[
                'label' => 'E-Mail',
                'attr' => [
                    'placeholder' => 'E-Mail Adresse',
                    'class' =>'form-control'
                ]
            ])
            ->add('username', TextType::class,[
                'label' => 'Benutzername',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Benutzername',
                    'class' => 'form-control',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Passwort',
                'mapped' => false,
                'required' => false,
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
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Passwort wiederholen',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control',
                ],
            ])
            /*
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
            ])*/
            ->add('status', ChoiceType::class,[
                'label' => 'Status',
                'choices' => [
                    'Status wählen' => '',
                    'Aktiv' => 'true',
                    'Inaktiv' => 'false',
                ],
                'required' => true,
                'attr' => [
                    'placeholder' => 'Status wählen',
                    'class' =>'form-control'
                ]
            ])
            ->add('submit', SubmitType::class,[
                'label' => 'Speichern',
                'attr' => [
                    'class' =>'form-button btn btn-main'
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
