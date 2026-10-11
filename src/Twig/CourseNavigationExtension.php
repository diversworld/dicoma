<?php

declare(strict_types=1);

namespace App\Twig;

use App\Controller\Admin\CoursesCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CourseNavigationExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'course_detail_url',
                [$this, 'getCourseDetailUrl']
            ),
        ];
    }

    public function getCourseDetailUrl(int $courseId): string
    {
        return (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setController(CoursesCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($courseId)
            ->generateUrl();
    }
}
