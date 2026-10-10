<?php

namespace XmlBeans\XPath;

/**
 * Helper class for building XPath predicates fluently.
 *
 * Compatible with PHP 7.2+.
 */
class XPathPredicate
{
    /** @var string */
    private $expression;

    /**
     * @param string $expression
     */
    public function __construct($expression)
    {
        $this->expression = $expression;
    }

    /**
     * Equals comparison (=).
     *
     * @param mixed $value
     * @return XPathPredicate
     */
    public function eq($value)
    {
        return new self($this->expression . ' = ' . self::quote($value));
    }

    /**
     * Not equals comparison (!=).
     *
     * @param mixed $value
     * @return XPathPredicate
     */
    public function ne($value)
    {
        return new self($this->expression . ' != ' . self::quote($value));
    }

    /**
     * Greater than (>).
     *
     * @param mixed $value
     * @return XPathPredicate
     */
    public function gt($value)
    {
        return new self($this->expression . ' > ' . self::quote($value));
    }

    /**
     * Greater than or equal (>=).
     *
     * @param mixed $value
     * @return XPathPredicate
     */
    public function gte($value)
    {
        return new self($this->expression . ' >= ' . self::quote($value));
    }

    /**
     * Less than (<).
     *
     * @param mixed $value
     * @return XPathPredicate
     */
    public function lt($value)
    {
        return new self($this->expression . ' < ' . self::quote($value));
    }

    /**
     * Less than or equal (<=).
     *
     * @param mixed $value
     * @return XPathPredicate
     */
    public function lte($value)
    {
        return new self($this->expression . ' <= ' . self::quote($value));
    }

    /**
     * Logical AND.
     *
     * @param string|XPathPredicate $other
     * @return XPathPredicate
     */
    public function andPred($other)
    {
        $expr = ($other instanceof XPathPredicate) ? $other->toXPath() : (string) $other;
        return new self($this->expression . ' and ' . $expr);
    }

    /**
     * Logical OR.
     *
     * @param string|XPathPredicate $other
     * @return XPathPredicate
     */
    public function orPred($other)
    {
        $expr = ($other instanceof XPathPredicate) ? $other->toXPath() : (string) $other;
        return new self($this->expression . ' or ' . $expr);
    }

    /**
     * contains() function predicate.
     *
     * @param string $substring
     * @return XPathPredicate
     */
    public function contains($substring)
    {
        return new self('contains(' . $this->expression . ', ' . self::quote($substring) . ')');
    }

    /**
     * starts-with() function predicate.
     *
     * @param string $prefix
     * @return XPathPredicate
     */
    public function startsWith($prefix)
    {
        return new self('starts-with(' . $this->expression . ', ' . self::quote($prefix) . ')');
    }

    /**
     * normalize-space() function.
     *
     * @return XPathPredicate
     */
    public function normalizeSpace()
    {
        return new self('normalize-space(' . $this->expression . ')');
    }

    /**
     * string-length() function.
     *
     * @return XPathPredicate
     */
    public function stringLength()
    {
        return new self('string-length(' . $this->expression . ')');
    }

    /**
     * Existence check (no comparison, just the expression).
     *
     * @return XPathPredicate
     */
    public function exists()
    {
        return $this;
    }

    /**
     * Get the XPath expression string.
     *
     * @return string
     */
    public function toXPath()
    {
        return $this->expression;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->expression;
    }

    /**
     * Quote a value for XPath use.
     *
     * @param mixed $value
     * @return string
     */
    private static function quote($value)
    {
        if (is_bool($value)) {
            return $value ? 'true()' : 'false()';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        $str = (string) $value;
        if (strpos($str, "'") === false) {
            return "'" . $str . "'";
        }
        if (strpos($str, '"') === false) {
            return '"' . $str . '"';
        }
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
}
