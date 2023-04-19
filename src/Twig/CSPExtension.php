<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Twig;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\CSPDirective;
use Aubes\CSPBundle\Uid\GeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CSPExtension extends AbstractExtension
{
    protected CSP $csp;
    protected GeneratorInterface $generator;

    public function __construct(CSP $csp, GeneratorInterface $generator)
    {
        $this->csp = $csp;
        $this->generator = $generator;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('csp_nonce', [$this, 'nonce'], ['is_safe' => ['html']]),
            new TwigFunction('csp_script_nonce', [$this, 'scriptNonce'], ['is_safe' => ['html']]),
            new TwigFunction('csp_style_nonce', [$this, 'styleNonce'], ['is_safe' => ['html']]),
        ];
    }

    public function nonce(string $directive, string $groupName = null, string $nonce = null): string
    {
        if ($nonce === null) {
            $nonce = \base64_encode($this->generator->generate(8));
        }

        $this->csp->addDirective($directive, '\'nonce-' . $nonce . '\'', $groupName);

        return 'nonce="' . $nonce . '"';
    }

    public function scriptNonce(string $groupName = null, string $nonce = null): string
    {
        return $this->nonce(CSPDirective::SCRIPT_SRC, $groupName, $nonce);
    }

    public function styleNonce(string $groupName = null, string $nonce = null): string
    {
        return $this->nonce(CSPDirective::STYLE_SRC, $groupName, $nonce);
    }
}
