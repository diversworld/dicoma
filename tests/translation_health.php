<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Translation\TwigExtractor;
use Symfony\Component\Console\Application;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\Extractor\ChainExtractor;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;

$root = dirname(__DIR__);
chdir($root);
$locales = ['de', 'en', 'fr', 'es'];
$translator = new Translator('de'); // No kernel, cache, logs or database.
$translator->setFallbackLocales(['en']);
$reader = new TranslationReader();
foreach (['php' => new PhpFileLoader(), 'xlf' => new XliffFileLoader(), 'yaml' => new YamlFileLoader()] as $format => $loader) {
    $translator->addLoader($format, $loader);
    $reader->addLoader($format, $loader);
}
$paths = [
    'vendor/symfony/validator/Resources/translations',
    'vendor/symfony/security-core/Resources/translations',
    'vendor/easycorp/easyadmin-bundle/translations',
    'vendor/symfonycasts/reset-password-bundle/src/Resources/translations',
    'vendor/symfonycasts/verify-email-bundle/src/Resources/translations',
    'translations',
];
foreach ($paths as $path) {
    foreach (glob($path.'/*') as $file) {
        if (!preg_match('/^(.+)\.(de|en|fr|es)\.(php|yaml|xlf)$/', basename($file), $match)) {
            continue;
        }
        $translator->addResource($match[3], $file, $match[2], $match[1]);
    }
}

$loader = new FilesystemLoader(['templates', 'vendor/symfony/twig-bridge/Resources/views/Form']);
$loader->addPath('vendor/easycorp/easyadmin-bundle/templates', 'EasyAdmin');
$twig = new Environment($loader, ['cache' => false, 'strict_variables' => false]);
$twig->addExtension(new TranslationExtension($translator));
$twig->addExtension(new FormExtension($translator));
foreach (['path', 'url', 'asset', 'csrf_token', 'is_granted', 'ea'] as $name) {
    $twig->addFunction(new TwigFunction($name, static fn (...$arguments) => ''));
}
$twig->addFilter(new TwigFilter('file_link', static fn ($file, ...$arguments) => $file));
$renderer = new FormRenderer(new TwigRendererEngine(['form_div_layout.html.twig'], $twig));
$twig->addRuntimeLoader(new FactoryRuntimeLoader([FormRenderer::class => static fn () => $renderer]));

$extractor = new ChainExtractor();
$extractor->addExtractor('twig', new TwigExtractor($twig));
$extractor->addExtractor('php', new Symfony\Component\Translation\Extractor\PhpAstExtractor([
    new Symfony\Component\Translation\Extractor\Visitor\TransMethodVisitor(),
    new Symfony\Component\Translation\Extractor\Visitor\TranslatableMessageVisitor(),
    new Symfony\Component\Translation\Extractor\Visitor\ConstraintVisitor(),
]));
$console = new class('DB-free translation health') extends Application {
    public function getKernel(): Symfony\Component\HttpKernel\KernelInterface
    {
        // TranslationDebugCommand only needs project paths; never boot it.
        return new App\Kernel('test', false);
    }
};
$console->add(new Symfony\Bridge\Twig\Command\LintCommand($twig));
$console->add(new Symfony\Component\Yaml\Command\LintCommand());
$console->add(new Symfony\Component\Translation\Command\TranslationLintCommand($translator, $locales));
$console->add(new Symfony\Bundle\FrameworkBundle\Command\TranslationDebugCommand(
    $translator, $reader, $extractor, 'translations', 'templates', [], [], $locales
));
if ($argc > 1) {
    $console->run();
    exit;
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
// Doctrine choices are substituted, not queried. Production FormTypes still
// build and render normally, including nested fields and enum choices.
$entityChoice = new class extends AbstractType {
    public function getParent(): string { return ChoiceType::class; }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['class' => null, 'query_builder' => null, 'em' => null]);
    }
};
$entityExtension = new class($entityChoice) extends PreloadedExtension {
    public function __construct(private FormTypeInterface $entityChoice) { parent::__construct([], []); }
    public function hasType(string $name): bool { return $name === EntityType::class || parent::hasType($name); }
    public function getType(string $name): FormTypeInterface { return $name === EntityType::class ? $this->entityChoice : parent::getType($name); }
};
$factory = Forms::createFormFactoryBuilder()
    ->addExtension($entityExtension)
    ->addExtension(new Symfony\Component\Form\Extension\Validator\ValidatorExtension(
        Symfony\Component\Validator\Validation::createValidatorBuilder()
            ->setTranslator($translator)->setTranslationDomain('validators')->getValidator()
    ))
    ->getFormFactory();
$messages = require 'translations/messages.en.php';
$checks = 0;
$missing = [];
$inspectAssert = static function (bool $condition, string $message) use (&$missing): void {
    if (!$condition) { $missing[] = $message; }
};
$inspect = function (FormView $view, string $file) use (&$inspect, &$checks, $inspectAssert, $renderer, $messages): void {
    if ($view->parent !== null && $view->vars['label'] !== false) {
        $key = $view->vars['label'] ?? $renderer->humanize($view->vars['name']);
        $inspectAssert(isset($messages[$key]) || preg_match('/^\d+$/', $key) === 1, "$file: missing label $key");
        ++$checks;
    }
    foreach (['help', 'placeholder'] as $option) {
        $key = $view->vars[$option] ?? null;
        if (is_string($key) && $key !== '' && $key !== '—') {
            $inspectAssert(isset($messages[$key]), "$file: missing $option $key");
        }
    }
    foreach ($view->vars['attr'] ?? [] as $name => $key) {
        if (in_array($name, ['placeholder', 'title'], true) && $key !== '' && !is_numeric($key)) {
            $inspectAssert(isset($messages[$key]), "$file: missing attribute $key");
        }
    }
    foreach ($view->vars['choices'] ?? [] as $choice) {
        if (isset($choice->label) && !preg_match('/^\d+L$/', $choice->label)) {
            $inspectAssert(isset($messages[$choice->label]), "$file: missing choice {$choice->label}");
        }
    }
    foreach ($view as $child) { $inspect($child, $file); }
};
foreach (glob('src/Form/*.php') as $file) {
    $class = 'App\\Form\\'.basename($file, '.php');
    $form = $factory->create($class, null, ['data_class' => null]);
    $inspect($form->createView(), $file);
}
$parser = (new PhpParser\ParserFactory())->createForHostVersion();
$finder = new PhpParser\NodeFinder();
$literal = function ($node) use (&$literal): ?string {
    if ($node instanceof PhpParser\Node\Scalar\String_) { return $node->value; }
    if ($node instanceof PhpParser\Node\Expr\BinaryOp\Concat) {
        $left = $literal($node->left); $right = $literal($node->right);
        return $left === null || $right === null ? null : $left.$right;
    }
    return null;
};
$adminChecks = 0;
foreach (glob('src/Controller/Admin/*.php') as $file) {
    $nodes = $parser->parse(file_get_contents($file));
    $calls = $finder->find($nodes, static fn ($node) =>
        $node instanceof PhpParser\Node\Expr\StaticCall || $node instanceof PhpParser\Node\Expr\MethodCall
    );
    foreach ($calls as $call) {
        if (!$call->name instanceof PhpParser\Node\Identifier) { continue; }
        $name = $call->name->toString();
        $argument = null;
        if ($call instanceof PhpParser\Node\Expr\StaticCall && $call->class instanceof PhpParser\Node\Name) {
            if ($name === 'new' && preg_match('/(?:Field|Action)$/', $call->class->toString())) { $argument = $call->args[1]->value ?? null; }
            if ($name === 'addFieldset') { $argument = $call->args[0]->value ?? null; }
        } elseif ($name === 'setHelp') {
            $argument = $call->args[0]->value ?? null;
        } elseif ($name === 'setFormTypeOption' && in_array($literal($call->args[0]->value), ['label', 'help', 'placeholder'], true)) {
            $argument = $call->args[1]->value ?? null;
        }
        $branches = $argument instanceof PhpParser\Node\Expr\Ternary ? [$argument->if, $argument->else] : [$argument];
        foreach ($branches as $branch) {
            $key = $literal($branch);
            if ($key === null || $key === '' || $key === 'ID') { continue; }
            $inspectAssert(isset($messages[$key]), "$file: missing admin field/action/help $key");
            ++$adminChecks;
        }
    }
}
$assert($missing === [], implode("\n", array_unique($missing)));
foreach ($locales as $locale) {
    foreach (['messages', 'validators', 'security'] as $domain) {
        $catalogue = require "translations/$domain.$locale.php";
        $reference = require "translations/$domain.en.php";
        $assert(array_keys($catalogue) === array_keys($reference), "$domain: locale coverage differs for $locale");
        foreach ($catalogue as $key => $text) {
            $assert(is_string($text) && $text !== '', "$locale.$domain: empty translation for $key");
            preg_match_all('/%[a-z_]+%|\{\{ \w+ \}\}/', $text, $actual);
            preg_match_all('/%[a-z_]+%|\{\{ \w+ \}\}/', $reference[$key], $expected);
            sort($actual[0]); sort($expected[0]);
            $assert($actual[0] === $expected[0], "$locale.$domain: mismatched parameters for $key");
        }
    }
    $translator->setLocale($locale);
    foreach (glob('src/Form/*.php') as $file) {
        $class = 'App\\Form\\'.basename($file, '.php');
        $view = $factory->create($class, null, ['data_class' => null])->createView();
        $assert($renderer->searchAndRenderBlock($view, 'widget') !== '', "$locale: could not render $class");
    }
    $editor = EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField::new('notes')->getAsDto();
    $editor->setFieldFqcn(EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField::class);
    $entity = (new ReflectionClass(EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto::class))->newInstanceWithoutConstructor();
    $context = (new ReflectionClass(EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext::class))->newInstanceWithoutConstructor();
    $configurator = new App\Form\Configurator\TextEditorTranslationConfigurator($translator);
    $assert($configurator->supports($editor, $entity), 'Trix translation configurator does not support text editor fields.');
    $configurator->configure($editor, $entity, $context);
    $config = $editor->getCustomOption(EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField::OPTION_TRIX_EDITOR_CONFIG);
    $assert($config['lang']['bold'] === $translator->trans('editor.bold'), "$locale: Trix toolbar is not translated");
    $editor->setCustomOption(EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField::OPTION_TRIX_EDITOR_CONFIG, ['lang' => ['bold' => 'Custom label'], 'attachments' => ['preview' => false]]);
    $configurator->configure($editor, $entity, $context);
    $config = $editor->getCustomOption(EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField::OPTION_TRIX_EDITOR_CONFIG);
    $assert($config['lang']['bold'] === 'Custom label' && $config['attachments']['preview'] === false, "$locale: custom Trix configuration was overwritten");
    $form = $factory->create(App\Form\RegistrationFormType::class, null, ['data_class' => null]);
    $html = $renderer->searchAndRenderBlock($form->createView(), 'widget');
    $assert(str_contains($html, htmlspecialchars($translator->trans('First Name'), ENT_QUOTES)), "$locale: form label not translated");
    $template = $twig->render('booking/_delete_form.html.twig', ['booking' => ['id' => 123]]);
    preg_match('/onsubmit="([^"]+)"/', $template, $match);
    $handler = html_entity_decode($match[1], ENT_QUOTES);
    preg_match('/confirm\((.*)\);/', $handler, $argument);
    $assert(json_decode($argument[1], true, 512, JSON_THROW_ON_ERROR) === $translator->trans('Möchtest Du diese Buchung wirklich löschen?'), "$locale: unsafe confirmation encoding");
    $reset = $factory->create(App\Form\ChangePasswordFormType::class, null, ['data_class' => null]);
    $reset->submit(['plainPassword' => ['first' => 'abcdef', 'second' => 'different']]);
    $errors = iterator_to_array($reset->getErrors(true));
    $assert(count($errors) === 1 && $errors[0]->getMessage() === $translator->trans('The password fields must match.', [], 'validators'), "$locale: repeated-password validation was not translated");
    $form = $factory->create(App\Form\RegistrationFormType::class, null, ['data_class' => null]);
    $form->submit([]);
    $errors = array_map(static fn ($error) => $error->getMessage(), iterator_to_array($form->getErrors(true)));
    $assert(in_array($translator->trans('Please enter a password', [], 'validators'), $errors, true), "$locale: password validation was not translated");
    $assert(in_array($translator->trans('You should agree to our terms.', [], 'validators'), $errors, true), "$locale: terms validation was not translated");
    foreach (['templates/admin/course/schedule_planning.html.twig', 'templates/admin/calendar/index.html.twig'] as $file) {
        preg_match_all('/<script>\s*(.*?)\s*<\/script>/s', file_get_contents($file), $scripts);
        foreach ($scripts[1] as $script) {
            $javascript = $twig->createTemplate($script)->render();
            $process = proc_open(['node', '--check'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            $assert(is_resource($process), 'Could not start JavaScript syntax check.');
            fwrite($pipes[0], $javascript); fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $assert(proc_close($process) === 0, "$locale: invalid rendered script in $file: $output");
        }
    }
}
$packKeys = null;
foreach (['de', 'fr_FR', 'es'] as $locale) {
    $file = "public/js/tinymce/langs/$locale.js";
    $assert(preg_match('/^tinymce.addI18n\("([^"]+)", (\{.*\})\);\s*$/s', file_get_contents($file), $pack) === 1, "Invalid TinyMCE language pack $file");
    $assert($pack[1] === $locale, "Incorrect TinyMCE language code in $file");
    $process = proc_open([
        'node', '-e',
        'require("vm").runInNewContext(require("fs").readFileSync(process.argv[1], "utf8"), {tinymce: {addI18n: (locale, data) => process.stdout.write(JSON.stringify(data))}});',
        $file,
    ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    $assert(is_resource($process), "Could not validate $file");
    fclose($pipes[0]);
    $json = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $assert(proc_close($process) === 0, "$file: $error");
    $dictionary = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $keys = array_keys($dictionary);
    sort($keys);
    $assert($packKeys === null || $keys === $packKeys, "$file: missing editor translations");
    $assert(count(array_filter($dictionary, static fn ($value) => $value === '')) === 0, "$file: empty editor translation");
    $packKeys = $keys;
}
echo "PASS: all ".count(glob('src/Form/*.php'))." FormTypes; $checks field labels; $adminChecks admin labels/actions/help; four-locale catalogue/parameter parity; translated forms, validation and confirmation encoding; eight rendered planning/dialog JavaScript checks; Trix configuration and three complete 424-entry TinyMCE language packs.\n";
