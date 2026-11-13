<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class RoleValidityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options['roles'] as $label => $role) {
            $builder->add($role, FormType::class, [
                'label' => $label,
                'constraints' => [new Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    if (is_array($data) && !empty($data['startsAt']) && !empty($data['endsAt'])
                        && $data['startsAt'] > $data['endsAt']) {
                        $context->buildViolation('Das Ende darf nicht vor dem Beginn liegen.')->atPath('endsAt')->addViolation();
                    }
                })],
            ]);
            $builder->get($role)
                ->add('startsAt', DateType::class, ['label' => 'Gültig ab', 'widget' => 'single_text', 'input' => 'string', 'required' => false])
                ->add('endsAt', DateType::class, ['label' => 'Gültig bis einschließlich', 'widget' => 'single_text', 'input' => 'string', 'required' => false]);
        }
        $roles = array_values($options['roles']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($roles): void {
            $data = $event->getData();
            if (!is_array($data)) {
                $event->getForm()->addError(new FormError('Die Rollengültigkeiten müssen vollständig übermittelt werden.'));
                return;
            }
            foreach ($roles as $role) {
                if (!isset($data[$role]) || !is_array($data[$role])
                    || !array_key_exists('startsAt', $data[$role]) || !array_key_exists('endsAt', $data[$role])) {
                    $event->getForm()->addError(new FormError('Die Rollengültigkeiten müssen vollständig übermittelt werden.'));
                    return;
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'roles' => []]);
        $resolver->setAllowedTypes('roles', 'array');
    }
}
