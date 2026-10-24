<?php
namespace App\Form;

use App\Entity\Schedule;
use App\Entity\Member;
use App\Repository\MemberRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SchedulePlanningRowType extends AbstractType
{
    public function __construct(private readonly MemberRepository $members) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
			->add('startDate',
				DateType::class, ['label' => false, 'required' => false, 'widget' => 'single_text'])
            ->add('startTime', TimeType::class, [
				'label' => 'Beginn',
				'required' => false,
				'widget' => 'single_text',
				'attr' => [
					'data-time-start' => '1',
				],
			])
            ->add('durationHours', NumberType::class, [
				'label' => 'Dauer (Stunden)',
				'required' => false,
				'scale' => 2,
                'constraints' => [new Assert\Range(min: 0.25, max: 24)],
				'attr' => [
					'min' => 0,
					'step' => 0.25,
					'data-time-duration' => '1',
				],
			])
            ->add('location',
				TextType::class, ['label' => false, 'required' => false, 'constraints' => [new Assert\Length(max: 100)]])
            ->add('instructor',
				EntityType::class, ['class' => Member::class, 'label' => false, 'required' => false,  'placeholder' => '—',
                    'choices' => $this->members->findInstructors($options['club'])]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Schedule::class, 'club' => null]);
    }
}
