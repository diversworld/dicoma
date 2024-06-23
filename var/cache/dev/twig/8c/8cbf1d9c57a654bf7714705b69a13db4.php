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

/* schedule/index.html.twig */
class __TwigTemplate_cc2f9f90b310760b0bcf2886cf4953b7 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "schedule/index.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "schedule/index.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "schedule/index.html.twig", 1);
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

        echo "Kurstermine";
        
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
        ";
        // line 8
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (isset($context["app"]) || array_key_exists("app", $context) ? $context["app"] : (function () { throw new RuntimeError('Variable "app" does not exist.', 8, $this->source); })()), "flashes", [], "any", false, false, false, 8));
        foreach ($context['_seq'] as $context["label"] => $context["messages"]) {
            // line 9
            echo "            ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($context["messages"]);
            foreach ($context['_seq'] as $context["_key"] => $context["message"]) {
                // line 10
                echo "                <div class=\"alert alert-";
                echo twig_escape_filter($this->env, $context["label"], "html", null, true);
                echo "\">
                    ";
                // line 11
                echo twig_escape_filter($this->env, $context["message"], "html", null, true);
                echo "
                </div>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['message'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 14
            echo "        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['label'], $context['messages'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 15
        echo "        <h1>Kurstermin Übersicht</h1>
        <div class=\"row\">
            ";
        // line 17
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
            // line 18
            echo "                <div class=\"col-md-3\">
                    <div class=\"block\">
                        <a href=\"";
            // line 20
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_new");
            echo "\" class=\"btn btn-transparent btn-solid-border\">Neuen Termin erstellen</a>
                    </div>
                </div>
            ";
        }
        // line 24
        echo "        </div>
        <div class=\"row\">
            &nbsp;
        </div>
        <div class=\"row\">
            <div class=\"col-md-12\">
                <table class=\"table\">
                    <thead>
                        <tr>
                            <th>Kurstermin</th>
                            <th>Startdatum</th>
                            <th>Startzeit</th>
                            <th>Dauer</th>
                            <th>Ort</th>
                            <th>Kurs</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    ";
        // line 43
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["schedules"]) || array_key_exists("schedules", $context) ? $context["schedules"] : (function () { throw new RuntimeError('Variable "schedules" does not exist.', 43, $this->source); })()));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["schedule"]) {
            // line 44
            echo "                        <tr>
                            <td><a href=\"";
            // line 45
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_show", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 45)]), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "title", [], "any", false, false, false, 45), "html", null, true);
            echo "</a></td>
                            <td>";
            // line 46
            ((twig_get_attribute($this->env, $this->source, $context["schedule"], "startDate", [], "any", false, false, false, 46)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "startDate", [], "any", false, false, false, 46), "d.m.Y"), "html", null, true))) : (print ("")));
            echo "</td>
                            <td>";
            // line 47
            ((twig_get_attribute($this->env, $this->source, $context["schedule"], "startTime", [], "any", false, false, false, 47)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "startTime", [], "any", false, false, false, 47), "H:i"), "html", null, true))) : (print ("")));
            echo "</td>
                            <td>";
            // line 48
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "duration", [], "any", false, false, false, 48), "html", null, true);
            echo " Tage</td>
                            <td>";
            // line 49
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "location", [], "any", false, false, false, 49), "html", null, true);
            echo "</td>
                            <td><a href=\"";
            // line 50
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_show", ["id" => twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["schedule"], "courses", [], "any", false, false, false, 50), "id", [], "any", false, false, false, 50)]), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["schedule"], "courses", [], "any", false, false, false, 50), "title", [], "any", false, false, false, 50), "html", null, true);
            echo "</a></td>
                            <td>
                                <a href=\"";
            // line 52
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_show", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 52)]), "html", null, true);
            echo "\"><i class=\"ion-ios-eye\"></i>  |  </a>
                                ";
            // line 53
            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
                // line 54
                echo "                                    <a href=\"";
                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 54)]), "html", null, true);
                echo "\"><i class=\"ion-edit\"></i> |  </a>
                                ";
            }
            // line 56
            echo "                                ";
            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_USER")) {
                // line 57
                echo "                                    <a href=\"";
                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_booking_book", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 57), "user" => twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["app"]) || array_key_exists("app", $context) ? $context["app"] : (function () { throw new RuntimeError('Variable "app" does not exist.', 57, $this->source); })()), "user", [], "any", false, false, false, 57), "id", [], "any", false, false, false, 57)]), "html", null, true);
                echo "\"><i class=\"ion-card\"></i> </a>
                                ";
            }
            // line 59
            echo "                            </td>
                        </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 62
            echo "                        <tr>
                            <td colspan=\"11\">no records found</td>
                        </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['schedule'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 66
        echo "                    </tbody>
                </table>
            </div>
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
        return "schedule/index.html.twig";
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
        return array (  233 => 66,  224 => 62,  217 => 59,  211 => 57,  208 => 56,  202 => 54,  200 => 53,  196 => 52,  189 => 50,  185 => 49,  181 => 48,  177 => 47,  173 => 46,  167 => 45,  164 => 44,  159 => 43,  138 => 24,  131 => 20,  127 => 18,  125 => 17,  121 => 15,  115 => 14,  106 => 11,  101 => 10,  96 => 9,  92 => 8,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Kurstermine{% endblock %}

{% block body %}
<section class=\"about section\">
    <div class=\"container\">
        {% for label, messages in app.flashes %}
            {% for message in messages %}
                <div class=\"alert alert-{{ label }}\">
                    {{ message }}
                </div>
            {% endfor %}
        {% endfor %}
        <h1>Kurstermin Übersicht</h1>
        <div class=\"row\">
            {% if is_granted('ROLE_ADMIN') %}
                <div class=\"col-md-3\">
                    <div class=\"block\">
                        <a href=\"{{ path('app_schedule_new') }}\" class=\"btn btn-transparent btn-solid-border\">Neuen Termin erstellen</a>
                    </div>
                </div>
            {% endif %}
        </div>
        <div class=\"row\">
            &nbsp;
        </div>
        <div class=\"row\">
            <div class=\"col-md-12\">
                <table class=\"table\">
                    <thead>
                        <tr>
                            <th>Kurstermin</th>
                            <th>Startdatum</th>
                            <th>Startzeit</th>
                            <th>Dauer</th>
                            <th>Ort</th>
                            <th>Kurs</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    {% for schedule in schedules %}
                        <tr>
                            <td><a href=\"{{ path('app_schedule_show', {'id': schedule.id}) }}\">{{ schedule.title }}</a></td>
                            <td>{{ schedule.startDate ? schedule.startDate|date('d.m.Y') : '' }}</td>
                            <td>{{ schedule.startTime ? schedule.startTime|date('H:i') : '' }}</td>
                            <td>{{ schedule.duration }} Tage</td>
                            <td>{{ schedule.location }}</td>
                            <td><a href=\"{{ path('app_courses_show', {'id': schedule.courses.id}) }}\">{{ schedule.courses.title }}</a></td>
                            <td>
                                <a href=\"{{ path('app_schedule_show', {'id': schedule.id}) }}\"><i class=\"ion-ios-eye\"></i>  |  </a>
                                {% if is_granted('ROLE_ADMIN') %}
                                    <a href=\"{{ path('app_schedule_edit', {'id': schedule.id}) }}\"><i class=\"ion-edit\"></i> |  </a>
                                {% endif %}
                                {% if is_granted('ROLE_USER') %}
                                    <a href=\"{{ path('app_booking_book', {id: schedule.id, user: app.user.id}) }}\"><i class=\"ion-card\"></i> </a>
                                {% endif %}
                            </td>
                        </tr>
                    {% else %}
                        <tr>
                            <td colspan=\"11\">no records found</td>
                        </tr>
                    {% endfor %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
{% endblock %}
", "schedule/index.html.twig", "/shared/httpd/dicoma/templates/schedule/index.html.twig");
    }
}
