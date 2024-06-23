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

/* booking/_form.html.twig */
class __TwigTemplate_71ef450dea55258039856eaa9d61ec39 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "booking/_form.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "booking/_form.html.twig"));

        // line 1
        echo "<div class=\"col-lg-8 mt-5 mt-lg-0\">
    <br>
    ";
        // line 3
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 3, $this->source); })()), 'form_start');
        echo "
    <div class=\"row g-3\">
        <div class=\"col-md-3 form-group\">
            <label class=\"required form_label\">Buchungsnummer</label>
            <input
                name=\"";
        // line 8
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\FormExtension']->getFieldName(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 8, $this->source); })()), "bookingnumber", [], "any", false, false, false, 8)), "html", null, true);
        echo "\"
                value=\"";
        // line 9
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\FormExtension']->getFieldValue(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 9, $this->source); })()), "bookingnumber", [], "any", false, false, false, 9)), "html", null, true);
        echo "\"
                class=\"form-control\"
            >
        </div>
    </div>
    <div class=\"row g-3\">
        <div class=\"col-md-3 form-group\">
            <label class=\"required form_label\">Datum der Buchung</label>
            ";
        // line 17
        echo $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->searchAndRenderBlock(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 17, $this->source); })()), "bookingdate", [], "any", false, false, false, 17), 'widget', ["attr" => ["class" => "form-control"]]);
        echo "
        </div>
    </div>
    <!-- Anzeige des Buchungsstatus (editierbar für Admin) -->
    <div class=\"row g-3\">
        <div class=\"col-md-4 form-group\">
            <label class=\"required form_label\">Status der Buchung</label>
            <select name=\"";
        // line 24
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\FormExtension']->getFieldName(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 24, $this->source); })()), "status", [], "any", false, false, false, 24)), "html", null, true);
        echo "\" class=\"form-control\">
                <option value=\"\">Status auswählen</option>
                ";
        // line 26
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable($this->extensions['Symfony\Bridge\Twig\Extension\FormExtension']->getFieldChoices(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 26, $this->source); })()), "status", [], "any", false, false, false, 26)));
        foreach ($context['_seq'] as $context["label"] => $context["value"]) {
            // line 27
            echo "                    <option value=\"";
            echo twig_escape_filter($this->env, $context["value"], "html", null, true);
            echo "\" ";
            if (($context["value"] == twig_get_attribute($this->env, $this->source, (isset($context["booking"]) || array_key_exists("booking", $context) ? $context["booking"] : (function () { throw new RuntimeError('Variable "booking" does not exist.', 27, $this->source); })()), "status", [], "any", false, false, false, 27))) {
                echo "selected";
            }
            echo ">";
            echo twig_escape_filter($this->env, $context["label"], "html", null, true);
            echo "</option>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['label'], $context['value'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 29
        echo "            </select>
        </div>
    </div>
    <div class=\"row g-3\">
        <div class=\"col-md-6 form-group\">
            <label for=\"courseSelect\" class=\"form-label\">Kurstermin(e)</label>
            <select name=\"";
        // line 35
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\FormExtension']->getFieldName(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 35, $this->source); })()), "schedule", [], "any", false, false, false, 35)), "html", null, true);
        echo "\" class=\"form-select form-control\">
                <option value=\"\" >Kurstermin(e) auswählen</option>
                ";
        // line 37
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable($this->extensions['Symfony\Bridge\Twig\Extension\FormExtension']->getFieldChoices(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 37, $this->source); })()), "schedule", [], "any", false, false, false, 37)));
        foreach ($context['_seq'] as $context["label"] => $context["value"]) {
            // line 38
            echo "                    ";
            if ( !(null === twig_get_attribute($this->env, $this->source, (isset($context["booking"]) || array_key_exists("booking", $context) ? $context["booking"] : (function () { throw new RuntimeError('Variable "booking" does not exist.', 38, $this->source); })()), "schedule", [], "any", false, false, false, 38))) {
                // line 39
                echo "                        <option value=\"";
                echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                echo "\" ";
                if (($context["value"] == twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["booking"]) || array_key_exists("booking", $context) ? $context["booking"] : (function () { throw new RuntimeError('Variable "booking" does not exist.', 39, $this->source); })()), "schedule", [], "any", false, false, false, 39), "id", [], "any", false, false, false, 39))) {
                    echo "selected";
                }
                echo ">";
                echo twig_escape_filter($this->env, $context["label"], "html", null, true);
                echo "</option>
                    ";
            } else {
                // line 41
                echo "                        <option value=\"";
                echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, $context["label"], "html", null, true);
                echo "</option>
                    ";
            }
            // line 43
            echo "                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['label'], $context['value'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 44
        echo "            </select>
            <div id=\"emailHelp\" class=\"form-text\">Bitte den zugehörigen Kurs auswählen.</div>
        </div>
    </div>
    <div class=\"row g-3\">
        <div class=\"col-md-6 form-group\">
            <label for=\"studentSelect\" class=\"form_label\">Schüler</label>
            ";
        // line 51
        if ($this->extensions['Symfony\Bridge\Twig\Extension\SecurityExtension']->isGranted("ROLE_ADMIN")) {
            // line 52
            echo "                <select name=\"";
            echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\FormExtension']->getFieldName(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 52, $this->source); })()), "students", [], "any", false, false, false, 52)), "html", null, true);
            echo "\" class=\"form-select form-control\">
                    <option value=\"\">Schüler auswählen</option>
                    ";
            // line 54
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->extensions['Symfony\Bridge\Twig\Extension\FormExtension']->getFieldChoices(twig_get_attribute($this->env, $this->source, (isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 54, $this->source); })()), "students", [], "any", false, false, false, 54)));
            foreach ($context['_seq'] as $context["label"] => $context["value"]) {
                // line 55
                echo "                        ";
                if ( !(null === twig_get_attribute($this->env, $this->source, (isset($context["booking"]) || array_key_exists("booking", $context) ? $context["booking"] : (function () { throw new RuntimeError('Variable "booking" does not exist.', 55, $this->source); })()), "students", [], "any", false, false, false, 55))) {
                    // line 56
                    echo "                            <option value=\"";
                    echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                    echo "\" ";
                    if (($context["value"] == twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["booking"]) || array_key_exists("booking", $context) ? $context["booking"] : (function () { throw new RuntimeError('Variable "booking" does not exist.', 56, $this->source); })()), "students", [], "any", false, false, false, 56), "id", [], "any", false, false, false, 56))) {
                        echo "selected";
                    }
                    echo ">";
                    echo twig_escape_filter($this->env, $context["label"], "html", null, true);
                    echo "</option>
                        ";
                } else {
                    // line 58
                    echo "                            <option value=\"";
                    echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, $context["label"], "html", null, true);
                    echo "</option>
                        ";
                }
                // line 60
                echo "                    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['label'], $context['value'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 61
            echo "                </select>
            ";
        } else {
            // line 63
            echo "                <input class=\"form-control\" type=\"text\" value=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["booking"]) || array_key_exists("booking", $context) ? $context["booking"] : (function () { throw new RuntimeError('Variable "booking" does not exist.', 63, $this->source); })()), "students", [], "any", false, false, false, 63), "firstname", [], "any", false, false, false, 63), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, (isset($context["booking"]) || array_key_exists("booking", $context) ? $context["booking"] : (function () { throw new RuntimeError('Variable "booking" does not exist.', 63, $this->source); })()), "students", [], "any", false, false, false, 63), "lastname", [], "any", false, false, false, 63), "html", null, true);
            echo "\" aria-label=\"Schüler\" readonly>
            ";
        }
        // line 64
        echo "    
        </div>
    </div>
    <br>
    <div>
        <button class=\"btn btn-outline-secondary btn-sm\">";
        // line 69
        echo twig_escape_filter($this->env, ((array_key_exists("button_label", $context)) ? (_twig_default_filter((isset($context["button_label"]) || array_key_exists("button_label", $context) ? $context["button_label"] : (function () { throw new RuntimeError('Variable "button_label" does not exist.', 69, $this->source); })()), "Speichern")) : ("Speichern")), "html", null, true);
        echo "</button>
    </div>
    ";
        // line 71
        echo         $this->env->getRuntime('Symfony\Component\Form\FormRenderer')->renderBlock((isset($context["form"]) || array_key_exists("form", $context) ? $context["form"] : (function () { throw new RuntimeError('Variable "form" does not exist.', 71, $this->source); })()), 'form_end');
        echo "
</div>
<br>";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "booking/_form.html.twig";
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
        return array (  224 => 71,  219 => 69,  212 => 64,  204 => 63,  200 => 61,  194 => 60,  186 => 58,  174 => 56,  171 => 55,  167 => 54,  161 => 52,  159 => 51,  150 => 44,  144 => 43,  136 => 41,  124 => 39,  121 => 38,  117 => 37,  112 => 35,  104 => 29,  89 => 27,  85 => 26,  80 => 24,  70 => 17,  59 => 9,  55 => 8,  47 => 3,  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<div class=\"col-lg-8 mt-5 mt-lg-0\">
    <br>
    {{ form_start(form) }}
    <div class=\"row g-3\">
        <div class=\"col-md-3 form-group\">
            <label class=\"required form_label\">Buchungsnummer</label>
            <input
                name=\"{{ field_name(form.bookingnumber) }}\"
                value=\"{{ field_value(form.bookingnumber) }}\"
                class=\"form-control\"
            >
        </div>
    </div>
    <div class=\"row g-3\">
        <div class=\"col-md-3 form-group\">
            <label class=\"required form_label\">Datum der Buchung</label>
            {{ form_widget(form.bookingdate, {'attr': {'class': 'form-control'}}) }}
        </div>
    </div>
    <!-- Anzeige des Buchungsstatus (editierbar für Admin) -->
    <div class=\"row g-3\">
        <div class=\"col-md-4 form-group\">
            <label class=\"required form_label\">Status der Buchung</label>
            <select name=\"{{ field_name(form.status) }}\" class=\"form-control\">
                <option value=\"\">Status auswählen</option>
                {% for label, value in field_choices(form.status) %}
                    <option value=\"{{ value }}\" {% if value == booking.status %}selected{% endif %}>{{ label }}</option>
                {% endfor %}
            </select>
        </div>
    </div>
    <div class=\"row g-3\">
        <div class=\"col-md-6 form-group\">
            <label for=\"courseSelect\" class=\"form-label\">Kurstermin(e)</label>
            <select name=\"{{ field_name(form.schedule) }}\" class=\"form-select form-control\">
                <option value=\"\" >Kurstermin(e) auswählen</option>
                {% for label, value in field_choices(form.schedule) %}
                    {% if booking.schedule is not null %}
                        <option value=\"{{ value }}\" {% if value == booking.schedule.id %}selected{% endif %}>{{ label }}</option>
                    {% else %}
                        <option value=\"{{ value }}\">{{ label }}</option>
                    {% endif %}
                {% endfor %}
            </select>
            <div id=\"emailHelp\" class=\"form-text\">Bitte den zugehörigen Kurs auswählen.</div>
        </div>
    </div>
    <div class=\"row g-3\">
        <div class=\"col-md-6 form-group\">
            <label for=\"studentSelect\" class=\"form_label\">Schüler</label>
            {% if is_granted('ROLE_ADMIN') %}
                <select name=\"{{ field_name(form.students) }}\" class=\"form-select form-control\">
                    <option value=\"\">Schüler auswählen</option>
                    {% for label, value in field_choices(form.students) %}
                        {% if booking.students is not null %}
                            <option value=\"{{ value }}\" {% if value == booking.students.id %}selected{% endif %}>{{ label }}</option>
                        {% else %}
                            <option value=\"{{ value }}\">{{ label }}</option>
                        {% endif %}
                    {% endfor %}
                </select>
            {% else %}
                <input class=\"form-control\" type=\"text\" value=\"{{ booking.students.firstname }} {{ booking.students.lastname }}\" aria-label=\"Schüler\" readonly>
            {% endif %}    
        </div>
    </div>
    <br>
    <div>
        <button class=\"btn btn-outline-secondary btn-sm\">{{ button_label|default('Speichern') }}</button>
    </div>
    {{ form_end(form) }}
</div>
<br>", "booking/_form.html.twig", "/shared/httpd/dicoma/templates/booking/_form.html.twig");
    }
}
