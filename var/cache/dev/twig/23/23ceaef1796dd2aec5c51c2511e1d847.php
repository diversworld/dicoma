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

/* courses/edit.html.twig */
class __TwigTemplate_e3d91a86eebaeaa4ed5a847b46acf803 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "courses/edit.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "courses/edit.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "courses/edit.html.twig", 1);
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

        echo "Edit Courses";
        
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
        <h1>Edit Courses</h1>
        <div class=\"row text-bg-dark text-center\">
            <div class=\"col-md-12\">
                <ul class=\"list-inline\">
                    <li class=\"list-inline-item\">
                        <a href=\"";
        // line 13
        echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_courses_index");
        echo "\" class=\"btn btn-transparent btn-solid-border\">Zurück</a>
                    </li>
                    <li class=\"list-inline-item\">
                        ";
        // line 16
        echo twig_include($this->env, $context, "courses/_delete_form.html.twig");
        echo "
                    </li>
                </ul>
            </div>
        </div>

        ";
        // line 22
        echo twig_include($this->env, $context, "courses/_form.html.twig");
        echo "

    </div>
</section>
<!-- File modal moved from files/index.html.twig to here -->
<div class=\"modal fade\" id=\"fileModal\">
    <div class=\"modal-dialog\">
        <div class=\"modal-content\">
            <!-- Modal Header -->
            <div class=\"modal-header\">
                <h4 class=\"modal-title\">Verfügbare Bilder</h4>
                <button type=\"button\" class=\"close\" data-dismiss=\"modal\">&times;</button>
            </div>

            <!-- Modal Body -->
            <div class=\"modal-body\">
                <div class=\"row\">
                    ";
        // line 39
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["files"]) || array_key_exists("files", $context) ? $context["files"] : (function () { throw new RuntimeError('Variable "files" does not exist.', 39, $this->source); })()));
        foreach ($context['_seq'] as $context["_key"] => $context["file"]) {
            // line 40
            echo "                        <div class=\"img-wrapper col-md-6\">
                            <img src=\"";
            // line 41
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl(("images/kurse/" . $context["file"])), "html", null, true);
            echo "\" width=\"100\" alt=\"";
            echo twig_escape_filter($this->env, $context["file"], "html", null, true);
            echo "\" class=\"img-responsive\" />
                            <button class=\"choose-file-btn\" data-filename=\"";
            // line 42
            echo twig_escape_filter($this->env, $context["file"], "html", null, true);
            echo "\">Choose</button>
                        </div>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['file'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 45
        echo "                </div>
            </div>
            <button type=\"button\" id=\"uploadButton\">Upload new image</button>
            <input type=\"file\" id=\"uploadInput\" style=\"display:none\">
            <!-- Modal footer -->
            <div class=\"modal-footer\">
                <button type=\"button\" class=\"btn btn-icon\" id=\"selectFile\">Select</button>
                <button type=\"button\" class=\"btn btn-icon\" data-dismiss=\"modal\">Close</button>
            </div>

        </div>
    </div>
</div>
";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "courses/edit.html.twig";
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
        return array (  154 => 45,  145 => 42,  139 => 41,  136 => 40,  132 => 39,  112 => 22,  103 => 16,  97 => 13,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Edit Courses{% endblock %}

{% block body %}
<section class=\"about section\">
    <div class=\"container\">
        <h1>Edit Courses</h1>
        <div class=\"row text-bg-dark text-center\">
            <div class=\"col-md-12\">
                <ul class=\"list-inline\">
                    <li class=\"list-inline-item\">
                        <a href=\"{{ path('app_courses_index') }}\" class=\"btn btn-transparent btn-solid-border\">Zurück</a>
                    </li>
                    <li class=\"list-inline-item\">
                        {{ include('courses/_delete_form.html.twig') }}
                    </li>
                </ul>
            </div>
        </div>

        {{ include('courses/_form.html.twig') }}

    </div>
</section>
<!-- File modal moved from files/index.html.twig to here -->
<div class=\"modal fade\" id=\"fileModal\">
    <div class=\"modal-dialog\">
        <div class=\"modal-content\">
            <!-- Modal Header -->
            <div class=\"modal-header\">
                <h4 class=\"modal-title\">Verfügbare Bilder</h4>
                <button type=\"button\" class=\"close\" data-dismiss=\"modal\">&times;</button>
            </div>

            <!-- Modal Body -->
            <div class=\"modal-body\">
                <div class=\"row\">
                    {% for file in files %}
                        <div class=\"img-wrapper col-md-6\">
                            <img src=\"{{ asset('images/kurse/' ~ file) }}\" width=\"100\" alt=\"{{ file }}\" class=\"img-responsive\" />
                            <button class=\"choose-file-btn\" data-filename=\"{{ file }}\">Choose</button>
                        </div>
                    {% endfor %}
                </div>
            </div>
            <button type=\"button\" id=\"uploadButton\">Upload new image</button>
            <input type=\"file\" id=\"uploadInput\" style=\"display:none\">
            <!-- Modal footer -->
            <div class=\"modal-footer\">
                <button type=\"button\" class=\"btn btn-icon\" id=\"selectFile\">Select</button>
                <button type=\"button\" class=\"btn btn-icon\" data-dismiss=\"modal\">Close</button>
            </div>

        </div>
    </div>
</div>
{% endblock %}
", "courses/edit.html.twig", "/shared/httpd/dicoma/templates/courses/edit.html.twig");
    }
}
