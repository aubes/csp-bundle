<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Twig;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\Enum\CSPDirective;
use Aubes\CSPBundle\Uid\GeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CSPExtension extends AbstractExtension
{
    private const NONCE_PATTERN = '/^[A-Za-z0-9+\/=]+$/';

    public function __construct(
        private readonly CSP $csp,
        private readonly GeneratorInterface $generator,
        private readonly RequestStack $requestStack,
    ) {
    }

    private const ALLOWED_HASH_ALGORITHMS = ['sha256', 'sha384', 'sha512'];

    public function getTokenParsers(): array
    {
        return [
            new CSPInlineTokenParser('script'),
            new CSPInlineTokenParser('style'),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', [$this, 'nonce'], ['is_safe' => ['html']]),
            new TwigFunction('csp_script_nonce', [$this, 'scriptNonce'], ['is_safe' => ['html']]),
            new TwigFunction('csp_style_nonce', [$this, 'styleNonce'], ['is_safe' => ['html']]),
            new TwigFunction('csp_hash', [$this, 'hash']),
        ];
    }

    public function nonce(string $directive, ?string $groupName = null, ?string $nonce = null): string
    {
        if ($nonce === null) {
            $nonce = \base64_encode($this->generator->generate(16));
        } elseif (!\preg_match(self::NONCE_PATTERN, $nonce)) {
            throw new \InvalidArgumentException('Invalid nonce value: must contain only base64 characters (A-Z, a-z, 0-9, +, /, =)');
        }

        $nonceValue = '\'nonce-' . $nonce . '\'';

        foreach ($this->resolveGroups($groupName) as $group) {
            $this->csp->addDirective($directive, $nonceValue, $group);
        }

        return 'nonce="' . $nonce . '"';
    }

    public function scriptNonce(?string $groupName = null, ?string $nonce = null): string
    {
        return $this->nonce(CSPDirective::ScriptSrc->value, $groupName, $nonce);
    }

    public function styleNonce(?string $groupName = null, ?string $nonce = null): string
    {
        return $this->nonce(CSPDirective::StyleSrc->value, $groupName, $nonce);
    }

    public function hash(string $directive, string $content, string $algorithm = 'sha256', ?string $groupName = null): void
    {
        if (!\in_array($algorithm, self::ALLOWED_HASH_ALGORITHMS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported hash algorithm "%s". Supported: %s', $algorithm, \implode(', ', self::ALLOWED_HASH_ALGORITHMS)));
        }

        $hash = \base64_encode(\hash($algorithm, $content, true));

        $hashValue = "'" . $algorithm . '-' . $hash . "'";

        foreach ($this->resolveGroups($groupName) as $group) {
            $this->csp->addDirective($directive, $hashValue, $group);
        }
    }

    /**
     * @return list<null|string>
     */
    private function resolveGroups(?string $groupName): array
    {
        if ($groupName !== null) {
            return [$groupName];
        }

        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return [null];
        }

        /** @var list<string> $groups */
        $groups = (array) $request->attributes->get('_csp_groups', []);

        return $groups !== [] ? $groups : [null];
    }
}
