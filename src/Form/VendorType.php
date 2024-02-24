<?php

namespace App\Form;

use App\Entity\Vendor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VendorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => [
                    'class' => 'form-control'],
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
            ->add('email', EmailType::class, [
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('phone', TelType::class, [
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
            ])
            ->add('website', TelType::class, [
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
            'data_class' => Vendor::class,
        ]);
    }
}
