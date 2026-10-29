<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\WebsiteDisplaySettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WebsiteDisplaySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('articlesPerPage', IntegerType::class, [
                'label' => 'Artikel pro Seite', 
                'attr' => ['min' => 1, 'max' => 100, 'class' => 'form-control']
                ]
            )
            ->add('eventsPerPage', IntegerType::class, [
                'label' => 'Veranstaltungen pro Seite', 
                'attr' => ['min' => 1, 'max' => 100, 'class' => 'form-control'],
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WebsiteDisplaySettings::class]);
    }
}

