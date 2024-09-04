<?php

namespace App\Form;

use App\Entity\Member;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('lastname', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('birthday', DateType::class, [
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('email', EmailType::class, [
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('mobile', TelType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
            ])
            ->add('phone', TelType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
            ])
            ->add('street', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
            ])
            ->add('postal', IntegerType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'mytextarea'
                ],
                'required' => false,
            ])
            ->add('category', ChoiceType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'choices'  => [
                    'Student' => 'student',
                    'Mitglied' => 'member',
                    'Instructor' => 'instructor',
                ],
            ])
            ->add('published', CheckboxType::class, [
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('user', EntityType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
                'class' => User::class,
                'choice_label' => 'user',
                'placeholder' => '',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options'  => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm Password'],
            ])
            ->add('status', ChoiceType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'choices'  => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                    // ... any other statuses
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Speichern',
                'attr' => [
                    'class' => 'form-button btn-main'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Member::class,
        ]);
    }
}
