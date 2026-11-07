<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class PersonContactFields
{
    public static function add(FormBuilderInterface $builder, string $prefix = ''): void
    {
        foreach (['email' => ['Kontakt-E-Mail', EmailType::class], 'mobile' => ['Mobiltelefon', TelType::class], 'phone' => ['Telefon', TelType::class], 'street' => ['Straße / Hausnummer', TextType::class], 'postalCode' => ['Postleitzahl', TextType::class], 'city' => ['Ort', TextType::class]] as $name => [$label, $type]) {
            $builder->add($name, $type, ['label' => $label, 'required' => false, 'property_path' => $prefix . $name]);
        }
    }
}
