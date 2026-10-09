<?php

namespace App\Form;

use App\Entity\Member;
use App\Entity\TeamMember;
use App\Enum\TeamMemberRole;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamMemberType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('member', EntityType::class, [
                'class' => Member::class,
                'label' => 'Mitglied',
                'placeholder' => 'Mitglied auswählen',
                'choice_label' => static function (
                    Member $member
                ): string {
                    $number = $member->getMemberNumber();

                    if ($number) {
                        return sprintf(
                            '%s (%s)',
                            $member->getFullName(),
                            $number
                        );
                    }

                    return $member->getFullName();
                },
            ])

            ->add('role', EnumType::class, [
                'class' => TeamMemberRole::class,
                'label' => 'Funktion',
                'choice_label' => static fn (
                    TeamMemberRole $role
                ): string => $role->label(),
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
            'data_class' => TeamMember::class,
        ]);
    }
}