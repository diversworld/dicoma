<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Club;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Qualification;
use App\Enum\CourseStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class TrainingCourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $club = $options['club'];
        $builder
            ->add('title', TextType::class, ['label' => 'Kursname', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('category', ChoiceType::class, ['label' => 'Kursart', 'choices' => [
                'Beginner' => 'beginner', 'Aufbaukurse' => 'aufbau', 'Sonderkurse' => 'sonder',
                'Mischgas-Kurse' => 'mischgas', 'Technische Kurse' => 'technisch',
            ]])
            ->add('description', TextareaType::class, ['label' => 'Kursbeschreibung', 'required' => false])
            ->add('requirements', TextareaType::class, ['label' => 'Voraussetzungen', 'required' => false])
            ->add('requiredEquipment', TextareaType::class, ['label' => 'Benötigte Ausrüstung', 'required' => false])
            ->add('courseFee', MoneyType::class, ['label' => 'Kursgebühr', 'currency' => 'EUR', 'input' => 'string'])
            ->add('maxParticipants', IntegerType::class, ['label' => 'Maximale Teilnehmerzahl', 'required' => false])
            ->add('registrationDeadline', DateType::class, ['label' => 'Anmeldeschluss', 'widget' => 'single_text', 'required' => false])
            ->add('status', ChoiceType::class, ['label' => 'Kursstatus', 'choices' => [
                'Entwurf' => CourseStatus::DRAFT, 'Anmeldung geöffnet' => CourseStatus::OPEN,
                'In Ausbildung' => CourseStatus::RUNNING,
            ], 'choice_value' => static fn (?CourseStatus $status): ?string => $status?->value])
            ->add('qualification', EntityType::class, [
                'class' => Qualification::class, 'label' => 'Zielqualifikation', 'required' => false,
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('q')
                    ->where('q.club IS NULL OR q.club = :club')->setParameter('club', $club),
            ])
            ->add('leadInstructor', EntityType::class, [
                'class' => Member::class, 'label' => 'Verantwortlicher Ausbilder', 'required' => false,
                'choices' => $options['instructors'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Courses::class, 'instructors' => []]);
        $resolver->setAllowedTypes('instructors', 'array');
        $resolver->setRequired('club');
        $resolver->setAllowedTypes('club', Club::class);
    }
}
