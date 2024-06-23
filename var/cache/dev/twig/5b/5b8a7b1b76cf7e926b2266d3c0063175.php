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

/* includes/_footer.html.twig */
class __TwigTemplate_8c906e4354ef5ae6e1b69b78fcec64f3 extends Template
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
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->enter($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "includes/_footer.html.twig"));

        $__internal_6f47bbe9983af81f1e7450e9a3e3768f = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->enter($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "includes/_footer.html.twig"));

        // line 1
        echo "<!-- footer Start -->
<footer class=\"footer\">
    <div class=\"container\">
        <div class=\"row\">
            <div class=\"col-md-12\">
                <div class=\"footer-manu\">
                    <ul>
                        <li><a href=\"#\">About Us</a></li>
                        <li><a href=\"#\">Contact us</a></li>
                        <li><a href=\"#\">How it works</a></li>
                        <li><a href=\"#\">FAQ</a></li>
                        <li><a href=\"#\">Pricing</a></li>
                    </ul>
                </div>
                <p class=\"copyright mb-0\">Copyright <script>document.write(new Date().getFullYear())</script> &copy; Designed & Developed by <a
                            href=\"http://www.themefisher.com\">Themefisher</a>. All rights reserved.
                    <br> Get More <a href=\"https://themefisher.com/free-bootstrap-templates/\">Free Bootstrap
                        Templates</a>
                </p>
            </div>
        </div>
    </div>
</footer>";
        
        $__internal_5a27a8ba21ca79b61932376b2fa922d2->leave($__internal_5a27a8ba21ca79b61932376b2fa922d2_prof);

        
        $__internal_6f47bbe9983af81f1e7450e9a3e3768f->leave($__internal_6f47bbe9983af81f1e7450e9a3e3768f_prof);

    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "includes/_footer.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo()
    {
        return array (  43 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<!-- footer Start -->
<footer class=\"footer\">
    <div class=\"container\">
        <div class=\"row\">
            <div class=\"col-md-12\">
                <div class=\"footer-manu\">
                    <ul>
                        <li><a href=\"#\">About Us</a></li>
                        <li><a href=\"#\">Contact us</a></li>
                        <li><a href=\"#\">How it works</a></li>
                        <li><a href=\"#\">FAQ</a></li>
                        <li><a href=\"#\">Pricing</a></li>
                    </ul>
                </div>
                <p class=\"copyright mb-0\">Copyright <script>document.write(new Date().getFullYear())</script> &copy; Designed & Developed by <a
                            href=\"http://www.themefisher.com\">Themefisher</a>. All rights reserved.
                    <br> Get More <a href=\"https://themefisher.com/free-bootstrap-templates/\">Free Bootstrap
                        Templates</a>
                </p>
            </div>
        </div>
    </div>
</footer>", "includes/_footer.html.twig", "/shared/httpd/dicoma/templates/includes/_footer.html.twig");
    }
}
