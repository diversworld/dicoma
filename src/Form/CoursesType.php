<?php

namespace App\Form;

use App\Entity\Courses;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CoursesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $files = $options['files'];

        $builder
            ->add('title', TextType::class,[
                'label' => 'Kursname',
                'attr' => [
                    'placeholder' => 'Kursname',
                    'class' =>'form-control'
                ]
            ])
            ->add('image', FileType::class, [
                'mapped' => false,
                'label' => 'Bild wählen',
                'required' => false,
                'attr' => [
                    'id' => 'image',
                    'class' => 'form-control',
                ]
            ])
            ->add('description', TextareaType::class,[
                'required' => false,
                'label' => 'Kursbeschreibung',
                'attr' => [
                    'placeholder' => 'Beschreibung der Kursinhalte',
                    'class' =>'form-control tinymce',
                    'novalidate' => 'novalidate'
                ]
            ])
            ->add('requirements', TextareaType::class,[
                'required' => false,
                'label' => 'Voraussetzungen',
                'attr' => [
                    'placeholder' => 'Beschreibung der Voraussetzungen',
                    'class' =>'form-control tinymce',
                    'novalidate' => 'novalidate'
                ]
            ])
            ->add('category', ChoiceType::class,[
                'label' => 'Kategorie',
                'choices' => [
                    'Kategorie wählen' => '',
                    'Beginner' => 'beginner',
                    'Aufbaukure' => 'aufbau',
                    'Sonderkurse' => 'sonder',
                    'Mischgas Kurse' => 'mischgas',
                    'Technische Kurse' => 'technisch',
                ],
                'attr' => [
                    'placeholder' => 'Kategorie wählen',
                    'class' =>'form-control'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label' => 'Bemerkungen',
                'attr' => [
                    'placeholder' => 'Bemerkungen zum Kurs',
                    'class' =>'form-control tinymce',
                    'novalidate' => 'novalidate'
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
            'data_class' => Courses::class,
            'sanitize_html' => true,
            //'existing_image' => null, // Hier die Variable $existingImage übergeben
            'files' => [],
        ]);
    }
}
