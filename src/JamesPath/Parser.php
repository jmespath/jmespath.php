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
    private static $instance;
    /** @var array Cache of previously computed ASTs */
    private static $cache = array();
    /** @var int Maximum number of cached expressions */
    private static $maxSize = 64;
    /** @var Lexer */
    private $lexer;

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
    private static function freeCache()
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
            $root = null;
            self::$cache[$path] = $this->parseNext($root);
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
    private function match($type)
    {
        $this->lexer->next();
        if (!in_array($this->lexer->current()->type, (array) $type)) {
            throw new SyntaxErrorException($type, $this->lexer->current(), $this->lexer);
        }

        return $this->lexer->current();
    }

    private function parseNext(AbstractNode $current = null, AbstractNode &$root = null)
    {
        $isRoot = !$root;
        $this->checkRoot($current, $root);

        switch ($this->lexer->current()->type) {
            case Lexer::T_IDENTIFIER:
            case Lexer::T_NUMBER:
                $result = $this->parseIdentifier($root);
                break;
            case Lexer::T_DOT:
                $result = $this->parseDot($current, $root);
                break;
            case Lexer::T_STAR:
                $result = $this->parseWildcard($root);
                break;
            case Lexer::T_LBRACKET:
                $result = $this->parseIndex($current, $root);
                break;
            case Lexer::T_OR:
                $result = $this->parseOr($current, $root);
                break;
            case Lexer::T_EOF:
                $result = $current;
                break;
            default:
                throw new SyntaxErrorException(range(0, 6), $this->lexer->current(), $this->lexer);
        }

        return $isRoot ? $root : $result;
    }

    private function parseIdentifier(AbstractNode &$root = null)
    {
        $field = new Ast\FieldNode($this->lexer->current()->value);
        // Allows: "Foo.Bar", "Foo[123]", "Foo", "Foo || ..."
        $this->match(array(Lexer::T_DOT, Lexer::T_LBRACKET, Lexer::T_EOF, Lexer::T_OR));

        return $this->parseNext($field, $root);
    }

    private function parseDot(AbstractNode $current, AbstractNode &$root = null)
    {
        // Allows: "Foo.Bar", "Foo.*", or "Foo.123"
        $this->match(array(Lexer::T_IDENTIFIER, Lexer::T_STAR, Lexer::T_NUMBER));
        $sub = new SubExpressionNode($current);
        $this->checkRoot($current, $root, $sub);
        $sub->setRight($this->parseNext($current, $root));

        return $sub;
    }

    private function parseWildcard(AbstractNode &$root = null)
    {
        // Allows: "*.", "*[X]", "*", "* || ..."
        $this->match(array(Lexer::T_DOT, Lexer::T_LBRACKET, Lexer::T_EOF, Lexer::T_OR));

        return $this->parseNext(new WildcardValuesNode(), $root);
    }

    private function parseIndex(AbstractNode $current = null, AbstractNode &$root = null)
    {
        // Allows: "Foo[123]", "Foo[*]"
        $value = $this->match(array(Lexer::T_NUMBER, Lexer::T_STAR))->value;
        $this->match(Lexer::T_RBRACKET);
        $this->lexer->next();
        $indexNode = $value === '*' ? new WildcardIndexNode() : new IndexNode($value);
        $sub = $current ? new SubExpressionNode($current, $indexNode) : $indexNode;
        $this->checkRoot($current, $root, $sub);

        return $this->parseNext($sub, $root);
    }

    private function parseOr(AbstractNode $current = null, AbstractNode &$root = null)
    {
        // Allows "Foo || Bar", "Foo || *", "Foo || [123]"
        $this->match(array(Lexer::T_IDENTIFIER, Lexer::T_STAR, Lexer::T_LBRACKET));
        // Change the root node but return the existing $current value for recursive descent
        $root = new OrExpressionNode($root, $this->parseNext($current, $root));

        return $current;
    }

    private function checkRoot(
        AbstractNode $current = null,
        AbstractNode &$root = null,
        AbstractNode $nextNode = null
    ) {
        if (!$current) {
            return;
        }

        // Sub expressions can wrap a root node, making it the new root
        if ($nextNode instanceof SubExpressionNode && $current === $root) {
            $root = $nextNode;
        }

        if (!$root) {
            $root = $current;
        }
    }
}
