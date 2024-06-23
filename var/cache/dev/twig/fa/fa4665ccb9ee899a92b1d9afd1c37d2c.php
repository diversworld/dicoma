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

/* instructor/index.html.twig */
class __TwigTemplate_025af8fb33932a45012b0ffec9baa82d extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "instructor/index.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "instructor/index.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "instructor/index.html.twig", 1);
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

        echo "Instructor index";
        
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
        <h1> Übersicht Instruktoren</h1>
        <div class=\"row\">
            ";
        // line 10
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
            // line 11
            echo "                <div class=\"col-md-3\">
                    <div class=\"block\">
                        <a href=\"";
            // line 13
            echo $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_new");
            echo "\" class=\"btn btn-transparent btn-solid-border\">Neuen Instruktor erstellen</a>
                    </div>
                </div>
            ";
        }
        // line 17
        echo "        </div>
        <div class=\"row\">
            &nbsp;
        </div>

        <div class=\"row row-cols-3 row-cols-md-3 g-3\">
            ";
        // line 23
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["instructors"]) || array_key_exists("instructors", $context) ? $context["instructors"] : (function () { throw new RuntimeError('Variable "instructors" does not exist.', 23, $this->source); })()));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["instructor"]) {
            // line 24
            echo "                <!--
                <div class=\"col\">
                    <div class=\"card\">
                        <svg class=\"bd-placeholder-img card-img-top\" width=\"100%\" height=\"140\" xmlns=\"http://www.w3.org/2000/svg\" role=\"img\" aria-label=\"Placeholder: Image cap\" preserveAspectRatio=\"xMidYMid slice\" focusable=\"false\">
                            <title>";
            // line 28
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "firstname", [], "any", false, false, false, 28), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "lastname", [], "any", false, false, false, 28), "html", null, true);
            echo "</title>
                            <rect width=\"100%\" height=\"100%\" fill=\"#868e96\"></rect>
                            <text x=\"40%\" y=\"50%\" fill=\"#dee2e6\" dy=\".3em\">Bild</text>
                        </svg>
                        <div class=\"card-body\">
                            <h5 class=\"card-title\">";
            // line 33
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "firstname", [], "any", false, false, false, 33), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "lastname", [], "any", false, false, false, 33), "html", null, true);
            echo "</h5>
                            <p class=\"card-text\">
                                Telefon: ";
            // line 35
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "phone", [], "any", false, false, false, 35), "html", null, true);
            echo "<br>
                                Mobil: ";
            // line 36
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "mobile", [], "any", false, false, false, 36), "html", null, true);
            echo "
                            </p>
                            <p class=\"card-text\">
                                E-Mail: ";
            // line 39
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "email", [], "any", false, false, false, 39), "html", null, true);
            echo "
                            </p>
                            <p class=\"card-text\">
                                Adresse:<br>
                                ";
            // line 43
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "street", [], "any", false, false, false, 43), "html", null, true);
            echo "<br>
                                ";
            // line 44
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "postal", [], "any", false, false, false, 44), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "city", [], "any", false, false, false, 44), "html", null, true);
            echo "
                            </p>
                            <p class=\"card-text\">
                                ";
            // line 47
            if ((twig_get_attribute($this->env, $this->source, $context["instructor"], "published", [], "any", false, false, false, 47) == "true")) {
                // line 48
                echo "                                    Status: Aktiv
                                ";
            } else {
                // line 50
                echo "                                    Status: Inaktiv
                                ";
            }
            // line 51
            echo "</p>
                            <p class=\"card-text\">
                                <a href=\"";
            // line 53
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_show", ["id" => twig_get_attribute($this->env, $this->source, $context["instructor"], "id", [], "any", false, false, false, 53)]), "html", null, true);
            echo "\"><i class=\"ion-ios-eye\"></i></a>
                                ";
            // line 54
            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
                // line 55
                echo "                                    <a href=\"";
                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["instructor"], "id", [], "any", false, false, false, 55)]), "html", null, true);
                echo "\"><i class=\"ion-edit\"></i></a>
                                ";
            }
            // line 57
            echo "                            </p>
                        </div>
                    </div>
                </div>
-->
                <div class=\"col\">
                    <div class=\"card\">
                        <div class=\"row g-0\">
                            <div class=\"col-md-4\">
                                <svg class=\"bd-placeholder-img card-img-top\" width=\"100%\" height=\"200\" xmlns=\"http://www.w3.org/2000/svg\" role=\"img\" aria-label=\"Placeholder: Image cap\" preserveAspectRatio=\"xMidYMid slice\" focusable=\"false\">
                                    <title>";
            // line 67
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "firstname", [], "any", false, false, false, 67), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "lastname", [], "any", false, false, false, 67), "html", null, true);
            echo "</title>
                                    <rect width=\"100%\" height=\"100%\" fill=\"#868e96\"></rect>
                                    <text x=\"50%\" y=\"50%\" fill=\"#dee2e6\" dy=\".3em\">";
            // line 69
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "firstname", [], "any", false, false, false, 69), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "lastname", [], "any", false, false, false, 69), "html", null, true);
            echo "</text>
                                </svg>
                            </div>
                            <div class=\"col-md-8\">
                                <div class=\"card-body\">
                                    <h5 class=\"card-title\">";
            // line 74
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "firstname", [], "any", false, false, false, 74), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "lastname", [], "any", false, false, false, 74), "html", null, true);
            echo "</h5>
                                    <p class=\"card-text\">
                                    Telefon:<br>";
            // line 76
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "phone", [], "any", false, false, false, 76), "html", null, true);
            echo "<br>
                                    Mobil:<br>";
            // line 77
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "mobile", [], "any", false, false, false, 77), "html", null, true);
            echo "
                                </p>
                                <p class=\"card-text\">
                                    E-Mail:<br>";
            // line 80
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "email", [], "any", false, false, false, 80), "html", null, true);
            echo "
                                </p>
                                <p class=\"card-text\">
                                    Adresse:<br>
                                    ";
            // line 84
            if (twig_get_attribute($this->env, $this->source, $context["instructor"], "street", [], "any", false, false, false, 84)) {
                // line 85
                echo "                                    ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "street", [], "any", false, false, false, 85), "html", null, true);
                echo "<br>
                                    ";
                // line 86
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "postal", [], "any", false, false, false, 86), "html", null, true);
                echo " ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "city", [], "any", false, false, false, 86), "html", null, true);
                echo "
                                    ";
            } else {
                // line 88
                echo "                                        &nbsp;<br>&nbsp;
                                    ";
            }
            // line 90
            echo "                                </p>
                                    <p class=\"card-text\">
                                        ";
            // line 92
            if ((twig_get_attribute($this->env, $this->source, $context["instructor"], "published", [], "any", false, false, false, 92) == "true")) {
                // line 93
                echo "                                            Status: Aktiv
                                        ";
            } else {
                // line 95
                echo "                                            Status: Inaktiv
                                        ";
            }
            // line 96
            echo "</p>
                                    <p class=\"card-text\">
                                        <a href=\"";
            // line 98
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_show", ["id" => twig_get_attribute($this->env, $this->source, $context["instructor"], "id", [], "any", false, false, false, 98)]), "html", null, true);
            echo "\"><i class=\"ion-ios-eye\"></i></a>
                                        ";
            // line 99
            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
                // line 100
                echo "                                            <a href=\"";
                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["instructor"], "id", [], "any", false, false, false, 100)]), "html", null, true);
                echo "\"><i class=\"ion-edit\"></i></a>
                                        ";
            }
            // line 102
            echo "                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 109
            echo "                <p colspan=\"12\">no records found</p>
            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['instructor'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 111
        echo "        </div>

        <div class=\"row\">
            <div class=\"col-md-12\">
                <table class=\"table\">
                    <thead>
                        <tr>
                            <th>Firstname</th>
                            <th>Lastname</th>
                            <th>E-Mail</th>
                            <th>Status</th>
                            <th>actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    ";
        // line 126
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["instructors"]) || array_key_exists("instructors", $context) ? $context["instructors"] : (function () { throw new RuntimeError('Variable "instructors" does not exist.', 126, $this->source); })()));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["instructor"]) {
            // line 127
            echo "                        <tr>
                            <td>";
            // line 128
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "firstname", [], "any", false, false, false, 128), "html", null, true);
            echo "</td>
                            <td>";
            // line 129
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "lastname", [], "any", false, false, false, 129), "html", null, true);
            echo "</td>
                            <td>";
            // line 130
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["instructor"], "email", [], "any", false, false, false, 130), "html", null, true);
            echo "</td>
                            <td>
                                ";
            // line 132
            if ((twig_get_attribute($this->env, $this->source, $context["instructor"], "published", [], "any", false, false, false, 132) == "true")) {
                // line 133
                echo "                                Aktiv
                                ";
            } else {
                // line 135
                echo "                                Inaktiv
                                ";
            }
            // line 137
            echo "                            </td>
                            <td>
                                <a href=\"";
            // line 139
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_show", ["id" => twig_get_attribute($this->env, $this->source, $context["instructor"], "id", [], "any", false, false, false, 139)]), "html", null, true);
            echo "\"><i class=\"ion-ios-eye\"></i></a>
                                ";
            // line 140
            if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
                // line 141
                echo "                                    <a href=\"";
                echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\RoutingExtension']->getPath("app_instructor_edit", ["id" => twig_get_attribute($this->env, $this->source, $context["instructor"], "id", [], "any", false, false, false, 141)]), "html", null, true);
                echo "\"><i class=\"ion-edit\"></i></a>
                                ";
            }
            // line 143
            echo "                            </td>
                        </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 146
            echo "                        <tr>
                            <td colspan=\"12\">no records found</td>
                        </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['instructor'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 150
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
        return "instructor/index.html.twig";
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
        return array (  397 => 150,  388 => 146,  381 => 143,  375 => 141,  373 => 140,  369 => 139,  365 => 137,  361 => 135,  357 => 133,  355 => 132,  350 => 130,  346 => 129,  342 => 128,  339 => 127,  334 => 126,  317 => 111,  310 => 109,  299 => 102,  293 => 100,  291 => 99,  287 => 98,  283 => 96,  279 => 95,  275 => 93,  273 => 92,  269 => 90,  265 => 88,  258 => 86,  253 => 85,  251 => 84,  244 => 80,  238 => 77,  234 => 76,  227 => 74,  217 => 69,  210 => 67,  198 => 57,  192 => 55,  190 => 54,  186 => 53,  182 => 51,  178 => 50,  174 => 48,  172 => 47,  164 => 44,  160 => 43,  153 => 39,  147 => 36,  143 => 35,  136 => 33,  126 => 28,  120 => 24,  115 => 23,  107 => 17,  100 => 13,  96 => 11,  94 => 10,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Instructor index{% endblock %}

{% block body %}
<section class=\"about section\">
    <div class=\"container\">
        <h1> Übersicht Instruktoren</h1>
        <div class=\"row\">
            {%  if is_granted('ROLE_ADMIN') %}
                <div class=\"col-md-3\">
                    <div class=\"block\">
                        <a href=\"{{ path('app_instructor_new') }}\" class=\"btn btn-transparent btn-solid-border\">Neuen Instruktor erstellen</a>
                    </div>
                </div>
            {% endif %}
        </div>
        <div class=\"row\">
            &nbsp;
        </div>

        <div class=\"row row-cols-3 row-cols-md-3 g-3\">
            {% for instructor in instructors %}
                <!--
                <div class=\"col\">
                    <div class=\"card\">
                        <svg class=\"bd-placeholder-img card-img-top\" width=\"100%\" height=\"140\" xmlns=\"http://www.w3.org/2000/svg\" role=\"img\" aria-label=\"Placeholder: Image cap\" preserveAspectRatio=\"xMidYMid slice\" focusable=\"false\">
                            <title>{{ instructor.firstname }} {{ instructor.lastname }}</title>
                            <rect width=\"100%\" height=\"100%\" fill=\"#868e96\"></rect>
                            <text x=\"40%\" y=\"50%\" fill=\"#dee2e6\" dy=\".3em\">Bild</text>
                        </svg>
                        <div class=\"card-body\">
                            <h5 class=\"card-title\">{{ instructor.firstname }} {{ instructor.lastname }}</h5>
                            <p class=\"card-text\">
                                Telefon: {{ instructor.phone }}<br>
                                Mobil: {{ instructor.mobile }}
                            </p>
                            <p class=\"card-text\">
                                E-Mail: {{ instructor.email }}
                            </p>
                            <p class=\"card-text\">
                                Adresse:<br>
                                {{ instructor.street }}<br>
                                {{ instructor.postal }} {{ instructor.city }}
                            </p>
                            <p class=\"card-text\">
                                {% if instructor.published =='true' %}
                                    Status: Aktiv
                                {% else %}
                                    Status: Inaktiv
                                {% endif %}</p>
                            <p class=\"card-text\">
                                <a href=\"{{ path('app_instructor_show', {'id': instructor.id}) }}\"><i class=\"ion-ios-eye\"></i></a>
                                {%  if is_granted('ROLE_ADMIN') %}
                                    <a href=\"{{ path('app_instructor_edit', {'id': instructor.id}) }}\"><i class=\"ion-edit\"></i></a>
                                {% endif %}
                            </p>
                        </div>
                    </div>
                </div>
-->
                <div class=\"col\">
                    <div class=\"card\">
                        <div class=\"row g-0\">
                            <div class=\"col-md-4\">
                                <svg class=\"bd-placeholder-img card-img-top\" width=\"100%\" height=\"200\" xmlns=\"http://www.w3.org/2000/svg\" role=\"img\" aria-label=\"Placeholder: Image cap\" preserveAspectRatio=\"xMidYMid slice\" focusable=\"false\">
                                    <title>{{ instructor.firstname }} {{ instructor.lastname }}</title>
                                    <rect width=\"100%\" height=\"100%\" fill=\"#868e96\"></rect>
                                    <text x=\"50%\" y=\"50%\" fill=\"#dee2e6\" dy=\".3em\">{{ instructor.firstname }} {{ instructor.lastname }}</text>
                                </svg>
                            </div>
                            <div class=\"col-md-8\">
                                <div class=\"card-body\">
                                    <h5 class=\"card-title\">{{ instructor.firstname }} {{ instructor.lastname }}</h5>
                                    <p class=\"card-text\">
                                    Telefon:<br>{{ instructor.phone }}<br>
                                    Mobil:<br>{{ instructor.mobile }}
                                </p>
                                <p class=\"card-text\">
                                    E-Mail:<br>{{ instructor.email }}
                                </p>
                                <p class=\"card-text\">
                                    Adresse:<br>
                                    {%  if instructor.street %}
                                    {{ instructor.street }}<br>
                                    {{ instructor.postal }} {{ instructor.city }}
                                    {% else %}
                                        &nbsp;<br>&nbsp;
                                    {% endif %}
                                </p>
                                    <p class=\"card-text\">
                                        {% if instructor.published =='true' %}
                                            Status: Aktiv
                                        {% else %}
                                            Status: Inaktiv
                                        {% endif %}</p>
                                    <p class=\"card-text\">
                                        <a href=\"{{ path('app_instructor_show', {'id': instructor.id}) }}\"><i class=\"ion-ios-eye\"></i></a>
                                        {%  if is_granted('ROLE_ADMIN') %}
                                            <a href=\"{{ path('app_instructor_edit', {'id': instructor.id}) }}\"><i class=\"ion-edit\"></i></a>
                                        {% endif %}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {% else %}
                <p colspan=\"12\">no records found</p>
            {% endfor %}
        </div>

        <div class=\"row\">
            <div class=\"col-md-12\">
                <table class=\"table\">
                    <thead>
                        <tr>
                            <th>Firstname</th>
                            <th>Lastname</th>
                            <th>E-Mail</th>
                            <th>Status</th>
                            <th>actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    {% for instructor in instructors %}
                        <tr>
                            <td>{{ instructor.firstname }}</td>
                            <td>{{ instructor.lastname }}</td>
                            <td>{{ instructor.email }}</td>
                            <td>
                                {% if instructor.published =='true' %}
                                Aktiv
                                {% else %}
                                Inaktiv
                                {% endif %}
                            </td>
                            <td>
                                <a href=\"{{ path('app_instructor_show', {'id': instructor.id}) }}\"><i class=\"ion-ios-eye\"></i></a>
                                {%  if is_granted('ROLE_ADMIN') %}
                                    <a href=\"{{ path('app_instructor_edit', {'id': instructor.id}) }}\"><i class=\"ion-edit\"></i></a>
                                {% endif %}
                            </td>
                        </tr>
                    {% else %}
                        <tr>
                            <td colspan=\"12\">no records found</td>
                        </tr>
                    {% endfor %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
{% endblock %}
", "instructor/index.html.twig", "/shared/httpd/dicoma/templates/instructor/index.html.twig");
    }
}
