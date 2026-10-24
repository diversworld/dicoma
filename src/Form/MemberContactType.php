<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Member;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MemberContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['email' => ['Kontakt-E-Mail', EmailType::class], 'mobile' => ['Mobiltelefon', TelType::class], 'phone' => ['Telefon', TelType::class], 'street' => ['Straße / Hausnummer', TextType::class], 'postalCode' => ['Postleitzahl', TextType::class], 'city' => ['Ort', TextType::class]] as $name => [$label, $type]) {
            $builder->add($name, $type, ['label' => $label, 'required' => false, 'property_path' => 'person.' . $name]);
        }
        $builder->add('emergencyContactName', TextType::class, ['label' => 'Notfallkontakt: Name', 'required' => false])
            ->add('emergencyContactPhone', TelType::class, ['label' => 'Notfallkontakt: Telefon', 'required' => false])
            ->add('emergencyContactRelation', TextType::class, ['label' => 'Notfallkontakt: Beziehung', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Member::class]);
    }
}
