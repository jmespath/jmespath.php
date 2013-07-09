<?php

namespace JamesPath;

use JamesPath\Ast\AbstractNode;
use JamesPath\Ast\IndexNode;
use JamesPath\Ast\MultiMatch;
use JamesPath\Ast\OrExpressionNode;
use JamesPath\Ast\SubExpressionNode;
use JamesPath\Ast\WildcardValuesNode;
use JamesPath\Ast\WildcardIndexNode;

class Parser
{
    /** @var self */
    protected static $instance;

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
        if (!self::$instance) {
            self::$instance = new self(new Lexer());
        }

        return self::$instance->parse($expression);
    }

    /**
     * Searches an array of data using a JamesPath expression
     *
     * @param string              $expression JamesPath expression
     * @param array|\ArrayAccess  $data       Data to traverse
     *
     * @return array|string|int|null
     */
    public static function search($expression, $data)
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
     * Free a random sample from the AST cache
     */
    protected static function freeCache()
    {
        shuffle(self::$cache);
        self::$cache = array_slice(self::$cache, self::$maxSize / 2);
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
        if (!isset(self::$cache[$path])) {
            if (count(self::$cache) >= self::$maxSize) {
                self::freeCache();
            }
            $this->lexer->setInput($path);
            self::$cache[$path] = $this->parseNext();
        }

        return self::$cache[$path];
    }

    /**
     * Match the next token against one or more types
     *
     * @param int|array $type Type to match
     * @return Token
     * @throws SyntaxErrorException
     */
    protected function match($type)
    {
        $this->lexer->next();
        if (!in_array($this->lexer->current()->type, (array) $type)) {
            throw new SyntaxErrorException($type, $this->lexer->current(), $this->lexer);
        }

        return $this->lexer->current();
    }

    protected function parseNext(AbstractNode $current = null)
    {
        switch ($this->lexer->current()->type) {
            case Lexer::T_IDENTIFIER:
            case Lexer::T_NUMBER:
                return $this->parseIdentifier();
            case Lexer::T_DOT:
                return $this->parseDot($current);
            case Lexer::T_STAR:
                return $this->parseWildcard();
            case Lexer::T_LBRACKET:
                return $this->parseIndex($current);
            case Lexer::T_OR:
                return $this->parseOr($current);
            case Lexer::T_EOF:
                return $current;
        }

        throw new SyntaxErrorException(range(0, 6), $this->lexer->current(), $this->lexer);
    }

    protected function parseIdentifier()
    {
        $field = new Ast\FieldNode($this->lexer->current()->value);
        // Allows: "Foo.Bar", "Foo[123]", "Foo", "Foo || ..."
        $this->match(array(Lexer::T_DOT, Lexer::T_LBRACKET, Lexer::T_EOF, Lexer::T_OR));

        return $this->parseNext($field);
    }

    protected function parseDot(AbstractNode $current)
    {
        // Allows: "Foo.Bar", "Foo.*", or "Foo.123"
        $this->match(array(Lexer::T_IDENTIFIER, Lexer::T_STAR, Lexer::T_NUMBER));

        return new SubExpressionNode($current, $this->parseNext($current));
    }

    protected function parseWildcard()
    {
        // Allows: "*.", "*[X]", "*", "* || ..."
        $this->match(array(Lexer::T_DOT, Lexer::T_LBRACKET, Lexer::T_EOF, Lexer::T_OR));

        return $this->parseNext(new WildcardValuesNode());
    }

    protected function parseIndex(AbstractNode $current = null)
    {
        // Allows: "Foo[123]", "Foo[*]"
        $value = $this->match(array(Lexer::T_NUMBER, Lexer::T_STAR))->value;
        $this->match(Lexer::T_RBRACKET);
        $this->lexer->next();
        $indexNode = $value === '*' ? new WildcardIndexNode() : new IndexNode($value);

        return $current
            ? $this->parseNext(new SubExpressionNode($current, $indexNode))
            : $this->parseNext($indexNode);
    }

    protected function parseOr(AbstractNode $current = null)
    {
        // Allows "Foo || Bar", "Foo || *", "Foo || [123]"
        $this->match(array(Lexer::T_IDENTIFIER, Lexer::T_STAR, Lexer::T_LBRACKET));

        return new OrExpressionNode($current, $this->parseNext());
    }
}
