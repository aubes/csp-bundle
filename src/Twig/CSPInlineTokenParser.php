<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Twig;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class CSPInlineTokenParser extends AbstractTokenParser
{
    public const MODE_NONCE = 'nonce';
    public const MODE_HASH = 'hash';

    public function __construct(
        private readonly string $type,
        private readonly string $mode = self::MODE_NONCE,
    ) {
    }

    public function parse(Token $token): CSPInlineNode
    {
        $stream = $this->parser->getStream();
        $groupName = null;

        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            /** @var string $groupName */
            $groupName = $stream->expect(Token::STRING_TYPE)->getValue();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        $endTag = 'end_' . $this->getTag();
        $body = $this->parser->subparse(static fn (Token $token) => $token->test($endTag), true);

        $stream->expect(Token::BLOCK_END_TYPE);

        return new CSPInlineNode($body, $this->type, $this->mode, $groupName, $token->getLine());
    }

    public function getTag(): string
    {
        return $this->mode === self::MODE_HASH
            ? 'csp_' . $this->type . '_hash'
            : 'csp_' . $this->type;
    }
}
