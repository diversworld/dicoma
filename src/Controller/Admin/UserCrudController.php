<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')
                ->hideOnForm()
                ->setColumns(3),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            FormField::addFieldset('Nutzerdaten')
                ->collapsible(),
            TextField::new('username', 'Benutzername')
                ->setColumns(3),
            EmailField::new('email', 'E-Mail')
                ->setColumns(3),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            TextField::new('password', 'Passwort')
                ->onlyOnForms()
                ->setTemplatePath('admin/fields/password.html.twig')
                ->setColumns(3),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            BooleanField::new('isVerified','Verifiziert')
                ->renderAsSwitch(true)
                ->setColumns(4),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            ChoiceField::new('roles','Rollen')
                ->setColumns(3)
                ->allowMultipleChoices('true')
                ->setChoices([
                    'Kunde' => 'ROLE_CUSTOMER',
                    'Student' => 'ROLE_STUDENT',
                    'Instructor' => 'ROLE_INSTRUCTOR',
                    'Admin' => 'ROLE_ADMIN',
                    'SuperAdmin' => 'ROLE_SUPER_ADMIN',
                ]),
            FormField::addFieldset('Mitglied')
                ->collapsible(),
            AssociationField::new('member', 'Mitgliedsname')
                ->setColumns(3),
        ];
    }

}
