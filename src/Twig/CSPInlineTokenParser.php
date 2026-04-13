<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\Twig;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class CSPInlineTokenParser extends AbstractTokenParser
{
    public function __construct(
        private readonly string $type,
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

        $body = $this->parser->subparse(fn (Token $token) => $token->test('end_csp_' . $this->type), true);

        $stream->expect(Token::BLOCK_END_TYPE);

        return new CSPInlineNode($body, $this->type, $groupName, $token->getLine());
    }

    public function getTag(): string
    {
        return 'csp_' . $this->type;
    }
}
