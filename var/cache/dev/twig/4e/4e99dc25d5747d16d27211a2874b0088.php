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

/* vendor/_form.html.twig */
class __TwigTemplate_65f54d4e6430aac2fcf538296f8d09ca extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "vendor/_form.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "vendor/_form.html.twig"));

        // line 2
        echo "
";
        // line 3
        if ($this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 3, $this->source); })()), 'errors')) {
            // line 4
            echo "    <div class=\"alert alert-danger\">
        ";
            // line 5
            echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 5, $this->source); })()), 'errors');
            echo "
    </div>
";
        }
        // line 8
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 8, $this->source); })()), 'form_start', ["method" => "POST"]);
        echo "
<div class=\"row form-group\">
    <div class=\"col-md-6\">
        <div class=\"block\">
            ";
        // line 12
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 12, $this->source); })()), "name", [], "any", false, false, false, 12), 'row');
        echo "
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-5\">
        <div class=\"block\">
            ";
        // line 19
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 19, $this->source); })()), "street", [], "any", false, false, false, 19), 'row');
        echo "
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        <div class=\"block\">
            ";
        // line 26
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 26, $this->source); })()), "postal", [], "any", false, false, false, 26), 'row');
        echo "
        </div>
    </div>
    <div class=\"col-md-4\">
        <div class=\"block\">
            ";
        // line 31
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 31, $this->source); })()), "city", [], "any", false, false, false, 31), 'row');
        echo "
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        <div class=\"block\">
            ";
        // line 38
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 38, $this->source); })()), "email", [], "any", false, false, false, 38), 'row');
        echo "
        </div>
    </div>
    <div class=\"col-md-3\">
        <div class=\"block\">
            ";
        // line 43
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 43, $this->source); })()), "phone", [], "any", false, false, false, 43), 'row');
        echo "
        </div>
    </div>
    <div class=\"col-md-5\">
        <div class=\"block\">
            ";
        // line 48
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 48, $this->source); })()), "website", [], "any", false, false, false, 48), 'row');
        echo "
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-12\">
        <div class=\"block\">
            ";
        // line 55
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 55, $this->source); })()), "notes", [], "any", false, false, false, 55), 'row');
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
        // line 64
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 64, $this->source); })()), "submit", [], "any", false, false, false, 64), 'row');
        echo "
        </div>
    </div>
</div>
";
        // line 68
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 68, $this->source); })()), 'form_end');
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
        return "vendor/_form.html.twig";
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
        return array (  147 => 68,  140 => 64,  128 => 55,  118 => 48,  110 => 43,  102 => 38,  92 => 31,  84 => 26,  74 => 19,  64 => 12,  57 => 8,  51 => 5,  48 => 4,  46 => 3,  43 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("{# vendor/_form.html.twig #}

{% if form_errors(form) %}
    <div class=\"alert alert-danger\">
        {{ form_errors(form) }}
    </div>
{% endif %}
{{ form_start(form, {'method': 'POST'}) }}
<div class=\"row form-group\">
    <div class=\"col-md-6\">
        <div class=\"block\">
            {{ form_row(form.name) }}
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-5\">
        <div class=\"block\">
            {{ form_row(form.street) }}
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        <div class=\"block\">
            {{ form_row(form.postal) }}
        </div>
    </div>
    <div class=\"col-md-4\">
        <div class=\"block\">
            {{ form_row(form.city) }}
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-3\">
        <div class=\"block\">
            {{ form_row(form.email) }}
        </div>
    </div>
    <div class=\"col-md-3\">
        <div class=\"block\">
            {{ form_row(form.phone) }}
        </div>
    </div>
    <div class=\"col-md-5\">
        <div class=\"block\">
            {{ form_row(form.website) }}
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

", "vendor/_form.html.twig", "/shared/httpd/dicoma/templates/vendor/_form.html.twig");
    }
}
