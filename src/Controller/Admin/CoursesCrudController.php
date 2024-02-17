<?php

namespace App\Controller\Admin;

use App\Entity\Courses;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CoursesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Courses::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Kursinformation')
                ->collapsible(),
            IdField::new('id')
                ->hideOnForm()
                ->setColumns(3),
            TextField::new('title')
                ->setColumns(5),
            FormField::addFieldset('Bild')
                ->collapsible(),
            ImageField::new('image')
                ->setBasePath('/images/kurse/')
                ->setUploadDir('public/images/kurse/')
                ->setUploadedFileNamePattern('[name].[extension]')
                ->setRequired(false)
                ->setColumns(5),
            ChoiceField::new('category')
                ->setChoices([
                    'Beginner' => 'beginner',
                    'Aufbaukure' => 'aufbau',
                    'Sonderkurse' => 'sonder',
                    'Mischgas Kurse' => 'mischgas',
                    'Technische Kurse' => 'technisch'
                ])
                ->setColumns(2),
            FormField::addFieldset('Detailinformation')
                ->collapsible(),
            TextEditorField::new('requirements')
                ->hideOnIndex()
                ->setColumns(8),
            TextEditorField::new('description')
                ->hideOnIndex()
                ->setColumns(8),
            FormField::addFieldset('Notizen')
                ->collapsible(),
            TextEditorField::new('notes')
                ->hideOnIndex()
                ->setColumns(8),
        ];
    }

}
