<?php
namespace App\Form;

use App\Entity\ScheduleTemplateEntry;
use App\Entity\TrainingUnitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ScheduleTemplateEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('trainingUnitType', EntityType::class, ['class' => TrainingUnitType::class, 'label' => 'Ausbildungseinheit', 'placeholder' => 'Bitte auswählen'])
            ->add('sequence', IntegerType::class, ['label' => 'Einheit Nr.', 'empty_data' => '1', 'attr' => ['min' => 1]])
            ->add('dayOffset', IntegerType::class, ['label' => 'Tage ab Start', 'empty_data' => '0', 'attr' => ['min' => 0]])
            ->add('startTime', TimeType::class, ['label' => 'Beginn', 'widget' => 'single_text', 'required' => false])
            ->add('durationMinutes', IntegerType::class, ['label' => 'Dauer (Minuten)', 'required' => false, 'attr' => ['min' => 1]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => ScheduleTemplateEntry::class]); }
}
