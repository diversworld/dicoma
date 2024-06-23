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

/* schedule/_form.html.twig */
class __TwigTemplate_a6890167aa1a435beee19c2eeb211ccb extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "schedule/_form.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "schedule/_form.html.twig"));

        // line 1
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 1, $this->source); })()), 'errors');
        echo "
";
        // line 2
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 2, $this->source); })()), 'form_start');
        echo "
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        ";
        // line 5
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 5, $this->source); })()), "title", [], "any", false, false, false, 5), 'label');
        echo "
        ";
        // line 6
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 6, $this->source); })()), "title", [], "any", false, false, false, 6), 'widget');
        echo "
    </div>
    <div class=\"col-md-4\">
        ";
        // line 9
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 9, $this->source); })()), "courses", [], "any", false, false, false, 9), 'label');
        echo "
        ";
        // line 10
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 10, $this->source); })()), "courses", [], "any", false, false, false, 10), 'widget');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        ";
        // line 15
        if (twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 15, $this->source); })()), "image", [], "any", false, false, false, 15)) {
            // line 16
            echo "        <img src=\"";
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl((("images/kurse/" . "/") . twig_get_attribute($this->env, $this->source, (isset($context["schedule"]) || array_key_exists("schedule", $context) ? $context["schedule"] : (function () { throw new RuntimeError('Variable "schedule" does not exist.', 16, $this->source); })()), "image", [], "any", false, false, false, 16))), "html", null, true);
            echo "\" width=\"200px\" alt=\"Brevetbild\">
        ";
        }
        // line 18
        echo "    </div>
    <div class=\"col-md-5\">
        ";
        // line 20
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 20, $this->source); })()), "image", [], "any", false, false, false, 20), 'label');
        echo "
        ";
        // line 21
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 21, $this->source); })()), "image", [], "any", false, false, false, 21), 'widget');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        ";
        // line 26
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 26, $this->source); })()), "price", [], "any", false, false, false, 26), 'label');
        echo "
        ";
        // line 27
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 27, $this->source); })()), "price", [], "any", false, false, false, 27), 'widget');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        ";
        // line 32
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 32, $this->source); })()), "startDate", [], "any", false, false, false, 32), 'label');
        echo "
        ";
        // line 33
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 33, $this->source); })()), "startDate", [], "any", false, false, false, 33), 'widget');
        echo "
    </div>
    <div class=\"col-md-2\">
        ";
        // line 36
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 36, $this->source); })()), "startTime", [], "any", false, false, false, 36), 'label');
        echo "
        ";
        // line 37
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 37, $this->source); })()), "startTime", [], "any", false, false, false, 37), 'widget');
        echo "
    </div>
    <div class=\"col-md-2\">
        ";
        // line 40
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 40, $this->source); })()), "duration", [], "any", false, false, false, 40), 'label');
        echo "
        ";
        // line 41
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 41, $this->source); })()), "duration", [], "any", false, false, false, 41), 'widget');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        <div class=\"block\">
            ";
        // line 47
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 47, $this->source); })()), "location", [], "any", false, false, false, 47), 'label');
        echo "
            ";
        // line 48
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 48, $this->source); })()), "location", [], "any", false, false, false, 48), 'widget');
        echo "
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        ";
        // line 54
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 54, $this->source); })()), "locationStreet", [], "any", false, false, false, 54), 'label');
        echo "
        ";
        // line 55
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 55, $this->source); })()), "locationStreet", [], "any", false, false, false, 55), 'widget');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        ";
        // line 60
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 60, $this->source); })()), "locationPostal", [], "any", false, false, false, 60), 'label');
        echo "
        ";
        // line 61
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 61, $this->source); })()), "locationPostal", [], "any", false, false, false, 61), 'widget');
        echo "
    </div>
    <div class=\"col-md-4\">
        ";
        // line 64
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 64, $this->source); })()), "locationCity", [], "any", false, false, false, 64), 'label');
        echo "
        ";
        // line 65
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 65, $this->source); })()), "locationCity", [], "any", false, false, false, 65), 'widget');
        echo "
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-8\">
        ";
        // line 70
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 70, $this->source); })()), "notes", [], "any", false, false, false, 70), 'label');
        echo "
        ";
        // line 71
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 71, $this->source); })()), "notes", [], "any", false, false, false, 71), 'widget');
        echo "
    </div>
</div>
<div class=\"row form-group\">&nbsp;</div>
<div class=\"row form-group\">
    <div class=\"col-md-6\">
        <div class=\"block\">
            ";
        // line 78
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 78, $this->source); })()), "submit", [], "any", false, false, false, 78), 'widget');
        echo "
        </div>
    </div>
</div>
";
        // line 82
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 82, $this->source); })()), 'form_end');
        echo "
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "schedule/_form.html.twig";
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
        return array (  212 => 82,  205 => 78,  195 => 71,  191 => 70,  183 => 65,  179 => 64,  173 => 61,  169 => 60,  161 => 55,  157 => 54,  148 => 48,  144 => 47,  135 => 41,  131 => 40,  125 => 37,  121 => 36,  115 => 33,  111 => 32,  103 => 27,  99 => 26,  91 => 21,  87 => 20,  83 => 18,  77 => 16,  75 => 15,  67 => 10,  63 => 9,  57 => 6,  53 => 5,  47 => 2,  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{{ form_errors(form) }}
{{ form_start(form) }}
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        {{ form_label(form.title) }}
        {{ form_widget(form.title) }}
    </div>
    <div class=\"col-md-4\">
        {{ form_label(form.courses) }}
        {{ form_widget(form.courses) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        {% if schedule.image %}
        <img src=\"{{ asset('images/kurse/'  ~ '/' ~ schedule.image) }}\" width=\"200px\" alt=\"Brevetbild\">
        {% endif %}
    </div>
    <div class=\"col-md-5\">
        {{ form_label(form.image) }}
        {{ form_widget(form.image) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        {{ form_label(form.price ) }}
        {{ form_widget(form.price) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        {{ form_label(form.startDate) }}
        {{ form_widget(form.startDate) }}
    </div>
    <div class=\"col-md-2\">
        {{ form_label(form.startTime) }}
        {{ form_widget(form.startTime) }}
    </div>
    <div class=\"col-md-2\">
        {{ form_label(form.duration) }}
        {{ form_widget(form.duration) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        <div class=\"block\">
            {{ form_label(form.location) }}
            {{ form_widget(form.location) }}
        </div>
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-4\">
        {{ form_label(form.locationStreet) }}
        {{ form_widget(form.locationStreet) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-2\">
        {{ form_label(form.locationPostal) }}
        {{ form_widget(form.locationPostal) }}
    </div>
    <div class=\"col-md-4\">
        {{ form_label(form.locationCity) }}
        {{ form_widget(form.locationCity) }}
    </div>
</div>
<div class=\"row form-group\">
    <div class=\"col-md-8\">
        {{ form_label(form.notes) }}
        {{ form_widget(form.notes) }}
    </div>
</div>
<div class=\"row form-group\">&nbsp;</div>
<div class=\"row form-group\">
    <div class=\"col-md-6\">
        <div class=\"block\">
            {{ form_widget(form.submit) }}
        </div>
    </div>
</div>
{{ form_end(form) }}
", "schedule/_form.html.twig", "/shared/httpd/dicoma/templates/schedule/_form.html.twig");
    }
}
