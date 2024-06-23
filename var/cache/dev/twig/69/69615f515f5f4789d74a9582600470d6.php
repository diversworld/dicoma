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

/* student/show.html.twig */
class __TwigTemplate_ba04ccf4056b814f6050e7a169ef7202 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "student/show.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "student/show.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "student/show.html.twig", 1);
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

        echo "Schüler";
        
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
        echo "
<section class=\"about section\">
    <div class=\"container\">
        <h1>Persönliche anzeigen</h1>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    <a class=\"btn btn-transparent btn-solid-border\" href=\"";
        // line 13
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_student_index");
        echo "\">back to list</a>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"block\">
                    <a class=\"btn btn-transparent btn-solid-border\" href=\"";
        // line 18
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_student_edit", ["id" => twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 18, $this->source); })()), "id", [], "any", false, false, false, 18)]), "html", null, true);
        echo "\">edit</a>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"block\">
                    ";
        // line 23
        echo twig_include($this->env, $context, "student/_delete_form.html.twig");
        echo "
                </div>
            </div>
        </div>
        <section class=\"testimonial\">
            <div class=\"container\">
            <div class=\" testimonial-carousel\">

                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Vorname
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            ";
        // line 39
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 39, $this->source); })()), "firstname", [], "any", false, false, false, 39), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 39, $this->source); })()), "lastname", [], "any", false, false, false, 39), "html", null, true);
        echo "
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Geburtsdatum
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            ";
        // line 51
        ((twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 51, $this->source); })()), "birthday", [], "any", false, false, false, 51)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 51, $this->source); })()), "birthday", [], "any", false, false, false, 51), "Y-m-d"), "html", null, true))) : (print ("")));
        echo "
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Telefon
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            ";
        // line 63
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 63, $this->source); })()), "email", [], "any", false, false, false, 63), "html", null, true);
        echo "
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Geburtsdatum
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            ";
        // line 75
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 75, $this->source); })()), "postal", [], "any", false, false, false, 75), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 75, $this->source); })()), "city", [], "any", false, false, false, 75), "html", null, true);
        echo "
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Mobil
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            ";
        // line 87
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 87, $this->source); })()), "mobile", [], "any", false, false, false, 87), "html", null, true);
        echo "
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Telefon
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            ";
        // line 99
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 99, $this->source); })()), "phone", [], "any", false, false, false, 99), "html", null, true);
        echo "
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Benutzername
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            ";
        // line 111
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 111, $this->source); })()), "username", [], "any", false, false, false, 111), "html", null, true);
        echo "
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Status
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            ";
        // line 123
        if ((twig_get_attribute($this->env, $this->source, (isset($context["student"]) || array_key_exists("student", $context) ? $context["student"] : (function () { throw new RuntimeError('Variable "student" does not exist.', 123, $this->source); })()), "published", [], "any", false, false, false, 123) == "true")) {
            // line 124
            echo "                                Aktiv
                            ";
        } else {
            // line 126
            echo "                                Inaktiv
                            ";
        }
        // line 128
        echo "                        </div>
                    </div>
                </div>
            </div>
            </div>
        </section>
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
        return "student/show.html.twig";
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
        return array (  251 => 128,  247 => 126,  243 => 124,  241 => 123,  226 => 111,  211 => 99,  196 => 87,  179 => 75,  164 => 63,  149 => 51,  132 => 39,  113 => 23,  105 => 18,  97 => 13,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Schüler{% endblock %}

{% block body %}

<section class=\"about section\">
    <div class=\"container\">
        <h1>Persönliche anzeigen</h1>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    <a class=\"btn btn-transparent btn-solid-border\" href=\"{{ path('app_student_index') }}\">back to list</a>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"block\">
                    <a class=\"btn btn-transparent btn-solid-border\" href=\"{{ path('app_student_edit', {'id': student.id}) }}\">edit</a>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"block\">
                    {{ include('student/_delete_form.html.twig') }}
                </div>
            </div>
        </div>
        <section class=\"testimonial\">
            <div class=\"container\">
            <div class=\" testimonial-carousel\">

                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Vorname
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            {{ student.firstname }} {{ student.lastname }}
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Geburtsdatum
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            {{ student.birthday ? student.birthday|date('Y-m-d') : '' }}
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Telefon
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            {{ student.email }}
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Geburtsdatum
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            {{ student.postal}} {{ student.city }}
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Mobil
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            {{ student.mobile }}
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Telefon
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            {{ student.phone }}
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Benutzername
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            {{ student.username }}
                        </div>
                    </div>
                </div>
                <div class=\"row\">
                    <div class=\"col-md-3\">
                        <div class=\"block\">
                            Status
                        </div>
                    </div>
                    <div class=\"col-md-6\">
                        <div class=\"block\">
                            {% if student.published == 'true' %}
                                Aktiv
                            {% else %}
                                Inaktiv
                            {% endif %}
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </section>
    </div>
</section>
{% endblock %}
", "student/show.html.twig", "/shared/httpd/dicoma/templates/student/show.html.twig");
    }
}
