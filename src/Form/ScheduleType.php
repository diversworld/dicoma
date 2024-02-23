<?php

namespace App\Form;

use App\Entity\Courses;
use App\Entity\Schedule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class,[
                'label' => 'Kurstermin',
                'attr' => [
                    'placeholder' => 'Bezeichnung',
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
                    //'style' => 'display:none;'
                ]
            ])
            ->add('startDate', DateType::class,[
                'label' => 'Startdatum',
                'attr' => [
                    'placeholder' => 'Startdatum',
                    'class' =>'form-control'
                ]
            ])
            ->add('duration', IntegerType::class,[
                'label' => 'Kursdauer in Tagen',
                'attr' => [
                    'placeholder' => 'Tage',
                    'class' =>'form-control'
                ]
            ])
            ->add('startTime', TimeType::class,[
                'label' => 'Startzeit',
                'attr' => [
                    'placeholder' => 'Startzeit',
                    'class' =>'form-control'
                ]
            ])
            ->add('location', TextType::class,[
                'label' => 'Kursort',
                'attr' => [
                    'placeholder' => 'Kursort',
                    'class' =>'form-control'
                ]
            ])
            ->add('locationStreet', TextType::class,[
                'label' => 'Straße',
                'attr' => [
                    'placeholder' => 'Straße',
                    'class' =>'form-control'
                ]
            ])
            ->add('locationPostal', TextType::class,[
                'label' => 'PLZ',
                'attr' => [
                    'placeholder' => '00000',
                    'class' =>'form-control'
                ]
            ])
            ->add('locationCity', TextType::class,[
                'label' => 'Ort',
                'attr' => [
                    'placeholder' => 'Ort',
                    'class' =>'form-control'
                ]
            ])
            ->add('price', MoneyType::class,[
                'label' => 'Preis',
                'divisor' => 1,
                'currency' => 'EUR',
                'attr' => [
                    'placeholder' => 'Preis',
                    'class' =>'form-control'
                ]
            ])
            ->add('notes', TextareaType::class,[
                'label' => 'Kursname',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Bemerkungen',
                    'class' =>'form-control tinymce',
                    'id' => 'tinymce'
                ]
            ])
            ->add('courses', EntityType::class, [
                'label' => 'Kurs',
                'class' => Courses::class,
                'choice_label' => 'title',
                'attr' => [
                    'placeholder' => 'Kurs',
                    'class' =>'form-control'
                ]
            ])
            ->add('submit', SubmitType::class,[
                'label' => 'Speichern',
            'attr' => [
                'class' =>'form-button btn btn-transparent btn-solid-border'
            ]
        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schedule::class,
            'files' => [],
        ]);
    }
}
