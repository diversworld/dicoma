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

/* tank/index.html.twig */
class __TwigTemplate_97da904a44300f7fed5cca1fc6040b8c extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank/index.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank/index.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "tank/index.html.twig", 1);
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

        echo "Tank index";
        
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
        echo "<section class=\"portfolio-work section\">
    <div class=\"container\">
        <h1>Tank index</h1>
        <div class=\"col-md-3\">
            <div class=\"block\">
                <a href=\"";
        // line 11
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_new");
        echo "\" class=\"btn btn-transparent btn-solid-border\">";
        echo $this->env->getExtension('Symfony\Bridge\Twig\Extension\TranslationExtension')->trans("Create new", [], "messages");
        echo "</a>
            </div>
        </div>
        <table class=\"table\">
            <thead>
                <tr>
                    <th>Inventory</th>
                    <th>Serielnumber</th>
                    <th>Size</th>
                    <th>BuyDate</th>
                    <th>LastCheckDate</th>
                    <th>NextCheckDate</th>
                    <th>Notes</th>
                    <th>OxigenClean</th>
                    <th>actions</th>
                </tr>
            </thead>
            <tbody>
            ";
        // line 29
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["tanks"]) || array_key_exists("tanks", $context) ? $context["tanks"] : (function () { throw new RuntimeError('Variable "tanks" does not exist.', 29, $this->source); })()));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["tank"]) {
            // line 30
            echo "                <tr>
                    <td>";
            // line 31
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank"], "inventory", [], "any", false, false, false, 31), "html", null, true);
            echo "</td>
                    <td>";
            // line 32
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank"], "serialnumber", [], "any", false, false, false, 32), "html", null, true);
            echo "</td>
                    <td>";
            // line 33
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank"], "size", [], "any", false, false, false, 33), "html", null, true);
            echo "</td>
                    <td>";
            // line 34
            ((twig_get_attribute($this->env, $this->source, $context["tank"], "buyDate", [], "any", false, false, false, 34)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank"], "buyDate", [], "any", false, false, false, 34), "Y-m-d"), "html", null, true))) : (print ("")));
            echo "</td>
                    <td>";
            // line 35
            ((twig_get_attribute($this->env, $this->source, $context["tank"], "lastCheckDate", [], "any", false, false, false, 35)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank"], "lastCheckDate", [], "any", false, false, false, 35), "d.m.Y"), "html", null, true))) : (print ("")));
            echo "</td>
                    <td>";
            // line 36
            ((twig_get_attribute($this->env, $this->source, $context["tank"], "nextCheckDate", [], "any", false, false, false, 36)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank"], "nextCheckDate", [], "any", false, false, false, 36), "d.m.Y"), "html", null, true))) : (print ("")));
            echo "</td>
                    <td>";
            // line 37
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank"], "notes", [], "any", false, false, false, 37), "html", null, true);
            echo "</td>
                    <td>";
            // line 38
            echo ((twig_get_attribute($this->env, $this->source, $context["tank"], "oxigenClean", [], "any", false, false, false, 38)) ? ("Yes") : ("No"));
            echo "</td>
                    <td>
                        <a href=\"";
            // line 40
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_show", ["id" => twig_get_attribute($this->env, $this->source, $context["tank"], "id", [], "any", false, false, false, 40)]), "html", null, true);
            echo "\">show</a>
                        <a href=\"";
            // line 41
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["tank"], "id", [], "any", false, false, false, 41)]), "html", null, true);
            echo "\">edit</a>
                    </td>
                </tr>
            ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 45
            echo "                <tr>
                    <td colspan=\"10\">no records found</td>
                </tr>
            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['tank'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 49
        echo "            </tbody>
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
        return "tank/index.html.twig";
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
        return array (  181 => 49,  172 => 45,  163 => 41,  159 => 40,  154 => 38,  150 => 37,  146 => 36,  142 => 35,  138 => 34,  134 => 33,  130 => 32,  126 => 31,  123 => 30,  118 => 29,  95 => 11,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Tank index{% endblock %}

{% block body %}
<section class=\"portfolio-work section\">
    <div class=\"container\">
        <h1>Tank index</h1>
        <div class=\"col-md-3\">
            <div class=\"block\">
                <a href=\"{{ path('app_tank_new') }}\" class=\"btn btn-transparent btn-solid-border\">{% trans %}Create new{% endtrans %}</a>
            </div>
        </div>
        <table class=\"table\">
            <thead>
                <tr>
                    <th>Inventory</th>
                    <th>Serielnumber</th>
                    <th>Size</th>
                    <th>BuyDate</th>
                    <th>LastCheckDate</th>
                    <th>NextCheckDate</th>
                    <th>Notes</th>
                    <th>OxigenClean</th>
                    <th>actions</th>
                </tr>
            </thead>
            <tbody>
            {% for tank in tanks %}
                <tr>
                    <td>{{ tank.inventory }}</td>
                    <td>{{ tank.serialnumber }}</td>
                    <td>{{ tank.size }}</td>
                    <td>{{ tank.buyDate ? tank.buyDate|date('Y-m-d') : '' }}</td>
                    <td>{{ tank.lastCheckDate ? tank.lastCheckDate|date('d.m.Y') : '' }}</td>
                    <td>{{ tank.nextCheckDate ? tank.nextCheckDate|date('d.m.Y') : '' }}</td>
                    <td>{{ tank.notes }}</td>
                    <td>{{ tank.oxigenClean ? 'Yes' : 'No' }}</td>
                    <td>
                        <a href=\"{{ path('app_tank_show', {'id': tank.id}) }}\">show</a>
                        <a href=\"{{ path('app_tank_edit', {'id': tank.id}) }}\">edit</a>
                    </td>
                </tr>
            {% else %}
                <tr>
                    <td colspan=\"10\">no records found</td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
    </div>
</section>
{% endblock %}
", "tank/index.html.twig", "/shared/httpd/dicoma/templates/tank/index.html.twig");
    }
}
