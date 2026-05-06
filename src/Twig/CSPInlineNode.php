<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Twig;

use Twig\Compiler;
use Twig\Node\Node;

class CSPInlineNode extends Node
{
    public function __construct(
        Node $body,
        private readonly string $type,
        private readonly string $mode,
        private readonly ?string $groupName,
        int $lineno,
    ) {
        parent::__construct(['body' => $body], [], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $directive = match ($this->type) {
            'script' => 'script-src',
            'style' => 'style-src',
            default => throw new \LogicException('Unsupported CSP inline type: ' . $this->type),
        };

        $compiler
            ->addDebugInfo($this)
            ->write('ob_start();' . "\n")
            ->subcompile($this->getNode('body'))
            ->write('$__csp_content = ob_get_clean();' . "\n")
            ->write('$__csp_ext = $this->env->getExtension(' . \var_export(CSPExtension::class, true) . ');' . "\n");

        if ($this->mode === CSPInlineTokenParser::MODE_HASH) {
            $compiler
                ->write('$__csp_ext->hash(' . \var_export($directive, true) . ', $__csp_content, \'sha256\', ' . \var_export($this->groupName, true) . ');' . "\n")
                ->write('echo \'<' . $this->type . '>\' . $__csp_content . \'</' . $this->type . '>\';' . "\n");

            return;
        }

        $compiler
            ->write('$__csp_nonce = $__csp_ext->nonce(' . \var_export($directive, true) . ', ' . \var_export($this->groupName, true) . ');' . "\n")
            ->write('echo \'<' . $this->type . ' \' . $__csp_nonce . \'>\' . $__csp_content . \'</' . $this->type . '>\';' . "\n");
    }
}
