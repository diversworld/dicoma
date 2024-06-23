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

/* schedule/calendar.html.twig */
class __TwigTemplate_0cac1e4b7f7c1fe6a3cab50a727db48b extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "schedule/calendar.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "schedule/calendar.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "schedule/calendar.html.twig", 1);
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

        echo "Terminkalender";
        
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
        echo "    <section class=\"about section\">
        <div class=\"container\">
            <h1>Kurskalender</h1>
            <div class=\"row\">
                ";
        // line 11
        echo "                <div class=\"col-md-4\">
                    <a href=\"";
        // line 12
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_calendar", ["month" => ((((twig_date_format_filter($this->env, (isset($context["currentDate"]) || array_key_exists("currentDate", $context) ? $context["currentDate"] : (function () { throw new RuntimeError('Variable "currentDate" does not exist.', 12, $this->source); })()), "m") - 1) < 1)) ? (12) : ((twig_date_format_filter($this->env, (isset($context["currentDate"]) || array_key_exists("currentDate", $context) ? $context["currentDate"] : (function () { throw new RuntimeError('Variable "currentDate" does not exist.', 12, $this->source); })()), "m") - 1)))]), "html", null, true);
        echo "\">Zurück</a>
                </div>
                <div class=\"col-md-4\" style=\"text-align: center;\">
                    <a href=\"";
        // line 15
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_calendar", ["month" => twig_date_format_filter($this->env, (isset($context["currentDate"]) || array_key_exists("currentDate", $context) ? $context["currentDate"] : (function () { throw new RuntimeError('Variable "currentDate" does not exist.', 15, $this->source); })()), "m")]), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, (isset($context["currentDate"]) || array_key_exists("currentDate", $context) ? $context["currentDate"] : (function () { throw new RuntimeError('Variable "currentDate" does not exist.', 15, $this->source); })()), "M"), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, (isset($context["currentDate"]) || array_key_exists("currentDate", $context) ? $context["currentDate"] : (function () { throw new RuntimeError('Variable "currentDate" does not exist.', 15, $this->source); })()), "Y"), "html", null, true);
        echo "</a>
                </div>
                <div class=\"col-md-4\" style=\"text-align: right;\">
                    <a href=\"";
        // line 18
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_calendar", ["month" => ((((twig_date_format_filter($this->env, (isset($context["currentDate"]) || array_key_exists("currentDate", $context) ? $context["currentDate"] : (function () { throw new RuntimeError('Variable "currentDate" does not exist.', 18, $this->source); })()), "m") + 1) > 12)) ? (1) : ((twig_date_format_filter($this->env, (isset($context["currentDate"]) || array_key_exists("currentDate", $context) ? $context["currentDate"] : (function () { throw new RuntimeError('Variable "currentDate" does not exist.', 18, $this->source); })()), "m") + 1)))]), "html", null, true);
        echo "\">Weiter</a>
                </div>
                ";
        // line 21
        echo "                <div class=\"table-responsive\">
                    <table class=\"table table-striped table-bordered table-hover\" style=\"table-layout: fixed;\">
                        <tr style=\"border-left: 1px; border-right: 1px; border-color: #cecece; border-style: solid\">
                            <th style=\"text-align: center;\">Mo</th>
                            <th style=\"text-align: center;\">Di</th>
                            <th style=\"text-align: center;\">Mi</th>
                            <th style=\"text-align: center;\">Do</th>
                            <th style=\"text-align: center;\">Fr</th>
                            <th style=\"text-align: center;\">Sa</th>
                            <th style=\"text-align: center;\">So</th>
                        </tr>
                        ";
        // line 32
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["calendar"]) || array_key_exists("calendar", $context) ? $context["calendar"] : (function () { throw new RuntimeError('Variable "calendar" does not exist.', 32, $this->source); })()));
        foreach ($context['_seq'] as $context["_key"] => $context["week"]) {
            // line 33
            echo "                            <tr class=\"row-cols-7 text-center\">
                                ";
            // line 34
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($context["week"]);
            foreach ($context['_seq'] as $context["_key"] => $context["date"]) {
                // line 35
                echo "                                    <td class=\"col-md-2 \" style=\"height: 120px;\">
                                        ";
                // line 36
                if (($context["date"] != " ")) {
                    // line 37
                    echo "                                            <div class=\"block\" >
                                                ";
                    // line 38
                    echo twig_escape_filter($this->env, twig_date_format_filter($this->env, $context["date"], "d"), "html", null, true);
                    echo "
                                                ";
                    // line 39
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable((isset($context["schedules"]) || array_key_exists("schedules", $context) ? $context["schedules"] : (function () { throw new RuntimeError('Variable "schedules" does not exist.', 39, $this->source); })()));
                    foreach ($context['_seq'] as $context["_key"] => $context["schedule"]) {
                        // line 40
                        echo "                                                    ";
                        if (($context["date"] == twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "startDate", [], "any", false, false, false, 40), "Y-m-d"))) {
                            // line 41
                            echo "                                                        <div class=\"block bg-shadow\">
                                                            <div class=\"badge text-wrap\">
                                                                <a href=\"";
                            // line 43
                            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_show", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 43)]), "html", null, true);
                            echo "\">
                                                                    ";
                            // line 44
                            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "title", [], "any", false, false, false, 44), "html", null, true);
                            echo "<br>";
                            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "startTime", [], "any", false, false, false, 44), "H:i"), "html", null, true);
                            echo "
                                                                </a>
                                                            </div>
                                                            <ul class=\"list-inline\">
                                                                ";
                            // line 48
                            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
                                // line 49
                                echo "                                                                    <li class=\"list-inline-item\">
                                                                        <a href=\"";
                                // line 50
                                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 50)]), "html", null, true);
                                echo "\"><i class=\"ion-edit\"></i></a>
                                                                    </li>
                                                                ";
                            }
                            // line 53
                            echo "                                                                ";
                            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_USER")) {
                                // line 54
                                echo "                                                                    <li class=\"list-inline-item\">
                                                                        <a href=\"";
                                // line 55
                                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_booking_book", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 55), "user" => twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["app"]) || array_key_exists("app", $context) ? $context["app"] : (function () { throw new RuntimeError('Variable "app" does not exist.', 55, $this->source); })()), "user", [], "any", false, false, false, 55), "username", [], "any", false, false, false, 55)]), "html", null, true);
                                echo "\"><i class=\"ion-card\"></i> </a>
                                                                    </li>
                                                                ";
                            }
                            // line 58
                            echo "                                                            </ul>
                                                        </div>
                                                    ";
                        }
                        // line 61
                        echo "                                                ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['schedule'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 62
                    echo "                                            </div>
                                        ";
                } else {
                    // line 64
                    echo "                                            ";
                    // line 65
                    echo "                                        ";
                }
                // line 66
                echo "                                    </td>
                                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['date'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 68
            echo "                            </tr>
                        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['week'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 70
        echo "                    </table>
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
        return "schedule/calendar.html.twig";
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
        return array (  235 => 70,  228 => 68,  221 => 66,  218 => 65,  216 => 64,  212 => 62,  206 => 61,  201 => 58,  195 => 55,  192 => 54,  189 => 53,  183 => 50,  180 => 49,  178 => 48,  169 => 44,  165 => 43,  161 => 41,  158 => 40,  154 => 39,  150 => 38,  147 => 37,  145 => 36,  142 => 35,  138 => 34,  135 => 33,  131 => 32,  118 => 21,  113 => 18,  103 => 15,  97 => 12,  94 => 11,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Terminkalender{% endblock %}

{% block body %}
    <section class=\"about section\">
        <div class=\"container\">
            <h1>Kurskalender</h1>
            <div class=\"row\">
                {# Calendar Navigation #}
                <div class=\"col-md-4\">
                    <a href=\"{{ path('app_schedule_calendar', {'month': (currentDate|date('m') - 1 < 1 ? 12 : currentDate|date('m') - 1)}) }}\">Zurück</a>
                </div>
                <div class=\"col-md-4\" style=\"text-align: center;\">
                    <a href=\"{{ path('app_schedule_calendar', {'month': currentDate|date('m')}) }}\">{{ currentDate|date('M') }} {{  currentDate|date('Y') }}</a>
                </div>
                <div class=\"col-md-4\" style=\"text-align: right;\">
                    <a href=\"{{ path('app_schedule_calendar', {'month': (currentDate|date('m') + 1 > 12 ? 1 : currentDate|date('m') + 1)}) }}\">Weiter</a>
                </div>
                {# Calendar #}
                <div class=\"table-responsive\">
                    <table class=\"table table-striped table-bordered table-hover\" style=\"table-layout: fixed;\">
                        <tr style=\"border-left: 1px; border-right: 1px; border-color: #cecece; border-style: solid\">
                            <th style=\"text-align: center;\">Mo</th>
                            <th style=\"text-align: center;\">Di</th>
                            <th style=\"text-align: center;\">Mi</th>
                            <th style=\"text-align: center;\">Do</th>
                            <th style=\"text-align: center;\">Fr</th>
                            <th style=\"text-align: center;\">Sa</th>
                            <th style=\"text-align: center;\">So</th>
                        </tr>
                        {% for week in calendar %}
                            <tr class=\"row-cols-7 text-center\">
                                {% for date in week %}
                                    <td class=\"col-md-2 \" style=\"height: 120px;\">
                                        {% if date != ' ' %}
                                            <div class=\"block\" >
                                                {{ date|date('d') }}
                                                {% for schedule in schedules %}
                                                    {% if date == schedule.startDate|date('Y-m-d') %}
                                                        <div class=\"block bg-shadow\">
                                                            <div class=\"badge text-wrap\">
                                                                <a href=\"{{ path('app_schedule_show', {'id': schedule.id}) }}\">
                                                                    {{ schedule.title }}<br>{{ schedule.startTime|date('H:i') }}
                                                                </a>
                                                            </div>
                                                            <ul class=\"list-inline\">
                                                                {% if is_granted('ROLE_ADMIN') %}
                                                                    <li class=\"list-inline-item\">
                                                                        <a href=\"{{ path('app_schedule_edit', {'id': schedule.id}) }}\"><i class=\"ion-edit\"></i></a>
                                                                    </li>
                                                                {% endif %}
                                                                {% if is_granted('ROLE_USER') %}
                                                                    <li class=\"list-inline-item\">
                                                                        <a href=\"{{ path('app_booking_book', {id: schedule.id, user: app.user.username}) }}\"><i class=\"ion-card\"></i> </a>
                                                                    </li>
                                                                {% endif %}
                                                            </ul>
                                                        </div>
                                                    {% endif %}
                                                {% endfor %}
                                            </div>
                                        {% else %}
                                            {# cell is empty #}
                                        {% endif %}
                                    </td>
                                {% endfor %}
                            </tr>
                        {% endfor %}
                    </table>
                </div>
            </div>
        </div>
    </section>
{% endblock %}", "schedule/calendar.html.twig", "/shared/httpd/dicoma/templates/schedule/calendar.html.twig");
    }
}
