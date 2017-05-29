<?php
namespace Vanio\WebBundle\Templating;

use Html2Text\Html2Text;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Vanio\Stdlib\Strings;
use Vanio\WebBundle\Request\RefererResolver;
use Vanio\WebBundle\Request\RouteHierarchyResolver;

class WebExtension extends \Twig_Extension
{
    /** @var TranslatorInterface */
    private $translator;

    /** @var RequestStack */
    private $requestStack;

    /** @var RefererResolver */
    private $refererResolver;

    /** @var RouteHierarchyResolver */
    private $routeHierarchyResolver;

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack,
        RefererResolver $refererResolver,
        RouteHierarchyResolver $routeHierarchyResolver
    ) {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->refererResolver = $refererResolver;
        $this->routeHierarchyResolver = $routeHierarchyResolver;
    }

    /**
     * @return \Twig_SimpleFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new \Twig_SimpleFunction('class_name', [$this, 'resolveClassName']),
            new \Twig_SimpleFunction('is_translated', [$this, 'isTranslated']),
            new \Twig_SimpleFunction('referer', [$this, 'resolveReferer']),
            new \Twig_SimpleFunction('is_current', [$this, 'isCurrent']),
            new \Twig_SimpleFunction('breadcrumbs', [$this, 'resolveBreadcrumbs']),
        ];
    }

    /**
     * @return \Twig_SimpleFilter[]
     */
    public function getFilters(): array
    {
        return [
            new \Twig_SimpleFilter('trans', [$this, 'trans']),
            new \Twig_SimpleFilter('filter', [$this, 'filter']),
            new \Twig_SimpleFilter('without', [$this, 'without']),
            new \Twig_SimpleFilter('regexp_replace', [$this, 'regexpReplace']),
            new \Twig_SimpleFilter('html_to_text', [$this, 'htmlToText']),
            new \Twig_SimpleFilter('evaluate', [$this, 'evaluate'], ['needs_environment' => true]),
        ];
    }

    /**
     * @return \Twig_SimpleFunction[]
     */
    public function getTests(): array
    {
        return [new \Twig_SimpleTest('instance of', [$this, 'isInstanceOf'])];
    }

    public function getName(): string
    {
        return 'vanio_web_extension';
    }

    public function resolveClassName(array $classes): string
    {
        return implode(' ', array_keys(array_filter($classes)));
    }

    public function isTranslated(string $id, string $domain = 'messages', string $locale = null): bool
    {
        return $this->translator instanceof TranslatorBagInterface
            && $this->translator->getCatalogue($locale)->has($id, $domain)
            && $this->translator->getCatalogue($locale)->get($id, $domain) !== false;
    }

    public function resolveReferer(string $fallbackPath = null): string
    {
        return $this->refererResolver->resolveReferer($this->requestStack->getCurrentRequest(), $fallbackPath);
    }

    public function isCurrent(string $route): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->attributes->get('_route') === $route) {
            return true;
        }

        $hierarchy = $this->routeHierarchyResolver->resolveRouteHierarchy($this->requestStack->getCurrentRequest());

        return isset($hierarchy[$route]);
    }

    /**
     * @return string[]
     */
    public function resolveBreadcrumbs(): array
    {
        return $this->routeHierarchyResolver->resolveRouteHierarchy($this->requestStack->getCurrentRequest());
    }

    /**
     * @param string|string[] $messages
     * @param array $arguments
     * @param string|null $domain
     * @param string|null $locale
     * @return string|string[]
     */
    public function trans($messages, array $arguments = [], string $domain = null, string $locale = null)
    {
        if (is_array($messages)) {
            $translatedMessages = [];

            foreach ($messages as $message) {
                $translatedMessages[] = $this->trans($message, $arguments, $domain, $locale);
            }

            return $translatedMessages;
        }

        return $this->translator->trans($messages, $arguments, $domain, $locale);
    }

    public function filter(array $array): array
    {
        return array_filter($array, [$this, 'isNotEmpty']);
    }

    /**
     * @param array $array
     * @param string|array $keys
     * @return array
     */
    public function without(array $array, $keys): array
    {
        return array_diff_key($array, array_flip((array) $keys));
    }

    /**
     * @param string $string
     * @param array|string $pattern
     * @param array|string|null $replacement
     * @return string
     */
    public function regexpReplace(string $string, $pattern, $replacement = null): string
    {
        if ($replacement === null) {
            return preg_replace(array_keys($pattern), $pattern, $string);
        }

        return preg_replace($pattern, $replacement, $string);
    }

    public function htmlToText(string $html, array $options = []): string
    {
        return (new Html2Text($html, $options))->getText();
    }

    public function evaluate(\Twig_Environment $environment, string $template, array $context = []): string
    {
        return Strings::contains($template, ['{{', '{%', '{#'])
            ? $environment->createTemplate($template)->render($context)
            : $template;
    }

    /**
     * @param mixed $value
     * @param string $class
     * @return bool
     */
    public function isInstanceOf($value, string $class): bool
    {
        return is_a($value, $class, true);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function isNotEmpty($value): bool
    {
        if ($value instanceof \Countable) {
            return count($value) > 0;
        }

        return $value !== '' && $value !== false && $value !== null && $value !== [];
    }
}
