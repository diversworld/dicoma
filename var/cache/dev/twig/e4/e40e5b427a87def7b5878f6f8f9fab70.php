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

/* courses/show.html.twig */
class __TwigTemplate_7a135ba49b4e29395f6e48040602285b extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "courses/show.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "courses/show.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "courses/show.html.twig", 1);
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

        $this->displayParentBlock("title", $context, $blocks);
        echo " -> Kurs anzeigen";
        
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
        <h1>Kursdetails</h1>
        <div class=\"row text-bg-dark text-center\">
            <div class=\"col-md-12\">
                <ul class=\"list-inline\">
                    <li class=\"list-inline-item\">
                        <a href=\"";
        // line 13
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_index");
        echo "\" class=\"btn btn-transparent btn-solid-border\">zurück</a>
                    </li>
                    <li class=\"list-inline-item\">
                        <a href=\"";
        // line 16
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_edit", ["id" => twig_get_attribute($this->env, $this->source, (isset($context["course"]) || array_key_exists("course", $context) ? $context["course"] : (function () { throw new RuntimeError('Variable "course" does not exist.', 16, $this->source); })()), "id", [], "any", false, false, false, 16), "selectedFile" => twig_get_attribute($this->env, $this->source, (isset($context["course"]) || array_key_exists("course", $context) ? $context["course"] : (function () { throw new RuntimeError('Variable "course" does not exist.', 16, $this->source); })()), "image", [], "any", false, false, false, 16)]), "html", null, true);
        echo "\" class=\"btn btn-transparent btn-solid-border\">Bearbeiten</a>
                    </li>
                    <li class=\"list-inline-item\">
                        ";
        // line 19
        echo twig_include($this->env, $context, "courses/_delete_form.html.twig");
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
                <div class=\"col-md-3\">
                    <img class=\"img-fluid rounded-start\" src=\"";
        // line 30
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl(("images/kurse/" . twig_get_attribute($this->env, $this->source, (isset($context["course"]) || array_key_exists("course", $context) ? $context["course"] : (function () { throw new RuntimeError('Variable "course" does not exist.', 30, $this->source); })()), "image", [], "any", false, false, false, 30))), "html", null, true);
        echo "\" alt=\"Brevetbild\">
                </div>
                <div class=\"col-md-9\">
                    <div class=\"card-header\">";
        // line 33
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["course"]) || array_key_exists("course", $context) ? $context["course"] : (function () { throw new RuntimeError('Variable "course" does not exist.', 33, $this->source); })()), "title", [], "any", false, false, false, 33), "html", null, true);
        echo "</div>
                    <div class=\"card-body\">
                        <h5 class=\"card-title\">Kursbeschreibung</h5>
                        <p class=\"card-text\">";
        // line 36
        echo twig_get_attribute($this->env, $this->source, (isset($context["course"]) || array_key_exists("course", $context) ? $context["course"] : (function () { throw new RuntimeError('Variable "course" does not exist.', 36, $this->source); })()), "description", [], "any", false, false, false, 36);
        echo "</p>
                        <h5 class=\"card-title\">Voraussetzungen</h5>
                        <p class=\"card-text\">";
        // line 38
        echo twig_get_attribute($this->env, $this->source, (isset($context["course"]) || array_key_exists("course", $context) ? $context["course"] : (function () { throw new RuntimeError('Variable "course" does not exist.', 38, $this->source); })()), "requirements", [], "any", false, false, false, 38);
        echo "</p>
                    </div>
                </div>
            </div>
        </div>
        <div class=\"row\">
            &nbsp;
        </div>
        <div class=\"row\">
            <div    class=\"col-md-12\">
                <h2>Kurstermine</h2>
                <table class=\"table\">
                    <thead>
                        <tr>
                            <th scope=\"col\">Kursname</th>
                            <th scope=\"col\">Startdatum</th>
                            <th scope=\"col\">Ort</th>
                            <th scope=\"col\">Preis</th>
                            <th scope=\"col\">Anzahl Buchungen</th>
                            <th scope=\"col\">Buchen</th>
                        </tr>
                    </thead>
                    <tbody>
                ";
        // line 61
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["schedules"]) || array_key_exists("schedules", $context) ? $context["schedules"] : (function () { throw new RuntimeError('Variable "schedules" does not exist.', 61, $this->source); })()));
        foreach ($context['_seq'] as $context["_key"] => $context["schedule"]) {
            // line 62
            echo "                    <tr>
                        <td>
                            ";
            // line 64
            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
                // line 65
                echo "                                <a  href=\"";
                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 65)]), "html", null, true);
                echo "\" role=\"\"Button>";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "title", [], "any", false, false, false, 65), "html", null, true);
                echo "</a>
                            ";
            } else {
                // line 67
                echo "                                ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "title", [], "any", false, false, false, 67), "html", null, true);
                echo "
                            ";
            }
            // line 68
            echo "</td>
                        <td>";
            // line 69
            ((twig_get_attribute($this->env, $this->source, $context["schedule"], "startDate", [], "any", false, false, false, 69)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "startDate", [], "any", false, false, false, 69), "d.m.Y"), "html", null, true))) : (print ("")));
            echo "</td>
                        <td>";
            // line 70
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "location", [], "any", false, false, false, 70), "html", null, true);
            echo "<br>
                            ";
            // line 71
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "locationStreet", [], "any", false, false, false, 71), "html", null, true);
            echo "<br>
                            ";
            // line 72
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "locationPostal", [], "any", false, false, false, 72), "html", null, true);
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "locationCity", [], "any", false, false, false, 72), "html", null, true);
            echo "
                        </td>
                        <td>";
            // line 74
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "price", [], "any", false, false, false, 74), "html", null, true);
            echo "</td>
                        <td>";
            // line 75
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["schedule"], "bookingCount", [], "any", false, false, false, 75), "html", null, true);
            echo "</td>
                        <td>
                            ";
            // line 77
            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_USER")) {
                // line 78
                echo "                                <a class=\"fa-solid fa-cart-shopping\" href=\"";
                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_booking_book", ["id" => twig_get_attribute($this->env, $this->source, $context["schedule"], "id", [], "any", false, false, false, 78), "user" => twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["app"]) || array_key_exists("app", $context) ? $context["app"] : (function () { throw new RuntimeError('Variable "app" does not exist.', 78, $this->source); })()), "user", [], "any", false, false, false, 78), "username", [], "any", false, false, false, 78)]), "html", null, true);
                echo "\" role=\"\"Button></a>
                            ";
            }
            // line 80
            echo "                        </td>
                    </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['schedule'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 83
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
        return "courses/show.html.twig";
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
        return array (  237 => 83,  229 => 80,  223 => 78,  221 => 77,  216 => 75,  212 => 74,  206 => 72,  202 => 71,  198 => 70,  194 => 69,  191 => 68,  185 => 67,  177 => 65,  175 => 64,  171 => 62,  167 => 61,  141 => 38,  136 => 36,  130 => 33,  124 => 30,  110 => 19,  104 => 16,  98 => 13,  89 => 6,  79 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}{{ parent() }} -> Kurs anzeigen{% endblock %}

{% block body %}
<section class=\"about section\">
    <div class=\"container\">
        <h1>Kursdetails</h1>
        <div class=\"row text-bg-dark text-center\">
            <div class=\"col-md-12\">
                <ul class=\"list-inline\">
                    <li class=\"list-inline-item\">
                        <a href=\"{{ path('app_courses_index') }}\" class=\"btn btn-transparent btn-solid-border\">zurück</a>
                    </li>
                    <li class=\"list-inline-item\">
                        <a href=\"{{ path('app_courses_edit', { 'id': course.id, 'selectedFile':  course.image }) }}\" class=\"btn btn-transparent btn-solid-border\">Bearbeiten</a>
                    </li>
                    <li class=\"list-inline-item\">
                        {{ include('courses/_delete_form.html.twig') }}
                    </li>
                </ul>
            </div>
        </div>
        <div class=\"row\">
            &nbsp;
        </div>
        <div class=\"card mb-12\">
            <div class=\"row g-0\">
                <div class=\"col-md-3\">
                    <img class=\"img-fluid rounded-start\" src=\"{{asset('images/kurse/' ~ course.image)}}\" alt=\"Brevetbild\">
                </div>
                <div class=\"col-md-9\">
                    <div class=\"card-header\">{{ course.title }}</div>
                    <div class=\"card-body\">
                        <h5 class=\"card-title\">Kursbeschreibung</h5>
                        <p class=\"card-text\">{{ course.description|raw }}</p>
                        <h5 class=\"card-title\">Voraussetzungen</h5>
                        <p class=\"card-text\">{{ course.requirements|raw }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class=\"row\">
            &nbsp;
        </div>
        <div class=\"row\">
            <div    class=\"col-md-12\">
                <h2>Kurstermine</h2>
                <table class=\"table\">
                    <thead>
                        <tr>
                            <th scope=\"col\">Kursname</th>
                            <th scope=\"col\">Startdatum</th>
                            <th scope=\"col\">Ort</th>
                            <th scope=\"col\">Preis</th>
                            <th scope=\"col\">Anzahl Buchungen</th>
                            <th scope=\"col\">Buchen</th>
                        </tr>
                    </thead>
                    <tbody>
                {% for schedule in schedules %}
                    <tr>
                        <td>
                            {% if is_granted('ROLE_ADMIN') %}
                                <a  href=\"{{ path('app_schedule_edit', {id: schedule.id}) }}\" role=\"\"Button>{{ schedule.title }}</a>
                            {% else %}
                                {{ schedule.title }}
                            {% endif %}</td>
                        <td>{{ schedule.startDate ? schedule.startDate|date('d.m.Y') : '' }}</td>
                        <td>{{ schedule.location }}<br>
                            {{ schedule.locationStreet }}<br>
                            {{ schedule.locationPostal }}{{ schedule.locationCity }}
                        </td>
                        <td>{{ schedule.price }}</td>
                        <td>{{ schedule.bookingCount }}</td>
                        <td>
                            {% if is_granted('ROLE_USER') %}
                                <a class=\"fa-solid fa-cart-shopping\" href=\"{{ path('app_booking_book', {id: schedule.id, user: app.user.username}) }}\" role=\"\"Button></a>
                            {% endif %}
                        </td>
                    </tr>
                {% endfor %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
{% endblock %}
", "courses/show.html.twig", "/shared/httpd/dicoma/templates/courses/show.html.twig");
    }
}
