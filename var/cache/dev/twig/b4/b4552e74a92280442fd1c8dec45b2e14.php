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

/* tank_check_article/index.html.twig */
class __TwigTemplate_306ecb4d8b786422db976a7e25af6721 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank_check_article/index.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "tank_check_article/index.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "tank_check_article/index.html.twig", 1);
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

        echo "TankCheckArticle index";
        
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
        <h1>Preise für TÜV Prüfung</h1>
        <div class=\"col-md-3\">
            <div class=\"block\">
                <a href=\"";
        // line 11
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_article_new");
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
        // line 26
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["tank_check_articles"]) || array_key_exists("tank_check_articles", $context) ? $context["tank_check_articles"] : (function () { throw new RuntimeError('Variable "tank_check_articles" does not exist.', 26, $this->source); })()));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["tank_check_article"]) {
            // line 27
            echo "                <tr>
                    <td width=\"180\">";
            // line 28
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "title", [], "any", false, false, false, 28), "html", null, true);
            echo "</td>
                    <td>";
            // line 29
            echo twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "notes", [], "any", false, false, false, 29);
            echo "</td>
                    <td class=\"text-right\">";
            // line 30
            echo twig_escape_filter($this->env, twig_number_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "priceNetto", [], "any", false, false, false, 30), 2, ",", "."), "html", null, true);
            echo " €</td>
                    <td class=\"text-right\">";
            // line 31
            echo twig_escape_filter($this->env, twig_number_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "priceBrutto", [], "any", false, false, false, 31), 2, ",", "."), "html", null, true);
            echo " €</td>
                    <td>
                        <a href=\"";
            // line 33
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_article_show", ["id" => twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "id", [], "any", false, false, false, 33)]), "html", null, true);
            echo "\"><ion class=\"ion-eye\"></ion></a>&nbsp;&nbsp;|&nbsp;
                        <a href=\"";
            // line 34
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_article_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["tank_check_article"], "id", [], "any", false, false, false, 34)]), "html", null, true);
            echo "\"><ion class=\"ion-edit\"></ion></a>
                    </td>
                </tr>
            ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 38
            echo "                <tr>
                    <td colspan=\"6\">no records found</td>
                </tr>
            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['tank_check_article'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 42
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
        return "tank_check_article/index.html.twig";
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

{% block title %}TankCheckArticle index{% endblock %}

{% block body %}
<section class=\"portfolio-work\">
    <div class=\"container\">
        <h1>Preise für TÜV Prüfung</h1>
        <div class=\"col-md-3\">
            <div class=\"block\">
                <a href=\"{{ path('app_tank_check_article_new') }}\" class=\"btn btn-transparent btn-solid-border\">Neuen Artikel anlegen</a>
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
                    <td width=\"180\">{{ tank_check_article.title }}</td>
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
</section>

{% endblock %}
", "tank_check_article/index.html.twig", "/shared/httpd/dicoma/templates/tank_check_article/index.html.twig");
    }
}
