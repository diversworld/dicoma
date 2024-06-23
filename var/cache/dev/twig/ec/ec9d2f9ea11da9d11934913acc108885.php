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

/* instructor/show.html.twig */
class __TwigTemplate_33b42288499902ba0732fa45921d656b extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "instructor/show.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "instructor/show.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "instructor/show.html.twig", 1);
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

        echo "Instructor";
        
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
        echo "<section class=\"section\">
    <div class=\"container\">
        <h1>Instruktor anzeigen</h1>
    </div>
    <div class=\"container\">
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    <a href=\"";
        // line 14
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_index");
        echo "\">back to list</a>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"block\">
                    ";
        // line 19
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
            // line 20
            echo "                        <a href=\"";
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_edit", ["id" => twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 20, $this->source); })()), "id", [], "any", false, false, false, 20)]), "html", null, true);
            echo "\">edit</a>
                    ";
        }
        // line 22
        echo "                </div>
            </div>
            <div class=\"col-md-2\">
                ";
        // line 25
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
            // line 26
            echo "                    ";
            echo twig_include($this->env, $context, "instructor/_delete_form.html.twig");
            echo "
                ";
        }
        // line 28
        echo "            </div>
        </div>
    </div>
    <div class=\"container\">
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Vorname
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    ";
        // line 40
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 40, $this->source); })()), "firstname", [], "any", false, false, false, 40), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 40, $this->source); })()), "lastname", [], "any", false, false, false, 40), "html", null, true);
        echo "
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Geburtsdatum
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    ";
        // line 52
        ((twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 52, $this->source); })()), "birthdate", [], "any", false, false, false, 52)) ? (print (twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 52, $this->source); })()), "birthdate", [], "any", false, false, false, 52), "Y-m-d"), "html", null, true))) : (print ("")));
        echo "
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Telefon
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    ";
        // line 64
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 64, $this->source); })()), "email", [], "any", false, false, false, 64), "html", null, true);
        echo "
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Geburtsdatum
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    ";
        // line 76
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 76, $this->source); })()), "postal", [], "any", false, false, false, 76), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 76, $this->source); })()), "city", [], "any", false, false, false, 76), "html", null, true);
        echo "
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Mobil
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    ";
        // line 88
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 88, $this->source); })()), "mobile", [], "any", false, false, false, 88), "html", null, true);
        echo "
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Telefon
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    ";
        // line 100
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 100, $this->source); })()), "phone", [], "any", false, false, false, 100), "html", null, true);
        echo "
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Benutzername
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    ";
        // line 112
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 112, $this->source); })()), "username", [], "any", false, false, false, 112), "html", null, true);
        echo "
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Status
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    ";
        // line 124
        if ((twig_get_attribute($this->env, $this->source, (isset($context["instructor"]) || array_key_exists("instructor", $context) ? $context["instructor"] : (function () { throw new RuntimeError('Variable "instructor" does not exist.', 124, $this->source); })()), "status", [], "any", false, false, false, 124) == "true")) {
            // line 125
            echo "                        Aktiv
                    ";
        } else {
            // line 127
            echo "                        Inaktiv
                    ";
        }
        // line 129
        echo "                </div>
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
        return "instructor/show.html.twig";
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
        return array (  260 => 129,  256 => 127,  252 => 125,  250 => 124,  235 => 112,  220 => 100,  205 => 88,  188 => 76,  173 => 64,  158 => 52,  141 => 40,  127 => 28,  121 => 26,  119 => 25,  114 => 22,  108 => 20,  106 => 19,  98 => 14,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Instructor{% endblock %}

{% block body %}
<section class=\"section\">
    <div class=\"container\">
        <h1>Instruktor anzeigen</h1>
    </div>
    <div class=\"container\">
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    <a href=\"{{ path('app_instructor_index') }}\">back to list</a>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"block\">
                    {%  if is_granted('ROLE_ADMIN') %}
                        <a href=\"{{ path('app_instructor_edit', {'id': instructor.id}) }}\">edit</a>
                    {% endif %}
                </div>
            </div>
            <div class=\"col-md-2\">
                {%  if is_granted('ROLE_ADMIN') %}
                    {{ include('instructor/_delete_form.html.twig') }}
                {% endif %}
            </div>
        </div>
    </div>
    <div class=\"container\">
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Vorname
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    {{ instructor.firstname }} {{ instructor.lastname }}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Geburtsdatum
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    {{ instructor.birthdate ? instructor.birthdate|date('Y-m-d') : '' }}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Telefon
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    {{ instructor.email }}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Geburtsdatum
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    {{ instructor.postal}} {{ instructor.city }}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Mobil
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    {{ instructor.mobile }}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Telefon
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    {{ instructor.phone }}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Benutzername
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    {{ instructor.username }}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-2\">
                <div class=\"block\">
                    Status
                </div>
            </div>
            <div class=\"col-md-6\">
                <div class=\"block\">
                    {% if instructor.status == 'true' %}
                        Aktiv
                    {% else %}
                        Inaktiv
                    {% endif %}
                </div>
            </div>
        </div>
    </div>
</section>

{% endblock %}
", "instructor/show.html.twig", "/shared/httpd/dicoma/templates/instructor/show.html.twig");
    }
}
