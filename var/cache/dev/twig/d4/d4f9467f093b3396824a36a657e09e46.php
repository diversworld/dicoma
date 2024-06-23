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

/* tank/show.html.twig */
class __TwigTemplate_7d0ce791e2b5efc02897700ec7e9edef extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank/show.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank/show.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "tank/show.html.twig", 1);
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

        echo "Tank";
        
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
        echo "    <h1>Tank</h1>

    <table class=\"table\">
        <tbody>
            <tr>
                <th>Inventory</th>
                <td>";
        // line 12
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 12, $this->source); })()), "inventory", [], "any", false, false, false, 12), "html", null, true);
        echo "</td>
            </tr>
            <tr>
                <th>Serialnumber</th>
                <td>";
        // line 16
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 16, $this->source); })()), "serialnumber", [], "any", false, false, false, 16), "html", null, true);
        echo "</td>
            </tr>
            <tr>
                <th>Size</th>
                <td>";
        // line 20
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 20, $this->source); })()), "size", [], "any", false, false, false, 20), "html", null, true);
        echo "</td>
            </tr>
            <tr>
                <th>BuyDate</th>
                <td>";
        // line 24
        ((twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 24, $this->source); })()), "buyDate", [], "any", false, false, false, 24)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 24, $this->source); })()), "buyDate", [], "any", false, false, false, 24), "d.m.Y"), "html", null, true))) : (print ("")));
        echo "</td>
            </tr>
            <tr>
                <th>LastCheckDate</th>
                <td>";
        // line 28
        ((twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 28, $this->source); })()), "lastCheckDate", [], "any", false, false, false, 28)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 28, $this->source); })()), "lastCheckDate", [], "any", false, false, false, 28), "d.m.Y"), "html", null, true))) : (print ("")));
        echo "</td>
            </tr>
            <tr>
                <th>NextCheckDate</th>
                <td>";
        // line 32
        ((twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 32, $this->source); })()), "nextCheckDate", [], "any", false, false, false, 32)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 32, $this->source); })()), "nextCheckDate", [], "any", false, false, false, 32), "d.m.Y"), "html", null, true))) : (print ("")));
        echo "</td>
            </tr>
            <tr>
                <th>Notes</th>
                <td>";
        // line 36
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 36, $this->source); })()), "notes", [], "any", false, false, false, 36), "html", null, true);
        echo "</td>
            </tr>
            <tr>
                <th>OxigenClean</th>
                <td>";
        // line 40
        echo ((twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 40, $this->source); })()), "oxigenClean", [], "any", false, false, false, 40)) ? ("Yes") : ("No"));
        echo "</td>
            </tr>
        </tbody>
    </table>

    <a href=\"";
        // line 45
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_index");
        echo "\">back to list</a>

    <a href=\"";
        // line 47
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_edit", ["id" => twig_get_attribute($this->env, $this->source, (isset($context["tank"]) || array_key_exists("tank", $context) ? $context["tank"] : (function () { throw new RuntimeError('Variable "tank" does not exist.', 47, $this->source); })()), "id", [], "any", false, false, false, 47)]), "html", null, true);
        echo "\">edit</a>

    ";
        // line 49
        echo twig_include($this->env, $context, "tank/_delete_form.html.twig");
        echo "
";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "tank/show.html.twig";
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
        return array (  163 => 49,  158 => 47,  153 => 45,  145 => 40,  138 => 36,  131 => 32,  124 => 28,  117 => 24,  110 => 20,  103 => 16,  96 => 12,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Tank{% endblock %}

{% block body %}
    <h1>Tank</h1>

    <table class=\"table\">
        <tbody>
            <tr>
                <th>Inventory</th>
                <td>{{ tank.inventory }}</td>
            </tr>
            <tr>
                <th>Serialnumber</th>
                <td>{{ tank.serialnumber }}</td>
            </tr>
            <tr>
                <th>Size</th>
                <td>{{ tank.size }}</td>
            </tr>
            <tr>
                <th>BuyDate</th>
                <td>{{ tank.buyDate ? tank.buyDate|date('d.m.Y') : '' }}</td>
            </tr>
            <tr>
                <th>LastCheckDate</th>
                <td>{{ tank.lastCheckDate ? tank.lastCheckDate|date('d.m.Y') : '' }}</td>
            </tr>
            <tr>
                <th>NextCheckDate</th>
                <td>{{ tank.nextCheckDate ? tank.nextCheckDate|date('d.m.Y') : '' }}</td>
            </tr>
            <tr>
                <th>Notes</th>
                <td>{{ tank.notes }}</td>
            </tr>
            <tr>
                <th>OxigenClean</th>
                <td>{{ tank.oxigenClean ? 'Yes' : 'No' }}</td>
            </tr>
        </tbody>
    </table>

    <a href=\"{{ path('app_tank_index') }}\">back to list</a>

    <a href=\"{{ path('app_tank_edit', {'id': tank.id}) }}\">edit</a>

    {{ include('tank/_delete_form.html.twig') }}
{% endblock %}
", "tank/show.html.twig", "/shared/httpd/dicoma/templates/tank/show.html.twig");
    }
}
