<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Person;
use App\Entity\Member;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

final class PersonContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        PersonContactFields::add($builder);
        $member = $options['member'];
        if ($member instanceof Member) {
            $builder
                ->add('emergencyContactName', TextType::class, ['label' => 'Notfallkontakt: Name', 'required' => false, 'mapped' => false, 'data' => $member->getEmergencyContactName(), 'constraints' => [new Length(max: 180)]])
                ->add('emergencyContactPhone', TelType::class, ['label' => 'Notfallkontakt: Telefon', 'required' => false, 'mapped' => false, 'data' => $member->getEmergencyContactPhone(), 'constraints' => [new Length(max: 40)]])
                ->add('emergencyContactRelation', TextType::class, ['label' => 'Notfallkontakt: Beziehung', 'required' => false, 'mapped' => false, 'data' => $member->getEmergencyContactRelation(), 'constraints' => [new Length(max: 100)]]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Person::class, 'member' => null]);
        $resolver->setAllowedTypes('member', [Member::class, 'null']);
    }
}
