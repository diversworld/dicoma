<?php

namespace App\Form;

use App\Entity\MemberSport;
use App\Entity\Sport;
use App\Enum\MemberSportStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberSportType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('sport', EntityType::class, [
                'class' => Sport::class,
                'label' => 'Sportart',
                'placeholder' => 'Sportart auswählen',
                'query_builder' => static function (
                    EntityRepository $repository
                ) {
                    return $repository
                        ->createQueryBuilder('sport')
                        ->andWhere('sport.active = :active')
                        ->setParameter('active', true)
                        ->orderBy('sport.sortOrder', 'ASC')
                        ->addOrderBy('sport.name', 'ASC');
                },
            ])

            ->add('status', EnumType::class, [
                'class' => MemberSportStatus::class,
                'label' => 'Status',
                'choice_label' => static fn (
                    MemberSportStatus $status
                ): string => $status->label(),
            ])

            ->add('joinedAt', DateType::class, [
                'label' => 'Seit',
                'required' => false,
                'widget' => 'single_text',
            ])

            ->add('leftAt', DateType::class, [
                'label' => 'Bis',
                'required' => false,
                'widget' => 'single_text',
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
            'data_class' => MemberSport::class,
        ]);
    }
}