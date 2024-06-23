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

/* tank_check/show.html.twig */
class __TwigTemplate_20d39bdcb24cc707596e0d7e17c51f40 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank_check/show.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank_check/show.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "tank_check/show.html.twig", 1);
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

        echo "TankCheck";
        
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
        <h1>TankCheck</h1>
        <div class=\"row text-bg-dark text-center\">
            <div class=\"col-md-12\">
                <ul class=\"list-inline\">
                    <li class=\"list-inline-item\">
                        <a href=\"";
        // line 13
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_index");
        echo "\" class=\"btn btn-transparent btn-solid-border\">zurück</a>
                    </li>
                    <li class=\"list-inline-item\">
                        <a href=\"";
        // line 16
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_edit", ["id" => twig_get_attribute($this->env, $this->source, (isset($context["tank_check"]) || array_key_exists("tank_check", $context) ? $context["tank_check"] : (function () { throw new RuntimeError('Variable "tank_check" does not exist.', 16, $this->source); })()), "id", [], "any", false, false, false, 16)]), "html", null, true);
        echo "\" class=\"btn btn-transparent btn-solid-border\">Bearbeiten</a>
                    </li>
                    <li class=\"list-inline-item\">
                        ";
        // line 19
        echo twig_include($this->env, $context, "tank_check/_delete_form.html.twig");
        echo "
                    </li>
                </ul>
            </div>
        </div>
        <table class=\"table\">
            <tbody>
                <tr>
                    <th>CheckDate</th>
                    <td>";
        // line 28
        ((twig_get_attribute($this->env, $this->source, (isset($context["tank_check"]) || array_key_exists("tank_check", $context) ? $context["tank_check"] : (function () { throw new RuntimeError('Variable "tank_check" does not exist.', 28, $this->source); })()), "checkDate", [], "any", false, false, false, 28)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank_check"]) || array_key_exists("tank_check", $context) ? $context["tank_check"] : (function () { throw new RuntimeError('Variable "tank_check" does not exist.', 28, $this->source); })()), "checkDate", [], "any", false, false, false, 28), "d.m.Y"), "html", null, true))) : (print ("")));
        echo "</td>
                </tr>
                <tr>
                    <th>VendorName</th>
                    <td>";
        // line 32
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank_check"]) || array_key_exists("tank_check", $context) ? $context["tank_check"] : (function () { throw new RuntimeError('Variable "tank_check" does not exist.', 32, $this->source); })()), "vendorName", [], "any", false, false, false, 32), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>Notes</th>
                    <td>";
        // line 36
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank_check"]) || array_key_exists("tank_check", $context) ? $context["tank_check"] : (function () { throw new RuntimeError('Variable "tank_check" does not exist.', 36, $this->source); })()), "notes", [], "any", false, false, false, 36), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>CostInformation</th>
                    <td>";
        // line 40
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["tank_check"]) || array_key_exists("tank_check", $context) ? $context["tank_check"] : (function () { throw new RuntimeError('Variable "tank_check" does not exist.', 40, $this->source); })()), "costInformation", [], "any", false, false, false, 40), "html", null, true);
        echo "</td>
                </tr>
            </tbody>
        </table>
        <div class=\"row\">
            <div class=\"col-md-3\">
                <div class=\"block\">
                    <a href=\"";
        // line 47
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_article_new", ["tank_check_id" => twig_get_attribute($this->env, $this->source, (isset($context["tank_check"]) || array_key_exists("tank_check", $context) ? $context["tank_check"] : (function () { throw new RuntimeError('Variable "tank_check" does not exist.', 47, $this->source); })()), "id", [], "any", false, false, false, 47)]), "html", null, true);
        echo "\" class=\"btn btn-transparent btn-solid-border\">Neuen Artikel anlegen</a>
                </div>
            </div>
            <table class=\"table\">
                <thead>
                <tr>
                    <th style=\"width: 380px\">Artikel</th>
                    <th >Notizen</th>
                    <th style=\"width: 100px\" class=\"text-right\">Preis (netto)</th>
                    <th style=\"width: 100px\" class=\"text-right\">Preis (rutto)</th>
                    <th style=\"width: 100px\"></th>
                </tr>
                </thead>
                <tbody>
                ";
        // line 61
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["tank_check_articles"]) || array_key_exists("tank_check_articles", $context) ? $context["tank_check_articles"] : (function () { throw new RuntimeError('Variable "tank_check_articles" does not exist.', 61, $this->source); })()));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["tank_check_article"]) {
            // line 62
            echo "                    <tr>
                        <td style=\"width:180px;\">";
            // line 63
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "title", [], "any", false, false, false, 63), "html", null, true);
            echo "</td>
                        <td>";
            // line 64
            echo twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "notes", [], "any", false, false, false, 64);
            echo "</td>
                        <td class=\"text-right\">";
            // line 65
            echo twig_escape_filter($this->env, twig_number_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "priceNetto", [], "any", false, false, false, 65), 2, ",", "."), "html", null, true);
            echo " €</td>
                        <td class=\"text-right\">";
            // line 66
            echo twig_escape_filter($this->env, twig_number_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "priceBrutto", [], "any", false, false, false, 66), 2, ",", "."), "html", null, true);
            echo " €</td>
                        <td>
                            <a href=\"";
            // line 68
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_article_show", ["id" => twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "id", [], "any", false, false, false, 68)]), "html", null, true);
            echo "\"><ion class=\"ion-eye\"></ion></a>&nbsp;&nbsp;|&nbsp;
                            <a href=\"";
            // line 69
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_article_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "id", [], "any", false, false, false, 69)]), "html", null, true);
            echo "\"><ion class=\"ion-edit\"></ion></a>
                        </td>
                    </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 73
            echo "                    <tr>
                        <td colspan=\"6\">no records found</td>
                    </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['tank_check_article'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 77
        echo "                </tbody>
            </table>
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
        return "tank_check/show.html.twig";
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
        return array (  216 => 77,  207 => 73,  198 => 69,  194 => 68,  189 => 66,  185 => 65,  181 => 64,  177 => 63,  174 => 62,  169 => 61,  152 => 47,  142 => 40,  135 => 36,  128 => 32,  121 => 28,  109 => 19,  103 => 16,  97 => 13,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}TankCheck{% endblock %}

{% block body %}
<section class=\"portfolio-work section\">
    <div class=\"container\">
        <h1>TankCheck</h1>
        <div class=\"row text-bg-dark text-center\">
            <div class=\"col-md-12\">
                <ul class=\"list-inline\">
                    <li class=\"list-inline-item\">
                        <a href=\"{{ path('app_tank_check_index') }}\" class=\"btn btn-transparent btn-solid-border\">zurück</a>
                    </li>
                    <li class=\"list-inline-item\">
                        <a href=\"{{ path('app_tank_check_edit', {'id': tank_check.id}) }}\" class=\"btn btn-transparent btn-solid-border\">Bearbeiten</a>
                    </li>
                    <li class=\"list-inline-item\">
                        {{ include('tank_check/_delete_form.html.twig') }}
                    </li>
                </ul>
            </div>
        </div>
        <table class=\"table\">
            <tbody>
                <tr>
                    <th>CheckDate</th>
                    <td>{{ tank_check.checkDate ? tank_check.checkDate|date('d.m.Y') : '' }}</td>
                </tr>
                <tr>
                    <th>VendorName</th>
                    <td>{{ tank_check.vendorName }}</td>
                </tr>
                <tr>
                    <th>Notes</th>
                    <td>{{ tank_check.notes }}</td>
                </tr>
                <tr>
                    <th>CostInformation</th>
                    <td>{{ tank_check.costInformation }}</td>
                </tr>
            </tbody>
        </table>
        <div class=\"row\">
            <div class=\"col-md-3\">
                <div class=\"block\">
                    <a href=\"{{ path('app_tank_check_article_new', {'tank_check_id': tank_check.id}) }}\" class=\"btn btn-transparent btn-solid-border\">Neuen Artikel anlegen</a>
                </div>
            </div>
            <table class=\"table\">
                <thead>
                <tr>
                    <th style=\"width: 380px\">Artikel</th>
                    <th >Notizen</th>
                    <th style=\"width: 100px\" class=\"text-right\">Preis (netto)</th>
                    <th style=\"width: 100px\" class=\"text-right\">Preis (rutto)</th>
                    <th style=\"width: 100px\"></th>
                </tr>
                </thead>
                <tbody>
                {% for tank_check_article in tank_check_articles %}
                    <tr>
                        <td style=\"width:180px;\">{{ tank_check_article.title }}</td>
                        <td>{{ tank_check_article.notes | raw }}</td>
                        <td class=\"text-right\">{{ tank_check_article.priceNetto | number_format(2, ',', '.') }} €</td>
                        <td class=\"text-right\">{{ tank_check_article.priceBrutto | number_format(2, ',', '.') }} €</td>
                        <td>
                            <a href=\"{{ path('app_tank_check_article_show', {'id': tank_check_article.id}) }}\"><ion class=\"ion-eye\"></ion></a>&nbsp;&nbsp;|&nbsp;
                            <a href=\"{{ path('app_tank_check_article_edit', {'id': tank_check_article.id}) }}\"><ion class=\"ion-edit\"></ion></a>
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
    </div>
</section>
{% endblock %}
", "tank_check/show.html.twig", "/shared/httpd/dicoma/templates/tank_check/show.html.twig");
    }
}
