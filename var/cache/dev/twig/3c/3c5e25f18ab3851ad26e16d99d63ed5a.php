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

/* profile/index.html.twig */
class __TwigTemplate_d64a423ecf2745c367ac833fb010635b extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "profile/index.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "profile/index.html.twig"));

        $this->parent = $this->loadTemplate("base.html.twig", "profile/index.html.twig", 1);
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

        echo "Mein Profil";
        
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
        <div class=\"row\">
            <div class=\"col-12\">
                <div class=\"about_content\">
                    <h2 class=\"section_title\">Mein Profil</h2>
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-3\">
                <div class=\"about_content\">
                    Name
                </div>
            </div>
            <div class=\"col-6\">
                <div class=\"about_content\">
                    ";
        // line 23
        if ((isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 23, $this->source); })())) {
            // line 24
            echo "                    <p>";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 24, $this->source); })()), "firstname", [], "any", false, false, false, 24), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 24, $this->source); })()), "lastname", [], "any", false, false, false, 24), "html", null, true);
            echo "</p>
                    <p>";
            // line 25
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 25, $this->source); })()), "birthday", [], "any", false, false, false, 25), "d.m.Y"), "html", null, true);
            echo "</p>
                    ";
        }
        // line 27
        echo "                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-3\">
                <div class=\"about_content\">
                    Adresse
                </div>
            </div>
            <div class=\"col-6\">
                <div class=\"about_content\">
                    ";
        // line 38
        if ((isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 38, $this->source); })())) {
            // line 39
            echo "                    <p>";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 39, $this->source); })()), "street", [], "any", false, false, false, 39), "html", null, true);
            echo "</p>
                    <p>";
            // line 40
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 40, $this->source); })()), "postal", [], "any", false, false, false, 40), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 40, $this->source); })()), "city", [], "any", false, false, false, 40), "html", null, true);
            echo "</p>
                    ";
        }
        // line 42
        echo "                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-3\">
                <div class=\"about_content\">
                    Kontaktdaten
                </div>
            </div>
            <div class=\"col-6\">
                <div class=\"about_content\">
                    ";
        // line 53
        if ((isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 53, $this->source); })())) {
            // line 54
            echo "                    <p>";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 54, $this->source); })()), "phone", [], "any", false, false, false, 54), "html", null, true);
            echo "</p>
                    <p>";
            // line 55
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 55, $this->source); })()), "mobile", [], "any", false, false, false, 55), "html", null, true);
            echo "</p>
                    <p>";
            // line 56
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["member"]) || array_key_exists("member", $context) ? $context["member"] : (function () { throw new RuntimeError('Variable "member" does not exist.', 56, $this->source); })()), "email", [], "any", false, false, false, 56), "html", null, true);
            echo "</p>
                    ";
        }
        // line 58
        echo "                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-3\">
                <div class=\"about_content\">
                    Benutzerdaten
                </div>
            </div>
            <div class=\"col-6\">
                <div class=\"about_content\">
                    <p>";
        // line 69
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (isset($context["user"]) || array_key_exists("user", $context) ? $context["user"] : (function () { throw new RuntimeError('Variable "user" does not exist.', 69, $this->source); })()), "username", [], "any", false, false, false, 69), "html", null, true);
        echo "</p>
                    <ul>
                        ";
        // line 71
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (isset($context["user"]) || array_key_exists("user", $context) ? $context["user"] : (function () { throw new RuntimeError('Variable "user" does not exist.', 71, $this->source); })()), "roles", [], "any", false, false, false, 71));
        foreach ($context['_seq'] as $context["_key"] => $context["role"]) {
            // line 72
            echo "                        <li>";
            echo twig_escape_filter($this->env, $context["role"], "html", null, true);
            echo "</li>
                        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['role'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 74
        echo "                    </ul>
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
        return "profile/index.html.twig";
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
        return array (  208 => 74,  199 => 72,  195 => 71,  190 => 69,  177 => 58,  172 => 56,  168 => 55,  163 => 54,  161 => 53,  148 => 42,  141 => 40,  136 => 39,  134 => 38,  121 => 27,  116 => 25,  109 => 24,  107 => 23,  88 => 6,  78 => 5,  59 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends 'base.html.twig' %}

{% block title %}Mein Profil{% endblock %}

{% block body %}
<section class=\"about section\">
    <div class=\"container\">
        <div class=\"row\">
            <div class=\"col-12\">
                <div class=\"about_content\">
                    <h2 class=\"section_title\">Mein Profil</h2>
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-3\">
                <div class=\"about_content\">
                    Name
                </div>
            </div>
            <div class=\"col-6\">
                <div class=\"about_content\">
                    {%  if member %}
                    <p>{{ member.firstname }} {{ member.lastname }}</p>
                    <p>{{ member.birthday|date('d.m.Y') }}</p>
                    {% endif %}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-3\">
                <div class=\"about_content\">
                    Adresse
                </div>
            </div>
            <div class=\"col-6\">
                <div class=\"about_content\">
                    {%  if member %}
                    <p>{{ member.street }}</p>
                    <p>{{ member.postal }} {{ member.city }}</p>
                    {% endif %}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-3\">
                <div class=\"about_content\">
                    Kontaktdaten
                </div>
            </div>
            <div class=\"col-6\">
                <div class=\"about_content\">
                    {%  if member %}
                    <p>{{ member.phone }}</p>
                    <p>{{ member.mobile }}</p>
                    <p>{{ member.email }}</p>
                    {% endif %}
                </div>
            </div>
        </div>
        <div class=\"row\">
            <div class=\"col-3\">
                <div class=\"about_content\">
                    Benutzerdaten
                </div>
            </div>
            <div class=\"col-6\">
                <div class=\"about_content\">
                    <p>{{ user.username }}</p>
                    <ul>
                        {% for role in user.roles %}
                        <li>{{ role }}</li>
                        {% endfor %}
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
{% endblock %}
", "profile/index.html.twig", "/shared/httpd/dicoma/templates/profile/index.html.twig");
    }
}
