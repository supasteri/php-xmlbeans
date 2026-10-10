<?php

namespace XmlBeans\Common;

/**
 * Represents an XML qualified name (namespace URI + local name).
 * Equivalent to javax.xml.namespace.QName.
 */
class QName
{
    /** @var string */
    private $namespaceURI;

    /** @var string */
    private $localPart;

    /** @var string */
    private $prefix;

    /**
     * @param string $namespaceURI
     * @param string $localPart
     * @param string $prefix
     */
    public function __construct($namespaceURI, $localPart, $prefix = '')
    {
        $this->namespaceURI = (string) $namespaceURI;
        $this->localPart = (string) $localPart;
        $this->prefix = (string) $prefix;
    }

    /**
     * @return string
     */
    public function getNamespaceURI()
    {
        return $this->namespaceURI;
    }

    /**
     * @return string
     */
    public function getLocalPart()
    {
        return $this->localPart;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param QName $other
     * @return bool
     */
    public function equals(QName $other)
    {
        return $this->namespaceURI === $other->namespaceURI
            && $this->localPart === $other->localPart;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->namespaceURI === '') {
            return $this->localPart;
        }
        return '{' . $this->namespaceURI . '}' . $this->localPart;
    }

    /**
     * Parse a Clark notation string {namespace}local into a QName.
     *
     * @param string $clark
     * @return self
     */
    public static function fromClark($clark)
    {
        if (preg_match('/^\{([^}]*)\}(.+)$/', $clark, $m)) {
            return new self($m[1], $m[2]);
        }
        return new self('', $clark);
    }

    /**
     * @return string A hash key suitable for array indexing.
     */
    public function hashKey()
    {
        return $this->namespaceURI . '|' . $this->localPart;
    }
}
