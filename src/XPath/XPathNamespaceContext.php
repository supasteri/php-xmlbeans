<?php

namespace XmlBeans\XPath;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Manages namespace prefix-to-URI mappings for XPath evaluation.
 *
 * Supports manual registration, auto-detection from DOM documents,
 * and predefined namespace sets (XML, XSD, XSLT, etc.).
 *
 * Compatible with PHP 7.2+.
 */
class XPathNamespaceContext
{
    /** @var array<string, string> prefix => URI */
    private $namespaces = array();

    /** @var string|null Default element namespace URI */
    private $defaultNamespaceUri = null;

    /** Well-known namespace URIs */
    const NS_XML = 'http://www.w3.org/XML/1998/namespace';
    const NS_XMLNS = 'http://www.w3.org/2000/xmlns/';
    const NS_XSD = 'http://www.w3.org/2001/XMLSchema';
    const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    const NS_XSLT = 'http://www.w3.org/1999/XSL/Transform';
    const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    const NS_SOAP12 = 'http://www.w3.org/2003/05/soap-envelope';
    const NS_WSDL = 'http://schemas.xmlsoap.org/wsdl/';

    public function __construct()
    {
        // Always register the xml prefix
        $this->namespaces['xml'] = self::NS_XML;
    }

    /**
     * Register a namespace prefix-URI mapping.
     *
     * @param string $prefix
     * @param string $namespaceUri
     * @return $this
     */
    public function registerNamespace($prefix, $namespaceUri)
    {
        $this->namespaces[$prefix] = $namespaceUri;
        return $this;
    }

    /**
     * Register multiple namespaces at once.
     *
     * @param array<string, string> $namespaces prefix => URI
     * @return $this
     */
    public function registerNamespaces(array $namespaces)
    {
        foreach ($namespaces as $prefix => $uri) {
            $this->namespaces[$prefix] = $uri;
        }
        return $this;
    }

    /**
     * Unregister a namespace prefix.
     *
     * @param string $prefix
     * @return $this
     */
    public function unregisterNamespace($prefix)
    {
        if ($prefix !== 'xml') {
            unset($this->namespaces[$prefix]);
        }
        return $this;
    }

    /**
     * Get the URI for a given prefix.
     *
     * @param string $prefix
     * @return string|null
     */
    public function getNamespaceUri($prefix)
    {
        if ($prefix === '' && $this->defaultNamespaceUri !== null) {
            return $this->defaultNamespaceUri;
        }
        return isset($this->namespaces[$prefix]) ? $this->namespaces[$prefix] : null;
    }

    /**
     * Get the prefix for a given URI.
     *
     * @param string $namespaceUri
     * @return string|null
     */
    public function getPrefix($namespaceUri)
    {
        $result = array_search($namespaceUri, $this->namespaces, true);
        return $result !== false ? $result : null;
    }

    /**
     * Get all prefixes bound to a given URI.
     *
     * @param string $namespaceUri
     * @return string[]
     */
    public function getPrefixes($namespaceUri)
    {
        return array_keys($this->namespaces, $namespaceUri, true);
    }

    /**
     * Get all registered namespace mappings.
     *
     * @return array<string, string>
     */
    public function getAllNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * Set the default element namespace.
     *
     * @param string $namespaceUri
     * @return $this
     */
    public function setDefaultNamespace($namespaceUri)
    {
        $this->defaultNamespaceUri = $namespaceUri;
        return $this;
    }

    /**
     * Get the default element namespace.
     *
     * @return string|null
     */
    public function getDefaultNamespace()
    {
        return $this->defaultNamespaceUri;
    }

    /**
     * Auto-detect and register namespaces from a DOM document.
     * Walks the entire document tree to find all xmlns declarations.
     *
     * @param DOMDocument $doc
     * @return $this
     */
    public function loadFromDocument(DOMDocument $doc)
    {
        $xpath = new DOMXPath($doc);

        // Collect all namespace nodes
        $nodes = $xpath->query('//namespace::*');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $prefix = $node->localName;
                $uri = $node->nodeValue;
                if ($prefix === 'xml' || $prefix === 'xmlns') {
                    continue;
                }
                if ($prefix === 'default' || $prefix === '') {
                    // Default namespace
                    if ($this->defaultNamespaceUri === null) {
                        $this->defaultNamespaceUri = $uri;
                    }
                } else {
                    if (!isset($this->namespaces[$prefix])) {
                        $this->namespaces[$prefix] = $uri;
                    }
                }
            }
        }

        // Also check root element attributes
        $root = $doc->documentElement;
        if ($root instanceof DOMElement) {
            $attrs = $root->attributes;
            if ($attrs !== null) {
                for ($i = 0; $i < $attrs->length; $i++) {
                    $attr = $attrs->item($i);
                    if ($attr->nodeName === 'xmlns') {
                        if ($this->defaultNamespaceUri === null) {
                            $this->defaultNamespaceUri = $attr->nodeValue;
                        }
                    } elseif (strpos($attr->nodeName, 'xmlns:') === 0) {
                        $prefix = substr($attr->nodeName, 6);
                        if (!isset($this->namespaces[$prefix])) {
                            $this->namespaces[$prefix] = $attr->nodeValue;
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Auto-detect and register namespaces from a DOM element and its ancestors.
     *
     * @param DOMElement $element
     * @return $this
     */
    public function loadFromElement(DOMElement $element)
    {
        $node = $element;
        while ($node instanceof DOMElement) {
            $attrs = $node->attributes;
            if ($attrs !== null) {
                for ($i = 0; $i < $attrs->length; $i++) {
                    $attr = $attrs->item($i);
                    if ($attr->nodeName === 'xmlns') {
                        if ($this->defaultNamespaceUri === null) {
                            $this->defaultNamespaceUri = $attr->nodeValue;
                        }
                    } elseif (strpos($attr->nodeName, 'xmlns:') === 0) {
                        $prefix = substr($attr->nodeName, 6);
                        if (!isset($this->namespaces[$prefix])) {
                            $this->namespaces[$prefix] = $attr->nodeValue;
                        }
                    }
                }
            }
            $node = $node->parentNode;
        }

        return $this;
    }

    /**
     * Register well-known XML Schema namespaces (xs/xsd, xsi).
     *
     * @return $this
     */
    public function registerSchemaNamespaces()
    {
        $this->namespaces['xs'] = self::NS_XSD;
        $this->namespaces['xsd'] = self::NS_XSD;
        $this->namespaces['xsi'] = self::NS_XSI;
        return $this;
    }

    /**
     * Register well-known SOAP/WSDL namespaces.
     *
     * @return $this
     */
    public function registerSoapNamespaces()
    {
        $this->namespaces['soap'] = self::NS_SOAP;
        $this->namespaces['soap12'] = self::NS_SOAP12;
        $this->namespaces['wsdl'] = self::NS_WSDL;
        return $this;
    }

    /**
     * Apply all registered namespaces to a DOMXPath instance.
     *
     * @param DOMXPath $xpath
     * @return void
     */
    public function applyTo(DOMXPath $xpath)
    {
        foreach ($this->namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
        if ($this->defaultNamespaceUri !== null) {
            // Register default NS under a synthetic prefix if no explicit one exists
            $existingPrefix = $this->getPrefix($this->defaultNamespaceUri);
            if ($existingPrefix === null || $existingPrefix === 'xml') {
                $xpath->registerNamespace('_default', $this->defaultNamespaceUri);
            }
        }
    }

    /**
     * Merge another context into this one. Existing bindings are not overwritten.
     *
     * @param XPathNamespaceContext $other
     * @return $this
     */
    public function merge(XPathNamespaceContext $other)
    {
        foreach ($other->getAllNamespaces() as $prefix => $uri) {
            if (!isset($this->namespaces[$prefix])) {
                $this->namespaces[$prefix] = $uri;
            }
        }
        if ($this->defaultNamespaceUri === null && $other->getDefaultNamespace() !== null) {
            $this->defaultNamespaceUri = $other->getDefaultNamespace();
        }
        return $this;
    }

    /**
     * Create a copy of this context.
     *
     * @return XPathNamespaceContext
     */
    public function copy()
    {
        $clone = new self();
        $clone->namespaces = $this->namespaces;
        $clone->defaultNamespaceUri = $this->defaultNamespaceUri;
        return $clone;
    }

    /**
     * Check if a prefix is registered.
     *
     * @param string $prefix
     * @return bool
     */
    public function hasPrefix($prefix)
    {
        return isset($this->namespaces[$prefix]);
    }

    /**
     * Clear all registered namespaces except xml.
     *
     * @return $this
     */
    public function clear()
    {
        $this->namespaces = array('xml' => self::NS_XML);
        $this->defaultNamespaceUri = null;
        return $this;
    }
}
