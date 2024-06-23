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

/* base.html.twig */
class __TwigTemplate_5b54629f79ffcac823e1b456ecfa9232 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'title' => [$this, 'block_title'],
            'body' => [$this, 'block_body'],
            'javascripts' => [$this, 'block_javascripts'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "base.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "base.html.twig"));

        // line 1
        echo "<!DOCTYPE html>
<html lang=\"de\">
<head>
    <!-- Basic Page Needs
    ================================================== -->
    <meta charset=\"utf-8\">
    <meta charset=\"UTF-8\">
    <title>";
        // line 8
        $this->displayBlock('title', $context, $blocks);
        echo "</title>

    <!-- Mobile Specific Metas
    ================================================== -->
    <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
    <meta name=\"description\" content=\"Diversworld DiveCourseManager\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=5.0\">
    <meta name=\"author\" content=\"Eckhard becker\">
    <meta name=\"theme-name\" content=\"airspace\">
    <meta name=\"generator\" content=\"Themefisher Airspace Template v1.0\">
    <!-- Favicon -->
    <link rel=\"shortcut icon\" type=\"image/x-icon\" href=\"";
        // line 19
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("images/favicon.ico"), "html", null, true);
        echo "\" />

    <!-- bootstrap.min css -->
    <link rel=\"stylesheet\" href=\"";
        // line 22
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/bootstrap/bootstrap.min.css"), "html", null, true);
        echo "\">
    <!-- Ionic Icon Css -->
    <link rel=\"stylesheet\" href=\"";
        // line 24
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/Ionicons/css/ionicons.min.css"), "html", null, true);
        echo "\">
    <!-- animate.css -->
    <link rel=\"stylesheet\" href=\"";
        // line 26
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/animate-css/animate.css"), "html", null, true);
        echo "\">
    <!-- Magnify Popup -->
    <link rel=\"stylesheet\" href=\"";
        // line 28
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/magnific-popup/magnific-popup.css"), "html", null, true);
        echo "\">
    <!-- Slick CSS -->
    <link rel=\"stylesheet\" href=\"";
        // line 30
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/slick/slick.css"), "html", null, true);
        echo "\">
    <!-- Place the first <script> tag in your HTML's <head> -->
    <script src=\"";
        // line 32
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("js/tinymce/tinymce.min.js"), "html", null, true);
        echo "\" referrerpolicy=\"origin\"></script>
    <script>
      tinymce.init({
          selector: 'textarea'
      });
    </script>
    <!-- Main Stylesheet -->
    <link rel=\"stylesheet\" href=\"";
        // line 39
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("css/style.css"), "html", null, true);
        echo "\">
</head>
    <body>
        ";
        // line 42
        $this->loadTemplate("includes/_header.html.twig", "base.html.twig", 42)->display($context);
        // line 43
        echo "        ";
        $this->loadTemplate("includes/_slider.html.twig", "base.html.twig", 43)->display($context);
        // line 44
        echo "
        ";
        // line 45
        $this->displayBlock('body', $context, $blocks);
        // line 46
        echo "
        ";
        // line 47
        $this->loadTemplate("includes/_footer.html.twig", "base.html.twig", 47)->display($context);
        // line 48
        echo "
        <!--Scroll to top-->
        <div id=\"scroll-to-top\" class=\"scroll-to-top\">
            <span class=\"icon ion-ios-arrow-up\"></span>
        </div>
        ";
        // line 53
        $this->displayBlock('javascripts', $context, $blocks);
        // line 81
        echo "    </body>
</html>
";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    // line 8
    public function block_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "title"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "title"));

        echo "Dive Course manager!";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 45
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "body"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "body"));

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    // line 53
    public function block_javascripts($context, array $blocks = [])
    {
        $macros = $this->macros;
        $__internal_5a27a8ba21ca79b61932376b2fa922d2 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "javascripts"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "block", "javascripts"));

        // line 54
        echo "        <!--
        Essential Scripts
        =====================================-->
            <!-- Main jQuery -->
            <script src=\"";
        // line 58
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/jquery/jquery.min.js"), "html", null, true);
        echo "\"></script>
            <!-- Ionicons -->
            <script type=\"module\" src=\"";
        // line 60
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/Ionicons/js/ionicons.esm.js"), "html", null, true);
        echo "\"></script>
            <!-- Bootstrap 3.1 -->
            <script src=\"";
        // line 62
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/bootstrap/bootstrap.min.js"), "html", null, true);
        echo "\"></script>
            <!-- slick Carousel -->
            <script src=\"";
        // line 64
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/slick/slick.min.js"), "html", null, true);
        echo "\"></script>
            <script src=\"";
        // line 65
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/magnific-popup/jquery.magnific-popup.min.js"), "html", null, true);
        echo "\"></script>
            <!-- filter -->
            <script src=\"";
        // line 67
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/shuffle/shuffle.min.js"), "html", null, true);
        echo "\"></script>
            <script src=\"";
        // line 68
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/SyoTimer/jquery.syotimer.min.js"), "html", null, true);
        echo "\"></script>
            <!-- Google Map -->
            <!--<script src=\"https://maps.googleapis.com/maps/api/js?key=AIzaSyCcABaamniA6OL5YvYSpB3pFMNrXwXnLwU&libraries=places\"></script>-->
            <script src=\"";
        // line 71
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("plugins/google-map/map.js"), "html", null, true);
        echo "\"></script>
            <script src=\"";
        // line 72
        echo twig_escape_filter($this->env, $this->extensions['Symfony\Bridge\Twig\Extension\AssetExtension']->getAssetUrl("js/script.js"), "html", null, true);
        echo "\"></script>

            <!-- Place the following <script> tags before the closing </body> tag -->
            <script>
                document.querySelector('form').addEventListener('submit', function() {
                    tinymce.triggerSave();
                });
            </script>
        ";
        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "base.html.twig";
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
        return array (  243 => 72,  239 => 71,  233 => 68,  229 => 67,  224 => 65,  220 => 64,  215 => 62,  210 => 60,  205 => 58,  199 => 54,  189 => 53,  171 => 45,  152 => 8,  140 => 81,  138 => 53,  131 => 48,  129 => 47,  126 => 46,  124 => 45,  121 => 44,  118 => 43,  116 => 42,  110 => 39,  100 => 32,  95 => 30,  90 => 28,  85 => 26,  80 => 24,  75 => 22,  69 => 19,  55 => 8,  46 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<!DOCTYPE html>
<html lang=\"de\">
<head>
    <!-- Basic Page Needs
    ================================================== -->
    <meta charset=\"utf-8\">
    <meta charset=\"UTF-8\">
    <title>{% block title %}Dive Course manager!{% endblock %}</title>

    <!-- Mobile Specific Metas
    ================================================== -->
    <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
    <meta name=\"description\" content=\"Diversworld DiveCourseManager\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=5.0\">
    <meta name=\"author\" content=\"Eckhard becker\">
    <meta name=\"theme-name\" content=\"airspace\">
    <meta name=\"generator\" content=\"Themefisher Airspace Template v1.0\">
    <!-- Favicon -->
    <link rel=\"shortcut icon\" type=\"image/x-icon\" href=\"{{ asset('images/favicon.ico') }}\" />

    <!-- bootstrap.min css -->
    <link rel=\"stylesheet\" href=\"{{ asset('plugins/bootstrap/bootstrap.min.css') }}\">
    <!-- Ionic Icon Css -->
    <link rel=\"stylesheet\" href=\"{{ asset('plugins/Ionicons/css/ionicons.min.css') }}\">
    <!-- animate.css -->
    <link rel=\"stylesheet\" href=\"{{ asset('plugins/animate-css/animate.css') }}\">
    <!-- Magnify Popup -->
    <link rel=\"stylesheet\" href=\"{{ asset('plugins/magnific-popup/magnific-popup.css') }}\">
    <!-- Slick CSS -->
    <link rel=\"stylesheet\" href=\"{{ asset('plugins/slick/slick.css') }}\">
    <!-- Place the first <script> tag in your HTML's <head> -->
    <script src=\"{{ asset('js/tinymce/tinymce.min.js') }}\" referrerpolicy=\"origin\"></script>
    <script>
      tinymce.init({
          selector: 'textarea'
      });
    </script>
    <!-- Main Stylesheet -->
    <link rel=\"stylesheet\" href=\"{{ asset('css/style.css') }}\">
</head>
    <body>
        {% include 'includes/_header.html.twig' %}
        {% include 'includes/_slider.html.twig' %}

        {% block body %}{% endblock %}

        {% include 'includes/_footer.html.twig' %}

        <!--Scroll to top-->
        <div id=\"scroll-to-top\" class=\"scroll-to-top\">
            <span class=\"icon ion-ios-arrow-up\"></span>
        </div>
        {% block javascripts %}
        <!--
        Essential Scripts
        =====================================-->
            <!-- Main jQuery -->
            <script src=\"{{ asset('plugins/jquery/jquery.min.js') }}\"></script>
            <!-- Ionicons -->
            <script type=\"module\" src=\"{{ asset('plugins/Ionicons/js/ionicons.esm.js') }}\"></script>
            <!-- Bootstrap 3.1 -->
            <script src=\"{{ asset('plugins/bootstrap/bootstrap.min.js') }}\"></script>
            <!-- slick Carousel -->
            <script src=\"{{ asset('plugins/slick/slick.min.js') }}\"></script>
            <script src=\"{{ asset('plugins/magnific-popup/jquery.magnific-popup.min.js') }}\"></script>
            <!-- filter -->
            <script src=\"{{ asset('plugins/shuffle/shuffle.min.js') }}\"></script>
            <script src=\"{{ asset('plugins/SyoTimer/jquery.syotimer.min.js') }}\"></script>
            <!-- Google Map -->
            <!--<script src=\"https://maps.googleapis.com/maps/api/js?key=AIzaSyCcABaamniA6OL5YvYSpB3pFMNrXwXnLwU&libraries=places\"></script>-->
            <script src=\"{{ asset('plugins/google-map/map.js') }}\"></script>
            <script src=\"{{ asset('js/script.js') }}\"></script>

            <!-- Place the following <script> tags before the closing </body> tag -->
            <script>
                document.querySelector('form').addEventListener('submit', function() {
                    tinymce.triggerSave();
                });
            </script>
        {% endblock %}
    </body>
</html>
", "base.html.twig", "/shared/httpd/dicoma/templates/base.html.twig");
    }
}
