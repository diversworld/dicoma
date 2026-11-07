<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Member;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MemberContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        PersonContactFields::add($builder, 'person.');
        $builder->add('emergencyContactName', TextType::class, ['label' => 'Notfallkontakt: Name', 'required' => false])
            ->add('emergencyContactPhone', TelType::class, ['label' => 'Notfallkontakt: Telefon', 'required' => false])
            ->add('emergencyContactRelation', TextType::class, ['label' => 'Notfallkontakt: Beziehung', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Member::class]);
    }
}
