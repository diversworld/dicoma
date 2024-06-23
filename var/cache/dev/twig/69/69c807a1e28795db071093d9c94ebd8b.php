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

/* student/_form.html.twig */
class __TwigTemplate_0cbd54091a5d4f5175b75250ba62ee1c extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "student/_form.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "student/_form.html.twig"));

        // line 1
        echo "
";
        // line 2
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 2, $this->source); })()), 'form_start');
        echo "
";
        // line 3
        if ($this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 3, $this->source); })()), 'errors')) {
            // line 4
            echo "    <div class=\"alert alert-danger\" role=\"alert\">
        ";
            // line 5
            echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 5, $this->source); })()), 'errors');
            echo "
    </div>
";
        }
        // line 8
        echo "<div class=\"row form-group\">
    <div class=\"col-md-4\">
        ";
        // line 10
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 10, $this->source); })()), "firstname", [], "any", false, false, false, 10), 'row');
        echo "
    </div>
    <div class=\"col-md-4\">
        ";
        // line 13
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 13, $this->source); })()), "lastname", [], "any", false, false, false, 13), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        ";
        // line 18
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 18, $this->source); })()), "birthday", [], "any", false, false, false, 18), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-5\">
        ";
        // line 23
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 23, $this->source); })()), "street", [], "any", false, false, false, 23), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        ";
        // line 28
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 28, $this->source); })()), "postal", [], "any", false, false, false, 28), 'row');
        echo "
    </div>
    <div class=\"col-md-6\">
        ";
        // line 31
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 31, $this->source); })()), "city", [], "any", false, false, false, 31), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-5\">
        ";
        // line 36
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 36, $this->source); })()), "email", [], "any", false, false, false, 36), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        ";
        // line 41
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 41, $this->source); })()), "phone", [], "any", false, false, false, 41), 'row');
        echo "
    </div>
    <div class=\"col-md-4\">
        ";
        // line 44
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 44, $this->source); })()), "mobile", [], "any", false, false, false, 44), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        ";
        // line 49
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 49, $this->source); })()), "category", [], "any", false, false, false, 49), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-8\">
        ";
        // line 54
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 54, $this->source); })()), "notes", [], "any", false, false, false, 54), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        ";
        // line 59
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 59, $this->source); })()), "username", [], "any", false, false, false, 59), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        ";
        // line 64
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 64, $this->source); })()), "published", [], "any", false, false, false, 64), 'row');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-6\">
        ";
        // line 69
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 69, $this->source); })()), "submit", [], "any", false, false, false, 69), 'row');
        echo "
    </div>
</div>
";
        // line 72
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 72, $this->source); })()), 'form_end');
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
        return "student/_form.html.twig";
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
        return array (  169 => 72,  163 => 69,  155 => 64,  147 => 59,  139 => 54,  131 => 49,  123 => 44,  117 => 41,  109 => 36,  101 => 31,  95 => 28,  87 => 23,  79 => 18,  71 => 13,  65 => 10,  61 => 8,  55 => 5,  52 => 4,  50 => 3,  46 => 2,  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("
{{ form_start(form) }}
{% if form_errors(form) %}
    <div class=\"alert alert-danger\" role=\"alert\">
        {{ form_errors(form) }}
    </div>
{% endif %}
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        {{ form_row(form.firstname) }}
    </div>
    <div class=\"col-md-4\">
        {{ form_row(form.lastname) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        {{ form_row(form.birthday) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-5\">
        {{ form_row(form.street) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        {{ form_row(form.postal) }}
    </div>
    <div class=\"col-md-6\">
        {{ form_row(form.city) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-5\">
        {{ form_row(form.email) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        {{ form_row(form.phone) }}
    </div>
    <div class=\"col-md-4\">
        {{ form_row(form.mobile) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        {{ form_row(form.category) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-8\">
        {{ form_row(form.notes) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        {{ form_row(form.username) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        {{ form_row(form.published) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-6\">
        {{ form_row(form.submit) }}
    </div>
</div>
{{ form_end(form) }}
", "student/_form.html.twig", "/shared/httpd/dicoma/templates/student/_form.html.twig");
    }
}
