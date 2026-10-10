<?php

namespace XmlBeans\XPath;

/**
 * Represents a compiled XPath expression with caching support.
 *
 * Wraps an XPath expression string with its namespace context,
 * bound variables, and provides a static expression cache for reuse.
 *
 * Compatible with PHP 7.2+.
 */
class XPathExpression
{
    /** @var string The raw XPath expression */
    private $expression;

    /** @var XPathNamespaceContext|null */
    private $namespaceContext;

    /** @var array<string, mixed> Bound variable values */
    private $variables = array();

    /** @var string|null Cached normalized form */
    private $normalizedExpression = null;

    /** @var array<string, XPathExpression> Static expression cache */
    private static $cache = array();

    /** @var int Maximum cache size */
    private static $maxCacheSize = 256;

    /**
     * @param string $expression The XPath expression
     * @param XPathNamespaceContext|null $namespaceContext
     */
    public function __construct($expression, $namespaceContext = null)
    {
        $this->expression = $expression;
        $this->namespaceContext = $namespaceContext;
    }

    /**
     * Compile and cache an XPath expression.
     *
     * @param string $expression
     * @param XPathNamespaceContext|null $namespaceContext
     * @return XPathExpression
     */
    public static function compile($expression, $namespaceContext = null)
    {
        $cacheKey = self::computeCacheKey($expression, $namespaceContext);

        if (isset(self::$cache[$cacheKey])) {
            $cached = self::$cache[$cacheKey];
            // Return a clone so variable bindings don't leak
            return $cached->copy();
        }

        $compiled = new self($expression, $namespaceContext);
        $compiled->normalize();

        // Evict oldest entries if cache is full
        if (count(self::$cache) >= self::$maxCacheSize) {
            $keys = array_keys(self::$cache);
            $removeCount = (int) (self::$maxCacheSize / 4);
            for ($i = 0; $i < $removeCount; $i++) {
                unset(self::$cache[$keys[$i]]);
            }
        }

        self::$cache[$cacheKey] = $compiled;
        return $compiled->copy();
    }

    /**
     * Get the raw XPath expression string.
     *
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * Get the normalized expression (with variables substituted).
     *
     * @return string
     */
    public function getNormalizedExpression()
    {
        if ($this->normalizedExpression === null) {
            $this->normalize();
        }

        $expr = $this->normalizedExpression;

        // Substitute bound variables
        if (!empty($this->variables)) {
            foreach ($this->variables as $name => $value) {
                $placeholder = '$' . $name;
                if (strpos($expr, $placeholder) !== false) {
                    $replacement = self::escapeValue($value);
                    $expr = str_replace($placeholder, $replacement, $expr);
                }
            }
        }

        return $expr;
    }

    /**
     * Get the namespace context.
     *
     * @return XPathNamespaceContext|null
     */
    public function getNamespaceContext()
    {
        return $this->namespaceContext;
    }

    /**
     * Set the namespace context.
     *
     * @param XPathNamespaceContext $context
     * @return $this
     */
    public function setNamespaceContext(XPathNamespaceContext $context)
    {
        $this->namespaceContext = $context;
        return $this;
    }

    /**
     * Bind a variable value.
     *
     * @param string $name Variable name (without $)
     * @param mixed $value
     * @return $this
     */
    public function bindVariable($name, $value)
    {
        $this->variables[$name] = $value;
        return $this;
    }

    /**
     * Bind multiple variables.
     *
     * @param array<string, mixed> $variables
     * @return $this
     */
    public function bindVariables(array $variables)
    {
        foreach ($variables as $name => $value) {
            $this->variables[$name] = $value;
        }
        return $this;
    }

    /**
     * Get all bound variables.
     *
     * @return array<string, mixed>
     */
    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * Clear all bound variables.
     *
     * @return $this
     */
    public function clearVariables()
    {
        $this->variables = array();
        return $this;
    }

    /**
     * Check if this expression uses any variables.
     *
     * @return bool
     */
    public function hasVariables()
    {
        return preg_match('/\$[a-zA-Z_]\w*/', $this->expression) === 1;
    }

    /**
     * Get the list of variable names used in the expression.
     *
     * @return string[]
     */
    public function getVariableNames()
    {
        $names = array();
        if (preg_match_all('/\$([a-zA-Z_]\w*)/', $this->expression, $matches)) {
            $names = array_unique($matches[1]);
        }
        return $names;
    }

    /**
     * Check if expression is an absolute path (starts with /).
     *
     * @return bool
     */
    public function isAbsolute()
    {
        $trimmed = ltrim($this->expression);
        return isset($trimmed[0]) && $trimmed[0] === '/';
    }

    /**
     * Check if expression contains a predicate.
     *
     * @return bool
     */
    public function hasPredicate()
    {
        return strpos($this->expression, '[') !== false;
    }

    /**
     * Create a copy of this expression.
     *
     * @return XPathExpression
     */
    public function copy()
    {
        $clone = new self($this->expression, $this->namespaceContext);
        $clone->variables = $this->variables;
        $clone->normalizedExpression = $this->normalizedExpression;
        return $clone;
    }

    /**
     * Normalize the expression (trim whitespace, etc.).
     *
     * @return void
     */
    private function normalize()
    {
        $this->normalizedExpression = trim($this->expression);
    }

    /**
     * Escape a PHP value for XPath substitution.
     *
     * @param mixed $value
     * @return string
     */
    private static function escapeValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'true()' : 'false()';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_null($value)) {
            return "''";
        }
        $str = (string) $value;
        // Handle XPath string escaping: if value contains both ' and ", use concat()
        if (strpos($str, "'") === false) {
            return "'" . $str . "'";
        }
        if (strpos($str, '"') === false) {
            return '"' . $str . '"';
        }
        // Contains both - use concat
        $parts = array();
        $segments = explode("'", $str);
        foreach ($segments as $i => $segment) {
            if ($i > 0) {
                $parts[] = "\"'\"";
            }
            if ($segment !== '') {
                $parts[] = "'" . $segment . "'";
            }
        }
        return 'concat(' . implode(',', $parts) . ')';
    }

    /**
     * Compute cache key for an expression + context combination.
     *
     * @param string $expression
     * @param XPathNamespaceContext|null $context
     * @return string
     */
    private static function computeCacheKey($expression, $context)
    {
        $key = $expression;
        if ($context !== null) {
            $ns = $context->getAllNamespaces();
            ksort($ns);
            $key .= '|' . serialize($ns);
        }
        return md5($key);
    }

    /**
     * Clear the expression cache.
     *
     * @return void
     */
    public static function clearCache()
    {
        self::$cache = array();
    }

    /**
     * Get current cache size.
     *
     * @return int
     */
    public static function getCacheSize()
    {
        return count(self::$cache);
    }

    /**
     * Set maximum cache size.
     *
     * @param int $size
     * @return void
     */
    public static function setMaxCacheSize($size)
    {
        self::$maxCacheSize = max(1, (int) $size);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->expression;
    }
}
