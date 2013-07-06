<?php

namespace JamesPath;

use JamesPath\Ast\AbstractNode;
use JamesPath\Ast\ElementsBranchNode;
use JamesPath\Ast\IndexNode;
use JamesPath\Ast\MultiMatch;
use JamesPath\Ast\SubExpressionNode;
use JamesPath\Ast\ValuesBranchNode;

class Parser
{
    /** @var array Cache of previously computed ASTs */
    protected static $cache = array();

    /** @var int Maximum number of cached expressions */
    protected static $maxSize = 64;

    /** @var Lexer */
    protected $lexer;

    /**
     * @param Lexer $lexer Lexer used to tokenize paths
     */
    public function __construct(Lexer $lexer)
    {
        $this->lexer = $lexer;
    }

    /**
     * Compiles a JamesPath expression into an AST
     *
     * @param string $expression JamesPath expression
     *
     * @return AbstractNode
     */
    public static function compile($expression)
    {
        $parser = new self(new Lexer());

        return $parser->parse($expression);
    }

    /**
     * Searches an array of data using a JamesPath expression
     *
     * @param string $expression JamesPath expression
     * @param array  $data       Data to traverse
     *
     * @return mixed
     */
    public static function search($expression, array $data)
    {
        $result = self::compile($expression)->search($data);

        return $result instanceof MultiMatch ? $result->toArray() : $result;
    }

    /**
     * Purge all cached expressions
     */
    public static function purge()
    {
        self::$cache = array();
    }

    /**
     * Parse a JamesPath expression into an AST
     *
     * @param string $path Path to parse
     *
     * @return AbstractNode
     */
    public function parse($path)
    {
        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        $this->lexer->setInput($path);
        $ast = $this->parseNext();

        if (count(self::$cache) >= self::$maxSize) {
            $this->freeCache();
        }

        return self::$cache[$path] = $ast;
    }

    protected function freeCache()
    {
        shuffle(self::$cache);
        self::$cache = array_slice(self::$cache, self::$maxSize / 2);
    }

    protected function checkType($actual, array $types)
    {
        if (in_array($actual, $types)) {
            return $actual;
        }

        $lexer = $this->lexer;
        throw new \RuntimeException(sprintf(
            'Expected %s; found %s',
            implode('|', array_map(function ($t) use ($lexer) {
                return $lexer->getTokenName($t);
            }, $types)),
            $this->lexer->getTokenName($actual)
        ));
    }

    protected function peek($type)
    {
        return $this->checkType($this->lexer->peek()->type, (array) $type);
    }

    protected function match($type)
    {
        $result = $this->checkType($this->lexer->current()->type, (array) $type);
        $this->lexer->next();

        return $result;
    }

    protected function parseNext(AbstractNode $current = null)
    {
        switch ($this->lexer->current()->type) {
            case Lexer::T_IDENTIFIER:
            case Lexer::T_NUMBER:
                return $this->parseIdentifier($current);
            case Lexer::T_STAR:
                return $this->parseWildcard($current);
            case Lexer::T_LBRACKET:
                return $this->parseIndex($current);
            case Lexer::T_OR:
                return $this->parseOr($current);
            default:
                throw new \RuntimeException('JamesPath expression syntax error: ' . $this->lexer->getInput());
        }
    }

    protected function parseIdentifier(AbstractNode $current = null)
    {
        $field = new Ast\FieldNode($this->lexer->current()->value);
        // expression '.' expression | expression '[' (number|star) ']'
        $peekType = $this->peek(array(Lexer::T_DOT, Lexer::T_LBRACKET, Lexer::T_EOF));
        $this->lexer->next();

        switch ($peekType) {
            case Lexer::T_EOF:
                return $field;
            case Lexer::T_DOT:
                $this->match(Lexer::T_DOT);
                // Last expression or is there more?
                return $this->lexer->peek()->type == Lexer::T_EOF
                    ? $this->parseNext($field)
                    : new SubExpressionNode($field, $this->parseNext($field));
            case Lexer::T_LBRACKET:
                // Index after expression field
                $index = $this->parseIndex($field);
                if (!($index instanceof ElementsBranchNode)) {
                    return new SubExpressionNode($field, $index);
                } elseif ($this->lexer->peek()->type == Lexer::T_EOF) {
                    return $index;
                } else {
                    return new SubExpressionNode($index, $this->parseNext($field));
                }
        }
    }

    protected function parseWildcard(AbstractNode $current = null)
    {
        return new ValuesBranchNode($current);
    }

    protected function parseIndex(AbstractNode $current = null)
    {
        // expression '[' (number|star) ']'
        $this->lexer->next();
        $index = $this->lexer->current()->value;
        $type = $this->match(array(Lexer::T_NUMBER, Lexer::T_STAR));
        $this->match(Lexer::T_RBRACKET);

        return $type == Lexer::T_NUMBER ? new IndexNode($index) : new ElementsBranchNode($current);
    }

    protected function parseOr(AbstractNode $current = null)
    {

    }
}
