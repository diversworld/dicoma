<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* courses/_form.html.twig */
class __TwigTemplate_73995476da4a4263debf603e4874a689 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "courses/_form.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "courses/_form.html.twig"));

        // line 2
        if ($this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 2, $this->source); })()), 'errors')) {
            // line 3
            echo "    <div class=\"alert alert-danger\">
        ";
            // line 4
            echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 4, $this->source); })()), 'errors');
            echo "
    </div>
";
        }
        // line 7
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 7, $this->source); })()), 'form_start', ["method" => "POST"]);
        echo "
    <div class=\"row form-group\">
        <div class=\"col-md-6\">
            <div class=\"block\">
                ";
        // line 11
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 11, $this->source); })()), "title", [], "any", false, false, false, 11), 'row');
        echo "
            </div>
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-4\">
            ";
        // line 17
        if (twig_get_attribute($this->env, $this->source, (isset($context["course"]) || array_key_exists("course", $context) ? $context["course"] : (function () { throw new RuntimeError('Variable "course" does not exist.', 17, $this->source); })()), "image", [], "any", false, false, false, 17)) {
            // line 18
            echo "                <img src=\"";
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl((("images/kurse/" . "/") . twig_get_attribute($this->env, $this->source, (isset($context["course"]) || array_key_exists("course", $context) ? $context["course"] : (function () { throw new RuntimeError('Variable "course" does not exist.', 18, $this->source); })()), "image", [], "any", false, false, false, 18))), "html", null, true);
            echo "\" width=\"200px\" alt=\"Brevetbild\">
            ";
        }
        // line 20
        echo "        </div>
        <div class=\"col-md-5\">
            ";
        // line 22
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 22, $this->source); })()), "image", [], "any", false, false, false, 22), 'label');
        echo "
            ";
        // line 23
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 23, $this->source); })()), "image", [], "any", false, false, false, 23), 'widget');
        echo "
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-4\">
            <div class=\"block\">
                ";
        // line 29
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 29, $this->source); })()), "category", [], "any", false, false, false, 29), 'row');
        echo "
            </div>
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-12\">
            <div class=\"block\">
                ";
        // line 36
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 36, $this->source); })()), "description", [], "any", false, false, false, 36), 'row');
        echo "
            </div>
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-12\">
            <div class=\"block\">
                ";
        // line 43
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 43, $this->source); })()), "requirements", [], "any", false, false, false, 43), 'row');
        echo "
            </div>
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-12\">
            <div class=\"block\">
                ";
        // line 50
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 50, $this->source); })()), "notes", [], "any", false, false, false, 50), 'row');
        echo "
            </div>
        </div>
    </div>
    <div class=\"row form-group\">&nbsp;
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-6\">
            <div class=\"block\">
                ";
        // line 59
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 59, $this->source); })()), "submit", [], "any", false, false, false, 59), 'row');
        echo "
            </div>
        </div>
    </div>
";
        // line 63
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 63, $this->source); })()), 'form_end');
        echo "

";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "courses/_form.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable()
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo()
    {
        return array (  144 => 63,  137 => 59,  125 => 50,  115 => 43,  105 => 36,  95 => 29,  86 => 23,  82 => 22,  78 => 20,  72 => 18,  70 => 17,  61 => 11,  54 => 7,  48 => 4,  45 => 3,  43 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("{# template/courses/_form.html.twig #}
{% if form_errors(form) %}
    <div class=\"alert alert-danger\">
        {{ form_errors(form) }}
    </div>
{% endif %}
{{ form_start(form, {'method': 'POST'}) }}
    <div class=\"row form-group\">
        <div class=\"col-md-6\">
            <div class=\"block\">
                {{ form_row(form.title) }}
            </div>
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-4\">
            {% if course.image %}
                <img src=\"{{ asset('images/kurse/'  ~ '/' ~ course.image) }}\" width=\"200px\" alt=\"Brevetbild\">
            {% endif %}
        </div>
        <div class=\"col-md-5\">
            {{ form_label(form.image) }}
            {{ form_widget(form.image) }}
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-4\">
            <div class=\"block\">
                {{ form_row(form.category) }}
            </div>
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-12\">
            <div class=\"block\">
                {{ form_row(form.description) }}
            </div>
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-12\">
            <div class=\"block\">
                {{ form_row(form.requirements) }}
            </div>
        </div>
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-12\">
            <div class=\"block\">
                {{ form_row(form.notes) }}
            </div>
        </div>
    </div>
    <div class=\"row form-group\">&nbsp;
    </div>
    <div class=\"row form-group\">
        <div class=\"col-md-6\">
            <div class=\"block\">
                {{ form_row(form.submit) }}
            </div>
        </div>
    </div>
{{ form_end(form) }}

", "courses/_form.html.twig", "/shared/httpd/dicoma/templates/courses/_form.html.twig");
    }
}
