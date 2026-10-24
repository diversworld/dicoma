<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tournament;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as F;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class TournamentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', F\TextType::class, ['label' => 'Turniername', 'empty_data' => ''])
            ->add('sportCode', F\TextType::class, ['label' => 'Sportkennung', 'empty_data' => '', 'help' => 'Entspricht dem Kürzel der Sportart, zum Beispiel uwr oder uwh. Ohne Kürzel wird der Sportname verwendet.'])
            ->add('organizer', F\TextType::class, ['label' => 'Veranstalter', 'empty_data' => ''])
            ->add('location', F\TextType::class, ['label' => 'Veranstaltungsort', 'empty_data' => ''])
            ->add('description', F\TextareaType::class, ['label' => 'Beschreibung und Regeln', 'required' => false])
            ->add('startsAt', F\DateTimeType::class, ['label' => 'Beginn', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endsAt', F\DateTimeType::class, ['label' => 'Ende', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('registrationDeadline', F\DateTimeType::class, ['label' => 'Meldeschluss', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('feeCents', F\MoneyType::class, ['label' => 'Teilnahmegebühr je Mannschaft', 'divisor' => 100, 'currency' => 'EUR', 'input' => 'integer'])
            ->add('maxTeams', F\IntegerType::class, ['label' => 'Maximale Mannschaftszahl'])
            ->add('minPlayers', F\IntegerType::class, ['label' => 'Mindestspielerzahl je Kader'])
            ->add('maxPlayers', F\IntegerType::class, ['label' => 'Maximale Spielerzahl je Kader'])
            ->add('winPoints', F\IntegerType::class, ['label' => 'Wertungspunkte für Sieg'])
            ->add('drawPoints', F\IntegerType::class, ['label' => 'Wertungspunkte für Unentschieden'])
            ->add('lossPoints', F\IntegerType::class, ['label' => 'Wertungspunkte für Niederlage']);
        foreach (['feeCents', 'maxTeams', 'minPlayers', 'maxPlayers', 'winPoints', 'drawPoints', 'lossPoints'] as $field) {
            $options = $builder->get($field)->getOptions();
            $options['constraints'] = [new Assert\NotNull()];
            $options['setter'] = static function (Tournament $tournament, ?int $value) use ($field): void {
                if ($value !== null) { $tournament->{'set' . ucfirst($field)}($value); }
            };
            $builder->add($field, $builder->get($field)->getType()->getInnerType()::class, $options);
        }
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => Tournament::class]); }
}
