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

/* tank_check/index.html.twig */
class __TwigTemplate_3dfb919834227525a71a2e284347c2f7 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank_check/index.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank_check/index.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "tank_check/index.html.twig", 1);
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

        echo "TankCheck index";
        
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
        echo "<section class=\"portfolio-work\">
    <div class=\"container\">
        <h1>Übersicht Prüfungen</h1>
        <div class=\"col-md-3\">
            <div class=\"block\">
                <a href=\"";
        // line 11
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_new");
        echo "\" class=\"btn btn-transparent btn-solid-border\">Neu anlegen</a>
            </div>
        </div>

    <table class=\"table\">
        <thead>
            <tr>
                <th>CheckDate</th>
                <th>VendorName</th>
                <th>Notes</th>
                <th>CostInformation</th>
                <th>actions</th>
            </tr>
        </thead>
        <tbody>
        ";
        // line 26
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["tank_checks"]) || array_key_exists("tank_checks", $context) ? $context["tank_checks"] : (function () { throw new RuntimeError('Variable "tank_checks" does not exist.', 26, $this->source); })()));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["tank_check"]) {
            // line 27
            echo "            <tr>
                <td>";
            // line 28
            ((twig_get_attribute($this->env, $this->source, $context["tank_check"], "checkDate", [], "any", false, false, false, 28)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank_check"], "checkDate", [], "any", false, false, false, 28), "d.m.Y"), "html", null, true))) : (print ("")));
            echo "</td>
                <td>";
            // line 29
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank_check"], "vendorName", [], "any", false, false, false, 29), "html", null, true);
            echo "</td>
                <td>";
            // line 30
            echo twig_get_attribute($this->env, $this->source, $context["tank_check"], "notes", [], "any", false, false, false, 30);
            echo "</td>
                <td>";
            // line 31
            echo twig_get_attribute($this->env, $this->source, $context["tank_check"], "costInformation", [], "any", false, false, false, 31);
            echo "</td>
                <td>
                    <a href=\"";
            // line 33
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_show", ["id" => twig_get_attribute($this->env, $this->source, $context["tank_check"], "id", [], "any", false, false, false, 33)]), "html", null, true);
            echo "\">show</a>
                    <a href=\"";
            // line 34
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["tank_check"], "id", [], "any", false, false, false, 34)]), "html", null, true);
            echo "\">edit</a>
                </td>
            </tr>
        ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 38
            echo "            <tr>
                <td colspan=\"6\">no records found</td>
            </tr>
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['tank_check'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 42
        echo "        </tbody>
    </table>
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
        return "tank_check/index.html.twig";
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
        return array (  160 => 42,  151 => 38,  142 => 34,  138 => 33,  133 => 31,  129 => 30,  125 => 29,  121 => 28,  118 => 27,  113 => 26,  95 => 11,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}TankCheck index{% endblock %}

{% block body %}
<section class=\"portfolio-work\">
    <div class=\"container\">
        <h1>Übersicht Prüfungen</h1>
        <div class=\"col-md-3\">
            <div class=\"block\">
                <a href=\"{{ path('app_tank_check_new') }}\" class=\"btn btn-transparent btn-solid-border\">Neu anlegen</a>
            </div>
        </div>

    <table class=\"table\">
        <thead>
            <tr>
                <th>CheckDate</th>
                <th>VendorName</th>
                <th>Notes</th>
                <th>CostInformation</th>
                <th>actions</th>
            </tr>
        </thead>
        <tbody>
        {% for tank_check in tank_checks %}
            <tr>
                <td>{{ tank_check.checkDate ? tank_check.checkDate|date('d.m.Y') : '' }}</td>
                <td>{{ tank_check.vendorName }}</td>
                <td>{{ tank_check.notes | raw }}</td>
                <td>{{ tank_check.costInformation | raw}}</td>
                <td>
                    <a href=\"{{ path('app_tank_check_show', {'id': tank_check.id}) }}\">show</a>
                    <a href=\"{{ path('app_tank_check_edit', {'id': tank_check.id}) }}\">edit</a>
                </td>
            </tr>
        {% else %}
            <tr>
                <td colspan=\"6\">no records found</td>
            </tr>
        {% endfor %}
        </tbody>
    </table>
    </div>
</section>

{% endblock %}
", "tank_check/index.html.twig", "/shared/httpd/dicoma/templates/tank_check/index.html.twig");
    }
}
