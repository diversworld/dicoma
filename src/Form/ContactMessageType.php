<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{EmailType, TextareaType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ContactMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Name', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]])
            ->add('email', EmailType::class, ['label' => 'E-Mail', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 254)]])
            ->add('message', TextareaType::class, ['label' => 'Nachricht', 'attr' => ['data-plain-text' => 'true', 'rows' => 8],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 5000)]]);
    }
}
