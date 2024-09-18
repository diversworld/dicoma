<?php
namespace App\Form;

use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;
use App\Entity\Vendor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TankCheckType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Validate that available_tanks is an array of Tank objects
        foreach ($options['available_tanks'] as $tank) {
            if (!$tank instanceof Tank) {
                throw new \LogicException('The available_tanks option must be an array of Tank objects.');
            }
        }

        $builder
            ->add('checkDate', DateType::class, [
                'label' => 'Prüfdatum',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'widget' => 'single_text',
                'input_format' => 'dd.MM.yyyy',
                'format' => 'dd.MM.yyyy',
                'html5' => false,
            ])
            ->add('vendorName', EntityType::class, [
                'class' => Vendor::class,
                'choice_label' => 'name',
                'label' => 'Prüfer',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'multiple' => false,
                'placeholder' => 'Wählen Sie einen Prüfer',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Bemerkungen',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'tinymce',
                ],
            ])
            ->add('costInformation', TextareaType::class, [
                'label' => 'Kosteninformation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'tinymce',
                ],
            ])
            ->add('tank', EntityType::class, [
                'class' => Tank::class,
                'required' => false,
                'choice_label' => 'serialnumber',
                'choices' => $options['available_tanks'],
                'multiple' => true,
            ])
            ->add('articles', EntityType::class, [
                'class' => TankCheckArticle::class,
                'choice_label' => 'title',
                'label' => 'Artikel',
                'required' => false,
                'multiple' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TankCheck::class,
            'available_tanks' => [],
        ]);

        // Ensure the available_tanks option is correctly passed as an array
        $resolver->setAllowedTypes('available_tanks', 'array');
    }
}