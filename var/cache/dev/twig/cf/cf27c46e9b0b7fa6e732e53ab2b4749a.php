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

/* vendor/show.html.twig */
class __TwigTemplate_6cd2c4b6a6d1f2993c100a375bfa6c78 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'title' => [$this, 'block_title'],
            'body' => [$this, 'block_body'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return "base.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "vendor/show.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "vendor/show.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "vendor/show.html.twig", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    // line 3
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "title"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "title"));

        echo "Vendor";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 5
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "body"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "body"));

        // line 6
        echo "<section class=\"about section\">
    <div class=\"container\">
    <h1>Vendor</h1>
        <div class=\"row text-bg-dark text-center\">
            <div class=\"col-md-12\">
                <ul class=\"list-inline\">
                    <li class=\"list-inline-item\">
                        <a href=\"";
        // line 13
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_vendor_index");
        echo "\" class=\"btn btn-transparent btn-solid-border\">zurück</a>
                    </li>
                    <li class=\"list-inline-item\">
                        <a href=\"";
        // line 16
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_vendor_edit", ["id" => twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 16, $this->source); })()), "id", [], "any", false, false, false, 16)]), "html", null, true);
        echo "\" class=\"btn btn-transparent btn-solid-border\">Bearbeiten</a>
                    </li>
                    <li class=\"list-inline-item\">
                        ";
        // line 19
        echo twig_include($this->env, $context, "vendor/_delete_form.html.twig");
        echo "
                    </li>
                </ul>
            </div>
        </div>
        <div class=\"row\">
            &nbsp;
        </div>
        <div class=\"card mb-12\">
            <div class=\"row g-0\">
                <div class=\"col-md-9\">
                    <div class=\"card-header\">";
        // line 30
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 30, $this->source); })()), "name", [], "any", false, false, false, 30), "html", null, true);
        echo "</div>
                    <div class=\"card-body\">
                        <h5 class=\"card-title\">Firma</h5>
                        <p class=\"card-text\">";
        // line 33
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 33, $this->source); })()), "name", [], "any", false, false, false, 33), "html", null, true);
        echo "</p>
                        <h5 class=\"card-title\">Adresse</h5>
                        <p class=\"card-text\">";
        // line 35
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 35, $this->source); })()), "street", [], "any", false, false, false, 35), "html", null, true);
        echo "</p>
                        <p class=\"card-text\">";
        // line 36
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 36, $this->source); })()), "postal", [], "any", false, false, false, 36), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 36, $this->source); })()), "city", [], "any", false, false, false, 36), "html", null, true);
        echo "</p>
                        <h5 class=\"card-title\">Kontakt</h5>
                        <p class=\"card-text\">";
        // line 38
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 38, $this->source); })()), "email", [], "any", false, false, false, 38), "html", null, true);
        echo "</p>
                        <p class=\"card-text\">";
        // line 39
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 39, $this->source); })()), "phone", [], "any", false, false, false, 39), "html", null, true);
        echo "</p>
                        <p class=\"card-text\">";
        // line 40
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 40, $this->source); })()), "website", [], "any", false, false, false, 40), "html", null, true);
        echo "</p>
                        <h5 class=\"card-title\">Bemerkungen</h5>
                        <p class=\"card-text\">";
        // line 42
        echo twig_get_attribute($this->env, $this->source, (isset($context["vendor"]) || array_key_exists("vendor", $context) ? $context["vendor"] : (function () { throw new RuntimeError('Variable "vendor" does not exist.', 42, $this->source); })()), "notes", [], "any", false, false, false, 42);
        echo "</p>

                    </div>
                </div>
            </div>
        </div>
        <div class=\"row\">
            &nbsp;
        </div>
    </div>
</section>
";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "vendor/show.html.twig";
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
        return array (  158 => 42,  153 => 40,  149 => 39,  145 => 38,  138 => 36,  134 => 35,  129 => 33,  123 => 30,  109 => 19,  103 => 16,  97 => 13,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Vendor{% endblock %}

{% block body %}
<section class=\"about section\">
    <div class=\"container\">
    <h1>Vendor</h1>
        <div class=\"row text-bg-dark text-center\">
            <div class=\"col-md-12\">
                <ul class=\"list-inline\">
                    <li class=\"list-inline-item\">
                        <a href=\"{{ path('app_vendor_index') }}\" class=\"btn btn-transparent btn-solid-border\">zurück</a>
                    </li>
                    <li class=\"list-inline-item\">
                        <a href=\"{{ path('app_vendor_edit', {'id': vendor.id}) }}\" class=\"btn btn-transparent btn-solid-border\">Bearbeiten</a>
                    </li>
                    <li class=\"list-inline-item\">
                        {{ include('vendor/_delete_form.html.twig') }}
                    </li>
                </ul>
            </div>
        </div>
        <div class=\"row\">
            &nbsp;
        </div>
        <div class=\"card mb-12\">
            <div class=\"row g-0\">
                <div class=\"col-md-9\">
                    <div class=\"card-header\">{{ vendor.name }}</div>
                    <div class=\"card-body\">
                        <h5 class=\"card-title\">Firma</h5>
                        <p class=\"card-text\">{{ vendor.name }}</p>
                        <h5 class=\"card-title\">Adresse</h5>
                        <p class=\"card-text\">{{ vendor.street }}</p>
                        <p class=\"card-text\">{{ vendor.postal }} {{ vendor.city }}</p>
                        <h5 class=\"card-title\">Kontakt</h5>
                        <p class=\"card-text\">{{ vendor.email }}</p>
                        <p class=\"card-text\">{{ vendor.phone }}</p>
                        <p class=\"card-text\">{{ vendor.website }}</p>
                        <h5 class=\"card-title\">Bemerkungen</h5>
                        <p class=\"card-text\">{{ vendor.notes|raw }}</p>

                    </div>
                </div>
            </div>
        </div>
        <div class=\"row\">
            &nbsp;
        </div>
    </div>
</section>
{% endblock %}
", "vendor/show.html.twig", "/shared/httpd/dicoma/templates/vendor/show.html.twig");
    }
}
