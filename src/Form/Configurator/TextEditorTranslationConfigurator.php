<?php

declare(strict_types=1);

namespace App\Form\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TextEditorTranslationConfigurator implements FieldConfiguratorInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return $field->getFieldFqcn() === TextEditorField::class;
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $language = [];
        foreach ([
            'attachFiles', 'bold', 'bullets', 'byte', 'bytes', 'captionPlaceholder',
            'code', 'heading1', 'indent', 'italic', 'link', 'numbers', 'outdent',
            'quote', 'redo', 'remove', 'strike', 'undo', 'unlink', 'url', 'urlPlaceholder',
        ] as $key) {
            $language[$key] = $this->translator->trans('editor.'.$key);
        }
        $language += ['GB' => 'GB', 'KB' => 'KB', 'MB' => 'MB', 'PB' => 'PB', 'TB' => 'TB'];

        $field->setCustomOption(TextEditorField::OPTION_TRIX_EDITOR_CONFIG, array_replace_recursive(
            ['lang' => $language],
            $field->getCustomOption(TextEditorField::OPTION_TRIX_EDITOR_CONFIG) ?? []
        ));
    }
}
