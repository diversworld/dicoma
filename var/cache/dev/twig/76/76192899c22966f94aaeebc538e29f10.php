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

/* schedule/show.html.twig */
class __TwigTemplate_b182e707ecc59a20c052e08814cbc6d9 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "schedule/show.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "schedule/show.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "schedule/show.html.twig", 1);
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

        echo "Kurstermin anzeigen";
        
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
        <h1>Kurstermindetails</h1>
        <p><a href=\"";
        // line 9
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_index");
        echo "\">Zurück zur Übersicht</a></p>
        <table class=\"table\">
            <tbody>
                <tr>
                    <th>Title</th>
                    <td>";
        // line 14
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 14, $this->source); })()), "title", [], "any", false, false, false, 14), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>StartDate</th>
                    <td>";
        // line 18
        ((twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 18, $this->source); })()), "startDate", [], "any", false, false, false, 18)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 18, $this->source); })()), "startDate", [], "any", false, false, false, 18), "Y-m-d"), "html", null, true))) : (print ("")));
        echo "</td>
                </tr>
                <tr>
                    <th>Duration</th>
                    <td>";
        // line 22
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 22, $this->source); })()), "duration", [], "any", false, false, false, 22), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>StartTime</th>
                    <td>";
        // line 26
        ((twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 26, $this->source); })()), "startTime", [], "any", false, false, false, 26)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 26, $this->source); })()), "startTime", [], "any", false, false, false, 26), "H:i:s"), "html", null, true))) : (print ("")));
        echo "</td>
                </tr>
                <tr>
                    <th>Location</th>
                    <td>";
        // line 30
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 30, $this->source); })()), "price", [], "any", false, false, false, 30), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>Location</th>
                    <td>";
        // line 34
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 34, $this->source); })()), "location", [], "any", false, false, false, 34), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>LocationStreet</th>
                    <td>";
        // line 38
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 38, $this->source); })()), "locationStreet", [], "any", false, false, false, 38), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>LocationPostal</th>
                    <td>";
        // line 42
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 42, $this->source); })()), "locationPostal", [], "any", false, false, false, 42), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>LocationCity</th>
                    <td>";
        // line 46
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 46, $this->source); })()), "locationCity", [], "any", false, false, false, 46), "html", null, true);
        echo "</td>
                </tr>
                <tr>
                    <th>Notes</th>
                    <td>";
        // line 50
        echo twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 50, $this->source); })()), "notes", [], "any", false, false, false, 50);
        echo "</td>
                </tr>
            </tbody>
        </table>

        <a href=\"";
        // line 55
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_edit", ["id" => twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 55, $this->source); })()), "id", [], "any", false, false, false, 55)]), "html", null, true);
        echo "\">edit</a>

        ";
        // line 57
        echo twig_include($this->env, $context, "schedule/_delete_form.html.twig");
        echo "
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
        return "schedule/show.html.twig";
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
        return array (  177 => 57,  172 => 55,  164 => 50,  157 => 46,  150 => 42,  143 => 38,  136 => 34,  129 => 30,  122 => 26,  115 => 22,  108 => 18,  101 => 14,  93 => 9,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Kurstermin anzeigen{% endblock %}

{% block body %}
<section class=\"about section\">
    <div class=\"container\">
        <h1>Kurstermindetails</h1>
        <p><a href=\"{{ path('app_schedule_index') }}\">Zurück zur Übersicht</a></p>
        <table class=\"table\">
            <tbody>
                <tr>
                    <th>Title</th>
                    <td>{{ schedule.title }}</td>
                </tr>
                <tr>
                    <th>StartDate</th>
                    <td>{{ schedule.startDate ? schedule.startDate|date('Y-m-d') : '' }}</td>
                </tr>
                <tr>
                    <th>Duration</th>
                    <td>{{ schedule.duration }}</td>
                </tr>
                <tr>
                    <th>StartTime</th>
                    <td>{{ schedule.startTime ? schedule.startTime|date('H:i:s') : '' }}</td>
                </tr>
                <tr>
                    <th>Location</th>
                    <td>{{ schedule.price }}</td>
                </tr>
                <tr>
                    <th>Location</th>
                    <td>{{ schedule.location }}</td>
                </tr>
                <tr>
                    <th>LocationStreet</th>
                    <td>{{ schedule.locationStreet }}</td>
                </tr>
                <tr>
                    <th>LocationPostal</th>
                    <td>{{ schedule.locationPostal }}</td>
                </tr>
                <tr>
                    <th>LocationCity</th>
                    <td>{{ schedule.locationCity }}</td>
                </tr>
                <tr>
                    <th>Notes</th>
                    <td>{{ schedule.notes|raw }}</td>
                </tr>
            </tbody>
        </table>

        <a href=\"{{ path('app_schedule_edit', {'id': schedule.id}) }}\">edit</a>

        {{ include('schedule/_delete_form.html.twig') }}
    </div>
</section>
{% endblock %}
", "schedule/show.html.twig", "/shared/httpd/dicoma/templates/schedule/show.html.twig");
    }
}
