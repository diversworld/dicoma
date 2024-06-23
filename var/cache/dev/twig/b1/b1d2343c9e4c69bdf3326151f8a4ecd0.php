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

/* courses/index.html.twig */
class __TwigTemplate_af2a9aefbd3ea6ed2a7205f87ce8fd0c extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "courses/index.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "courses/index.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "courses/index.html.twig", 1);
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

        echo "Courses index";
        
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
        <h1>Kursliste</h1>
        <div class=\"col-md-3\">
            <div class=\"block\">
                <a href=\"";
        // line 11
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_new");
        echo "\" class=\"btn btn-transparent btn-solid-border\">Neuen Kurs anlegen</a>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-12\">
                <div class=\"block\">
                    <div class=\"portfolio-menu\">
                        <div class=\"btn-group btn-group-toggle justify-content-center\" data-toggle=\"buttons\">
                            <label class=\"btn btn-sm btn-primary active\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"all\" checked=\"checked\" />Alle
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"beginner\" />Beginner
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"aufbau\" />Aufbaukure
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"sonder\" />Sonderkurse
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"mischgas\" />Mischgas Kurse
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"technisch\" />Technische Kurse
                            </label>
                        </div>
                    </div>
                    <div class=\"row shuffle-wrapper\">
                        ";
        // line 40
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["courses"]) || array_key_exists("courses", $context) ? $context["courses"] : (function () { throw new RuntimeError('Variable "courses" does not exist.', 40, $this->source); })()));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["course"]) {
            // line 41
            echo "                        <div class=\"col-lg-4 col-sm-6 portfolio-item shuffle-item\" data-groups=\"[&quot;";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["course"], "category", [], "any", false, false, false, 41), "html", null, true);
            echo "&quot;]\">
                            <img src=\"";
            // line 42
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl(("images/kurse/" . twig_get_attribute($this->env, $this->source, $context["course"], "image", [], "any", false, false, false, 42))), "html", null, true);
            echo "\" alt=\"\">
                            <div class=\"portfolio-hover\">
                                <div class=\"portfolio-content\">
                                    <a class=\"h3\" href=\"";
            // line 45
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_show", ["id" => twig_get_attribute($this->env, $this->source, $context["course"], "id", [], "any", false, false, false, 45)]), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["course"], "title", [], "any", false, false, false, 45), "html", null, true);
            echo "</a>
                                </div>
                            </div>
                        </div>
                        ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 50
            echo "                            <div class=\"portfolio-content\">
                                <p>Keine Kurse gefunden</p>
                            </div>
                        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['course'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 54
        echo "                    </div>
                </div>
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
        return "courses/index.html.twig";
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
        return array (  164 => 54,  155 => 50,  143 => 45,  137 => 42,  132 => 41,  127 => 40,  95 => 11,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Courses index{% endblock %}

{% block body %}
<section class=\"portfolio-work\">
    <div class=\"container\">
        <h1>Kursliste</h1>
        <div class=\"col-md-3\">
            <div class=\"block\">
                <a href=\"{{ path('app_courses_new') }}\" class=\"btn btn-transparent btn-solid-border\">Neuen Kurs anlegen</a>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-md-12\">
                <div class=\"block\">
                    <div class=\"portfolio-menu\">
                        <div class=\"btn-group btn-group-toggle justify-content-center\" data-toggle=\"buttons\">
                            <label class=\"btn btn-sm btn-primary active\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"all\" checked=\"checked\" />Alle
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"beginner\" />Beginner
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"aufbau\" />Aufbaukure
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"sonder\" />Sonderkurse
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"mischgas\" />Mischgas Kurse
                            </label>
                            <label class=\"btn btn-sm btn-primary\">
                                <input type=\"radio\" name=\"shuffle-filter\" value=\"technisch\" />Technische Kurse
                            </label>
                        </div>
                    </div>
                    <div class=\"row shuffle-wrapper\">
                        {% for course in courses %}
                        <div class=\"col-lg-4 col-sm-6 portfolio-item shuffle-item\" data-groups=\"[&quot;{{  course.category }}&quot;]\">
                            <img src=\"{{asset('images/kurse/' ~ course.image)}}\" alt=\"\">
                            <div class=\"portfolio-hover\">
                                <div class=\"portfolio-content\">
                                    <a class=\"h3\" href=\"{{ path('app_courses_show', {'id': course.id}) }}\">{{ course.title }}</a>
                                </div>
                            </div>
                        </div>
                        {% else %}
                            <div class=\"portfolio-content\">
                                <p>Keine Kurse gefunden</p>
                            </div>
                        {% endfor %}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
{% endblock %}
", "courses/index.html.twig", "/shared/httpd/dicoma/templates/courses/index.html.twig");
    }
}
