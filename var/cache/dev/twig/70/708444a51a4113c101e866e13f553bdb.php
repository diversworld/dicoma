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

/* includes/_navigation.html.twig */
class __TwigTemplate_dc7b2e00c8f571357b407692f9db6e44 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "includes/_navigation.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "includes/_navigation.html.twig"));

        // line 1
        echo "<nav class=\"navbar navbar-expand-lg p-0\">
    <a class=\"navbar-brand\" href=\"";
        // line 2
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_home");
        echo "\">
        <img src=\"";
        // line 3
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("images/Diversworld-Wort-Logo-ws.png"), "html", null, true);
        echo "\" width=\"250px\" alt=\"Logo\">
    </a>

    <button class=\"navbar-toggler collapsed\" type=\"button\" data-toggle=\"collapse\" data-target=\"#navbarsExample09\" aria-controls=\"navbarsExample09\" aria-expanded=\"false\" aria-label=\"Toggle navigation\">
        <span class=\"ion-android-menu\"></span>
    </button>

    <div class=\"collapse navbar-collapse ml-auto\" id=\"navbarsExample09\">
        <ul class=\"navbar-nav ml-auto\">
            <li class=\"nav-item\">
                <a class=\"nav-link\" href=\"";
        // line 13
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_home");
        echo "\">Home</a>
            </li>
            <li class=\"nav-item dropdown \">
                <a class=\"nav-link dropdown-toggle\" href=\"";
        // line 16
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_index");
        echo "\" id=\"dropdown\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">Tauchkurse</a>
                <ul class=\"dropdown-menu\" aria-labelledby=\"dropdown03\">
                    <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
        // line 18
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_index");
        echo "\">Tauchkurse</a></li>
                    <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
        // line 19
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_index");
        echo "\">Kurstermine</a></li>
                    <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
        // line 20
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_calendar", ["month" => twig_date_format_filter($this->env, "now", "m")]), "html", null, true);
        echo "\">Kurskalender</a></li>
                    <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
        // line 21
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_index");
        echo "\">Instruktoren</a></li>
                </ul>
            </li>

            <li class=\"nav-item @@service\"><a class=\"nav-link\" href=\"#\">Service</a></li>
            <li class=\"nav-item @@contact\"><a class=\"nav-link\" href=\"#\">Contact</a></li>
            ";
        // line 27
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("IS_AUTHENTICATED_ANONYMOUSLY")) {
            // line 28
            echo "                <li><a class=\"nav-link\" href=\"";
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_register");
            echo "\">Registrieren</a></li>
            ";
        }
        // line 30
        echo "            ";
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_INSTRUCTOR")) {
            // line 31
            echo "                <li class=\"nav-item dropdown @@portfolio\">
                    <a class=\"nav-link dropdown-toggle\" href=\"#\" id=\"dropdown\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">Instructor Menü<span class=\"ion-ios-arrow-down\"></span></a>
                    <ul class=\"dropdown-menu\" aria-labelledby=\"dropdown\">
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
            // line 34
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_index");
            echo "\">Instructor-Übersicht</a></li>
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
            // line 35
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_booking_index");
            echo "\">Buchungen bearbeiten</a></li>
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
            // line 36
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_student_index");
            echo "\">Tauchschüler</a></li>
                    </ul>
                </li>
            ";
        }
        // line 40
        echo "            ";
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("IS_AUTHENTICATED_FULLY")) {
            // line 41
            echo "                <li class=\"nav-item dropdown @@portfolio\">
                    <a class=\"nav-link dropdown-toggle\" href=\"#\" id=\"dropdown\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\"><i class=\"ion-person\"> ";
            // line 42
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["app"]) || array_key_exists("app", $context) ? $context["app"] : (function () { throw new RuntimeError('Variable "app" does not exist.', 42, $this->source); })()), "user", [], "any", false, false, false, 42), "username", [], "any", false, false, false, 42), "html", null, true);
            echo "</i> </a>
                    <ul class=\"dropdown-menu\" aria-labelledby=\"dropdown\">
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
            // line 44
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_profile");
            echo "\">Meine Daten</a></li>
                        ";
            // line 45
            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
                // line 46
                echo "                            <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("admin", ["id" => twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["app"]) || array_key_exists("app", $context) ? $context["app"] : (function () { throw new RuntimeError('Variable "app" does not exist.', 46, $this->source); })()), "user", [], "any", false, false, false, 46), "id", [], "any", false, false, false, 46)]), "html", null, true);
                echo "\" target=\"_blank\">Backend</a></li>
                        ";
            }
            // line 48
            echo "                        <li class=\"nav-item @@logout\"><a class=\"nav-link\" href=\"";
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_logout");
            echo "\">Logout</a></li>
                    </ul>
                </li>
            ";
        } else {
            // line 52
            echo "                <li class=\"nav-item @@login\"><a class=\"nav-link\" href=\"";
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_login");
            echo "\">Login</a></li>
            ";
        }
        // line 54
        echo "            ";
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
            // line 55
            echo "                <li class=\"nav-item dropdown @@portfolio\">
                    <a class=\"nav-link dropdown-toggle\" href=\"#\" id=\"dropdown03\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">Center Manager<span class=\"ion-ios-arrow-down\"></span></a>
                    <ul class=\"dropdown-menu\" aria-labelledby=\"dropdown03\">
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
            // line 58
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_index");
            echo "\">Kurs-Übersicht</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"";
            // line 59
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_new");
            echo "\">kurs hinzufügen</a></li>
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
            // line 60
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_index");
            echo "\">Kurstermine</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"";
            // line 61
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_schedule_new");
            echo "\">Kurstermin hinzufügen</a></li>
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"";
            // line 62
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_student_index");
            echo "\">Schüler</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"";
            // line 63
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_student_new");
            echo "\">Schüler hinzufügen</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"";
            // line 64
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_vendor_index");
            echo "\">Lieferanten</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"";
            // line 65
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_vendor_new");
            echo "\">Lieferant hinzufügen</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"";
            // line 66
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_index");
            echo "\">Flaschen</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"";
            // line 67
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_index");
            echo "\">Flaschenprüfung</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"";
            // line 68
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_tank_check_detail_index");
            echo "\">Buchungen Flaschenprüfung</a></li>
                    </ul>
                </li>
            ";
        }
        // line 72
        echo "        </ul>
    </div>
</nav>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "includes/_navigation.html.twig";
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
        return array (  218 => 72,  211 => 68,  207 => 67,  203 => 66,  199 => 65,  195 => 64,  191 => 63,  187 => 62,  183 => 61,  179 => 60,  175 => 59,  171 => 58,  166 => 55,  163 => 54,  157 => 52,  149 => 48,  143 => 46,  141 => 45,  137 => 44,  132 => 42,  129 => 41,  126 => 40,  119 => 36,  115 => 35,  111 => 34,  106 => 31,  103 => 30,  97 => 28,  95 => 27,  86 => 21,  82 => 20,  78 => 19,  74 => 18,  69 => 16,  63 => 13,  50 => 3,  46 => 2,  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<nav class=\"navbar navbar-expand-lg p-0\">
    <a class=\"navbar-brand\" href=\"{{ path('app_home') }}\">
        <img src=\"{{ asset('images/Diversworld-Wort-Logo-ws.png') }}\" width=\"250px\" alt=\"Logo\">
    </a>

    <button class=\"navbar-toggler collapsed\" type=\"button\" data-toggle=\"collapse\" data-target=\"#navbarsExample09\" aria-controls=\"navbarsExample09\" aria-expanded=\"false\" aria-label=\"Toggle navigation\">
        <span class=\"ion-android-menu\"></span>
    </button>

    <div class=\"collapse navbar-collapse ml-auto\" id=\"navbarsExample09\">
        <ul class=\"navbar-nav ml-auto\">
            <li class=\"nav-item\">
                <a class=\"nav-link\" href=\"{{ path('app_home') }}\">Home</a>
            </li>
            <li class=\"nav-item dropdown \">
                <a class=\"nav-link dropdown-toggle\" href=\"{{ path('app_courses_index') }}\" id=\"dropdown\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">Tauchkurse</a>
                <ul class=\"dropdown-menu\" aria-labelledby=\"dropdown03\">
                    <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_courses_index') }}\">Tauchkurse</a></li>
                    <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_schedule_index') }}\">Kurstermine</a></li>
                    <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_schedule_calendar', {'month': \"now\"|date(\"m\") }) }}\">Kurskalender</a></li>
                    <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_instructor_index') }}\">Instruktoren</a></li>
                </ul>
            </li>

            <li class=\"nav-item @@service\"><a class=\"nav-link\" href=\"#\">Service</a></li>
            <li class=\"nav-item @@contact\"><a class=\"nav-link\" href=\"#\">Contact</a></li>
            {%  if is_granted('IS_AUTHENTICATED_ANONYMOUSLY') %}
                <li><a class=\"nav-link\" href=\"{{ path('app_register') }}\">Registrieren</a></li>
            {% endif %}
            {% if is_granted('ROLE_INSTRUCTOR') %}
                <li class=\"nav-item dropdown @@portfolio\">
                    <a class=\"nav-link dropdown-toggle\" href=\"#\" id=\"dropdown\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">Instructor Menü<span class=\"ion-ios-arrow-down\"></span></a>
                    <ul class=\"dropdown-menu\" aria-labelledby=\"dropdown\">
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_instructor_index') }}\">Instructor-Übersicht</a></li>
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{path('app_booking_index')}}\">Buchungen bearbeiten</a></li>
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{path('app_student_index')}}\">Tauchschüler</a></li>
                    </ul>
                </li>
            {% endif %}
            {% if is_granted('IS_AUTHENTICATED_FULLY') %}
                <li class=\"nav-item dropdown @@portfolio\">
                    <a class=\"nav-link dropdown-toggle\" href=\"#\" id=\"dropdown\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\"><i class=\"ion-person\"> {{  app.user.username }}</i> </a>
                    <ul class=\"dropdown-menu\" aria-labelledby=\"dropdown\">
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_profile') }}\">Meine Daten</a></li>
                        {% if is_granted('ROLE_ADMIN') %}
                            <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('admin', {'id': app.user.id}) }}\" target=\"_blank\">Backend</a></li>
                        {% endif %}
                        <li class=\"nav-item @@logout\"><a class=\"nav-link\" href=\"{{ path('app_logout') }}\">Logout</a></li>
                    </ul>
                </li>
            {% else %}
                <li class=\"nav-item @@login\"><a class=\"nav-link\" href=\"{{ path('app_login') }}\">Login</a></li>
            {% endif %}
            {% if is_granted('ROLE_ADMIN') %}
                <li class=\"nav-item dropdown @@portfolio\">
                    <a class=\"nav-link dropdown-toggle\" href=\"#\" id=\"dropdown03\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">Center Manager<span class=\"ion-ios-arrow-down\"></span></a>
                    <ul class=\"dropdown-menu\" aria-labelledby=\"dropdown03\">
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_courses_index') }}\">Kurs-Übersicht</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"{{ path('app_courses_new') }}\">kurs hinzufügen</a></li>
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_schedule_index') }}\">Kurstermine</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"{{ path('app_schedule_new') }}\">Kurstermin hinzufügen</a></li>
                        <li><a class=\"dropdown-item @@portfolioSingle\" href=\"{{ path('app_student_index') }}\">Schüler</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"{{ path('app_student_new') }}\">Schüler hinzufügen</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"{{ path('app_vendor_index') }}\">Lieferanten</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"{{ path('app_vendor_new') }}\">Lieferant hinzufügen</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"{{ path('app_tank_index') }}\">Flaschen</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"{{ path('app_tank_check_index') }}\">Flaschenprüfung</a></li>
                        <li><a class=\"dropdown-item @@portfolioFilter\" href=\"{{ path('app_tank_check_detail_index') }}\">Buchungen Flaschenprüfung</a></li>
                    </ul>
                </li>
            {% endif %}
        </ul>
    </div>
</nav>
", "includes/_navigation.html.twig", "/shared/httpd/dicoma/templates/includes/_navigation.html.twig");
    }
}
