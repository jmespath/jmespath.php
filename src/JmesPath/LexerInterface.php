<?php

namespace JmesPath;

/**
 * JMESPath lexer used to create an array of tokens
 */
interface LexerInterface
{
    const T_EOF        = 'T_EOF';
    const T_IDENTIFIER = 'T_IDENTIFIER';
    const T_DOT        = 'T_DOT';
    const T_STAR       = 'T_STAR';
    const T_NUMBER     = 'T_NUMBER';
    const T_OR         = 'T_OR';
    const T_PIPE       = 'T_PIPE';
    const T_LBRACKET   = 'T_LBRACKET';
    const T_RBRACKET   = 'T_RBRACKET';
    const T_COMMA      = 'T_COMMA';
    const T_LBRACE     = 'T_LBRACE';
    const T_RBRACE     = 'T_RBRACE';
    const T_WHITESPACE = 'T_WHITESPACE';
    const T_UNKNOWN    = 'T_UNKNOWN';
    const T_COLON      = 'T_COLON';
    const T_OPERATOR   = 'T_OPERATOR';
    const T_FUNCTION   = 'T_FUNCTION';
    const T_LPARENS    = 'T_LPARENS';
    const T_RPARENS    = 'T_RPARENS';
    const T_MERGE      = 'T_MERGE';
    const T_LITERAL    = 'T_LITERAL';
    const T_FILTER     = 'T_FILTER';
    const T_AT         = 'T_AT';

    /**
     * Tokenize the JMESPath expression into an array of tokens
     *
     * @param string $input JMESPath input
     *
     * @return array
     * @throws SyntaxErrorException
     */
    public function tokenize($input);
}
