<?php

namespace XmlBeans\XPath;

/**
 * Fluent builder for constructing XPath expressions programmatically.
 *
 * Example:
 *   $xpath = XPathBuilder::create()
 *       ->descendant('item')
 *       ->where(XPathBuilder::attr('price')->gt(100))
 *       ->child('name')
 *       ->build();
 *
 * Compatible with PHP 7.2+.
 */
class XPathBuilder
{
    /** @var string[] Expression segments */
    private $segments = array();

    /** @var XPathNamespaceContext|null */
    private $namespaceContext = null;

    /**
     * Create a new builder.
     *
     * @return XPathBuilder
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Start from the document root (/).
     *
     * @return $this
     */
    public function root()
    {
        $this->segments[] = '/';
        return $this;
    }

    /**
     * Add a child axis step.
     *
     * @param string $nameOrExpr Element name or node test
     * @return $this
     */
    public function child($nameOrExpr)
    {
        $this->addStep('/', $nameOrExpr);
        return $this;
    }

    /**
     * Add a descendant-or-self axis step (//).
     *
     * @param string $nameOrExpr
     * @return $this
     */
    public function descendant($nameOrExpr)
    {
        $this->addStep('//', $nameOrExpr);
        return $this;
    }

    /**
     * Add a parent axis step (..).
     *
     * @return $this
     */
    public function parent()
    {
        $this->addStep('/', '..');
        return $this;
    }

    /**
     * Add a self axis step (.).
     *
     * @return $this
     */
    public function self()
    {
        $this->addStep('/', '.');
        return $this;
    }

    /**
     * Add an ancestor axis step.
     *
     * @param string $nameOrExpr
     * @return $this
     */
    public function ancestor($nameOrExpr)
    {
        $this->addStep('/', 'ancestor::' . $nameOrExpr);
        return $this;
    }

    /**
     * Add an ancestor-or-self axis step.
     *
     * @param string $nameOrExpr
     * @return $this
     */
    public function ancestorOrSelf($nameOrExpr)
    {
        $this->addStep('/', 'ancestor-or-self::' . $nameOrExpr);
        return $this;
    }

    /**
     * Add a following-sibling axis step.
     *
     * @param string $nameOrExpr
     * @return $this
     */
    public function followingSibling($nameOrExpr)
    {
        $this->addStep('/', 'following-sibling::' . $nameOrExpr);
        return $this;
    }

    /**
     * Add a preceding-sibling axis step.
     *
     * @param string $nameOrExpr
     * @return $this
     */
    public function precedingSibling($nameOrExpr)
    {
        $this->addStep('/', 'preceding-sibling::' . $nameOrExpr);
        return $this;
    }

    /**
     * Add a following axis step.
     *
     * @param string $nameOrExpr
     * @return $this
     */
    public function following($nameOrExpr)
    {
        $this->addStep('/', 'following::' . $nameOrExpr);
        return $this;
    }

    /**
     * Add a preceding axis step.
     *
     * @param string $nameOrExpr
     * @return $this
     */
    public function preceding($nameOrExpr)
    {
        $this->addStep('/', 'preceding::' . $nameOrExpr);
        return $this;
    }

    /**
     * Add a descendant axis step (explicit axis, not //).
     *
     * @param string $nameOrExpr
     * @return $this
     */
    public function descendantAxis($nameOrExpr)
    {
        $this->addStep('/', 'descendant::' . $nameOrExpr);
        return $this;
    }

    /**
     * Add an attribute axis step (@attr).
     *
     * @param string $attrName
     * @return $this
     */
    public function attribute($attrName)
    {
        $this->addStep('/', '@' . $attrName);
        return $this;
    }

    /**
     * Add a predicate to the last step.
     *
     * @param string|XPathPredicate $predicate
     * @return $this
     */
    public function where($predicate)
    {
        $expr = ($predicate instanceof XPathPredicate) ? $predicate->toXPath() : (string) $predicate;
        $this->segments[] = '[' . $expr . ']';
        return $this;
    }

    /**
     * Add a positional predicate [n].
     *
     * @param int $position 1-based position
     * @return $this
     */
    public function position($position)
    {
        $this->segments[] = '[' . (int) $position . ']';
        return $this;
    }

    /**
     * Select the first matching node ([1]).
     *
     * @return $this
     */
    public function first()
    {
        return $this->position(1);
    }

    /**
     * Select the last matching node ([last()]).
     *
     * @return $this
     */
    public function last()
    {
        $this->segments[] = '[last()]';
        return $this;
    }

    /**
     * Add a text() node test.
     *
     * @return $this
     */
    public function text()
    {
        $this->addStep('/', 'text()');
        return $this;
    }

    /**
     * Add a comment() node test.
     *
     * @return $this
     */
    public function comment()
    {
        $this->addStep('/', 'comment()');
        return $this;
    }

    /**
     * Add a processing-instruction() node test.
     *
     * @param string|null $target
     * @return $this
     */
    public function processingInstruction($target = null)
    {
        $pi = 'processing-instruction()';
        if ($target !== null) {
            $pi = "processing-instruction('" . $target . "')";
        }
        $this->addStep('/', $pi);
        return $this;
    }

    /**
     * Add a node() wildcard test.
     *
     * @return $this
     */
    public function node()
    {
        $this->addStep('/', 'node()');
        return $this;
    }

    /**
     * Add a wildcard (*) step.
     *
     * @return $this
     */
    public function any()
    {
        $this->addStep('/', '*');
        return $this;
    }

    /**
     * Add a namespace-prefixed element step.
     *
     * @param string $prefix
     * @param string $localName
     * @return $this
     */
    public function nsChild($prefix, $localName)
    {
        $this->addStep('/', $prefix . ':' . $localName);
        return $this;
    }

    /**
     * Union with another path expression (|).
     *
     * @param string|XPathBuilder $other
     * @return $this
     */
    public function union($other)
    {
        $expr = ($other instanceof XPathBuilder) ? $other->build() : (string) $other;
        $this->segments[] = ' | ' . $expr;
        return $this;
    }

    /**
     * Add a raw expression segment.
     *
     * @param string $raw
     * @return $this
     */
    public function raw($raw)
    {
        $this->segments[] = $raw;
        return $this;
    }

    /**
     * Set the namespace context for the built expression.
     *
     * @param XPathNamespaceContext $context
     * @return $this
     */
    public function withNamespaces(XPathNamespaceContext $context)
    {
        $this->namespaceContext = $context;
        return $this;
    }

    /**
     * Build the XPath expression string.
     *
     * @return string
     */
    public function build()
    {
        return implode('', $this->segments);
    }

    /**
     * Build and return a compiled XPathExpression.
     *
     * @return XPathExpression
     */
    public function compile()
    {
        return XPathExpression::compile($this->build(), $this->namespaceContext);
    }

    /**
     * Create an attribute predicate helper.
     *
     * @param string $name Attribute name
     * @return XPathPredicate
     */
    public static function attr($name)
    {
        return new XPathPredicate('@' . $name);
    }

    /**
     * Create an element predicate helper.
     *
     * @param string $name Element name
     * @return XPathPredicate
     */
    public static function elem($name)
    {
        return new XPathPredicate($name);
    }

    /**
     * Create a text() predicate helper.
     *
     * @return XPathPredicate
     */
    public static function textPred()
    {
        return new XPathPredicate('text()');
    }

    /**
     * Create a predicate using a function call.
     *
     * @param string $functionName
     * @param string ...$args
     * @return XPathPredicate
     */
    public static function func($functionName)
    {
        $args = func_get_args();
        array_shift($args); // remove functionName
        return new XPathPredicate($functionName . '(' . implode(', ', $args) . ')');
    }

    /**
     * Create a not() predicate.
     *
     * @param string|XPathPredicate $inner
     * @return XPathPredicate
     */
    public static function not($inner)
    {
        $expr = ($inner instanceof XPathPredicate) ? $inner->toXPath() : (string) $inner;
        return new XPathPredicate('not(' . $expr . ')');
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->build();
    }

    /**
     * Add a step with separator handling.
     *
     * @param string $separator
     * @param string $step
     */
    private function addStep($separator, $step)
    {
        if (empty($this->segments)) {
            if ($separator === '//') {
                $this->segments[] = '//' . $step;
            } else {
                $this->segments[] = $step;
            }
        } else {
            $last = end($this->segments);
            // Don't add separator if the last segment already ends with /
            // or if it's the root /
            if ($last === '/' || substr($last, -1) === '/') {
                $this->segments[] = $step;
            } elseif ($separator === '//' && $last !== '/') {
                $this->segments[] = '//' . $step;
            } else {
                $this->segments[] = '/' . $step;
            }
        }
    }
}
