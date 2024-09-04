<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
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
        // User fields
        $builder
            ->add('user', TextType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail Adresse',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'attr' => ['class' => 'form-control'],
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You should agree to our terms.',
                    ]),
                ],
            ]);

        // Member fields
        $builder
            ->add('firstname', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'label' => 'First Name',
            ])
            ->add('lastname', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'label' => 'Last Name',
            ])
            ->add('birthday', DateType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'widget' => 'single_text',
                'mapped' => false,
                'label' => 'Birthday',
            ])
            ->add('mobile', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'label' => 'Mobile Number',
            ])
            ->add('phone', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'label' => 'Phone Number',
            ])
            ->add('street', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'label' => 'Street',
            ])
            ->add('postal', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'label' => 'Postal Code',
            ])
            ->add('city', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'label' => 'City',
            ])
            ->add('category', ChoiceType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'choices' => [
                    'Student' => 'student',
                    'Mitglied' => 'member',
                    'Instructor' => 'instructor',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'mapped' => false,
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                    // any other statuses
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}