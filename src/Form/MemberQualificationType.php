<?php

namespace App\Form;

use App\Entity\MemberQualification;
use App\Entity\Qualification;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberQualificationType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('qualification', EntityType::class, [
                'class' => Qualification::class,
                'label' => 'Qualifikation',
                'placeholder' => 'Qualifikation auswählen',
                'query_builder' => static function (
                    EntityRepository $repository
                ) {
                    return $repository
                        ->createQueryBuilder('qualification')
                        ->andWhere(
                            'qualification.active = :active'
                        )
                        ->setParameter('active', true)
                        ->orderBy(
                            'qualification.sortOrder',
                            'ASC'
                        )
                        ->addOrderBy(
                            'qualification.name',
                            'ASC'
                        );
                },
            ])

            ->add('certificateNumber', TextType::class, [
                'label' => 'Brevet-/Zertifikatsnummer',
                'required' => false,
            ])

            ->add('issuedAt', DateType::class, [
                'label' => 'Ausgestellt am',
                'required' => false,
                'widget' => 'single_text',
            ])

            ->add('validUntil', DateType::class, [
                'label' => 'Gültig bis',
                'required' => false,
                'widget' => 'single_text',
            ])

            ->add('issuer', TextType::class, [
                'label' => 'Ausgestellt durch',
                'required' => false,
            ])

            ->add('verified', CheckboxType::class, [
                'label' => 'Nachweis geprüft',
                'required' => false,
            ])

            ->add('notes', TextareaType::class, [
                'label' => 'Notizen',
                'required' => false,
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => MemberQualification::class,
        ]);
    }
}