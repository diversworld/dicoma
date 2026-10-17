<?php

declare(strict_types=1);

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;

final class TankOwnershipFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $label = 'Flaschentyp'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty('member')
            ->setLabel($label)
            ->setFormType(ChoiceFilterType::class)
            ->setFormTypeOption('value_type_options.choices', [
                'Vereinsflaschen' => 'club',
                'Mitgliederflaschen' => 'member',
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto $fieldDto, \EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $value = $filterDataDto->getValue();

        if ($value === 'club') {
            $queryBuilder->andWhere(sprintf('%s.clubTank IS NOT NULL', $alias));
        } elseif ($value === 'member') {
            $queryBuilder->andWhere(sprintf('%s.clubTank IS NULL AND %s.memberTank IS NOT NULL', $alias, $alias));
        }
    }
}
