<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class BookingCourseFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'course', ?string $label = 'Kurs'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
            return;
        }
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->innerJoin($alias . '.schedule', 'booking_filter_schedule')
            ->andWhere('IDENTITY(booking_filter_schedule.courses) = :booking_course_id')
            ->setParameter('booking_course_id', (int) $value);
    }
}
