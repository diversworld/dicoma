<?php

namespace App\Form;

use App\Entity\MemberSport;
use App\Entity\Sport;
use App\Entity\Club;
use App\Enum\MemberSportStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberSportType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $club = $options['club'];
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($club): void {
            $assignment = $event->getData();
            $currentSportId = $assignment instanceof MemberSport ? $assignment->getSport()?->getId() : null;
            $event->getForm()->add('sport', EntityType::class, [
                'class' => Sport::class,
                'label' => 'Sportart',
                'placeholder' => 'Sportart auswählen',
                'query_builder' => static function (
                    EntityRepository $repository
                ) use ($club, $currentSportId) {
                    return $repository
                        ->createQueryBuilder('sport')
                        ->andWhere('sport.club = :club')
                        ->andWhere('(sport.active = :active OR sport.id = :currentSport)')
                        ->setParameter('club', $club)
                        ->setParameter('active', true)
                        ->setParameter('currentSport', $currentSportId)
                        ->orderBy('sport.sortOrder', 'ASC')
                        ->addOrderBy('sport.name', 'ASC');
                },
            ]);
        });
        $builder
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
            'club' => null,
        ]);
        $resolver->setAllowedTypes('club', ['null', Club::class]);
    }
}