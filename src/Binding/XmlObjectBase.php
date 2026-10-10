<?php

namespace XmlBeans\Binding;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use XmlBeans\Common\QName;
use XmlBeans\Cursor\XmlCursor;
use XmlBeans\SchemaType;
use XmlBeans\XmlError;
use XmlBeans\XmlObject;
use XmlBeans\XmlOptions;
use XmlBeans\Validation\Validator;

/**
 * Base implementation of XmlObject backed by PHP's DOM.
 * All generated typed classes extend this.
 */
class XmlObjectBase implements XmlObject
{
    /** @var DOMNode */
    protected $domNode;

    /** @var DOMDocument|null */
    protected $ownerDoc;

    /** @var SchemaType|null */
    protected $schemaType;

    /** @var XmlOptions|null */
    protected $options;

    /**
     * @param DOMNode|null    $node
     * @param SchemaType|null $type
     */
    public function __construct(DOMNode $node = null, SchemaType $type = null)
    {
        if ($node === null) {
            $this->ownerDoc = new DOMDocument('1.0', 'UTF-8');
            $this->domNode = $this->ownerDoc;
        } else {
            $this->domNode = $node;
            $this->ownerDoc = ($node instanceof DOMDocument) ? $node : $node->ownerDocument;
        }
        $this->schemaType = $type;
    }

    /** {@inheritdoc} */
    public function schemaType()
    {
        return $this->schemaType;
    }

    /**
     * @param SchemaType $type
     */
    public function setSchemaType(SchemaType $type)
    {
        $this->schemaType = $type;
    }

    /** {@inheritdoc} */
    public function validate($options = null)
    {
        $validator = new Validator();
        return $validator->validate($this, $options);
    }

    /** {@inheritdoc} */
    public function newCursor()
    {
        return new XmlCursor($this->domNode);
    }

    /** {@inheritdoc} */
    public function getDomNode()
    {
        return $this->domNode;
    }

    /**
     * Replace the underlying DOM node.
     *
     * @param DOMNode $node
     */
    public function setDomNode(DOMNode $node)
    {
        $this->domNode = $node;
        $this->ownerDoc = ($node instanceof DOMDocument) ? $node : $node->ownerDocument;
    }

    /** {@inheritdoc} */
    public function setStringValue($value)
    {
        if ($this->domNode instanceof DOMElement) {
            // Remove all children
            while ($this->domNode->firstChild) {
                $this->domNode->removeChild($this->domNode->firstChild);
            }
            $this->domNode->appendChild($this->ownerDoc->createTextNode($value));
        } elseif ($this->domNode instanceof DOMDocument) {
            $root = $this->domNode->documentElement;
            if ($root) {
                while ($root->firstChild) {
                    $root->removeChild($root->firstChild);
                }
                $root->appendChild($this->ownerDoc->createTextNode($value));
            }
        } else {
            $this->domNode->nodeValue = $value;
        }
    }

    /** {@inheritdoc} */
    public function getStringValue()
    {
        if ($this->domNode instanceof DOMDocument) {
            $root = $this->domNode->documentElement;
            return $root ? $root->textContent : '';
        }
        return $this->domNode->textContent;
    }

    /** {@inheritdoc} */
    public function xmlText($options = null)
    {
        $node = $this->domNode;

        if ($options instanceof XmlOptions && $options->getSaveInner()) {
            if ($node instanceof DOMElement) {
                $inner = '';
                foreach ($node->childNodes as $child) {
                    $inner .= $this->ownerDoc->saveXML($child);
                }
                return $inner;
            }
        }

        if ($node instanceof DOMDocument) {
            $text = $node->saveXML();
        } else {
            $text = $this->ownerDoc->saveXML($node);
        }

        if ($options instanceof XmlOptions && $options->getSavePrettyPrint()) {
            $tempDoc = new DOMDocument('1.0', 'UTF-8');
            $tempDoc->preserveWhiteSpace = false;
            $tempDoc->formatOutput = true;
            $tempDoc->loadXML($text);
            $text = $tempDoc->saveXML();
        }

        return $text;
    }

    /** {@inheritdoc} */
    public function save($filename, $options = null)
    {
        $xml = $this->xmlText($options);
        file_put_contents($filename, $xml);
    }

    /** {@inheritdoc} */
    public function copy()
    {
        $newDoc = new DOMDocument('1.0', 'UTF-8');
        if ($this->domNode instanceof DOMDocument) {
            $imported = $newDoc->importNode($this->domNode->documentElement, true);
            $newDoc->appendChild($imported);
            $copy = new static($newDoc, $this->schemaType);
        } else {
            $imported = $newDoc->importNode($this->domNode, true);
            $newDoc->appendChild($imported);
            $copy = new static($imported, $this->schemaType);
        }
        return $copy;
    }

    /** {@inheritdoc} */
    public function isNil()
    {
        if ($this->domNode instanceof DOMElement) {
            $xsiNs = 'http://www.w3.org/2001/XMLSchema-instance';
            $nilVal = $this->domNode->getAttributeNS($xsiNs, 'nil');
            return $nilVal === 'true' || $nilVal === '1';
        }
        return false;
    }

    /** {@inheritdoc} */
    public function setNil($nil = true)
    {
        if ($this->domNode instanceof DOMElement) {
            $xsiNs = 'http://www.w3.org/2001/XMLSchema-instance';
            if ($nil) {
                $this->domNode->setAttributeNS($xsiNs, 'xsi:nil', 'true');
            } else {
                $this->domNode->removeAttributeNS($xsiNs, 'nil');
            }
        }
    }

    /** {@inheritdoc} */
    public function instanceType(SchemaType $type)
    {
        if ($this->schemaType === null) {
            return false;
        }
        return $type->isAssignableFrom($this->schemaType);
    }

    /** {@inheritdoc} */
    public function selectPath($path, $options = null)
    {
        $xpath = new DOMXPath($this->ownerDoc);

        // Register namespaces if provided
        if ($options instanceof XmlOptions) {
            foreach ($options->getAdditionalNamespaces() as $prefix => $uri) {
                $xpath->registerNamespace($prefix, $uri);
            }
        }

        // Auto-register namespaces from the document element
        if ($this->ownerDoc->documentElement) {
            $attrs = $this->ownerDoc->documentElement->attributes;
            if ($attrs) {
                foreach ($attrs as $attr) {
                    if (strpos($attr->nodeName, 'xmlns:') === 0) {
                        $prefix = substr($attr->nodeName, 6);
                        $xpath->registerNamespace($prefix, $attr->nodeValue);
                    }
                }
            }
        }

        $result = $xpath->query($path, $this->domNode);
        $objects = [];
        if ($result !== false) {
            foreach ($result as $node) {
                $objects[] = new static($node, null);
            }
        }
        return $objects;
    }

    /** {@inheritdoc} */
    public function selectAttribute($path, $options = null)
    {
        $results = $this->selectPath($path, $options);
        return count($results) > 0 ? $results[0] : null;
    }

    /**
     * Select child elements by local name, optionally filtered by namespace.
     *
     * @param string $localName Element local name
     * @param string|null $namespaceUri Optional namespace URI filter
     * @return XmlObjectBase[]
     */
    public function selectChildren($localName, $namespaceUri = null)
    {
        $engine = $this->createXPathEngine();
        $contextNode = $this->domNode;
        if ($contextNode instanceof \DOMDocument) {
            $contextNode = $contextNode->documentElement;
        }
        return $engine->selectChildren($contextNode, $localName, $namespaceUri);
    }

    /**
     * Select descendant elements by local name, optionally filtered by namespace.
     *
     * @param string $localName Element local name
     * @param string|null $namespaceUri Optional namespace URI filter
     * @return XmlObjectBase[]
     */
    public function selectElements($localName, $namespaceUri = null)
    {
        $engine = $this->createXPathEngine();
        $contextNode = $this->domNode;
        if ($contextNode instanceof \DOMDocument) {
            $contextNode = $contextNode->documentElement;
        }
        return $engine->selectElements($contextNode, $localName, $namespaceUri);
    }

    /**
     * Execute XPath and return typed XmlObject results.
     *
     * @param string|\XmlBeans\XPath\XPathExpression $expression
     * @param \XmlBeans\XPath\XPathNamespaceContext|null $namespaceContext
     * @return XmlObjectBase[]
     */
    public function selectPathTyped($expression, $namespaceContext = null)
    {
        $engine = $this->createXPathEngine();
        if ($namespaceContext !== null) {
            $engine->setNamespaceContext($namespaceContext);
        }
        return $engine->selectTyped($expression, $this->domNode);
    }

    /**
     * Execute XPath and return scalar string value.
     *
     * @param string $expression
     * @return string
     */
    public function selectPathString($expression)
    {
        $engine = $this->createXPathEngine();
        return $engine->selectString($expression, $this->domNode);
    }

    /**
     * Execute XPath and return numeric value.
     *
     * @param string $expression
     * @return float
     */
    public function selectPathNumber($expression)
    {
        $engine = $this->createXPathEngine();
        return $engine->selectNumber($expression, $this->domNode);
    }

    /**
     * Execute XPath and return boolean value.
     *
     * @param string $expression
     * @return bool
     */
    public function selectPathBoolean($expression)
    {
        $engine = $this->createXPathEngine();
        return $engine->selectBoolean($expression, $this->domNode);
    }

    /**
     * Get an XPathEngine instance for this object.
     *
     * @return \XmlBeans\XPath\XPathEngine
     */
    public function createXPathEngine()
    {
        return \XmlBeans\XPath\XPathEngine::fromXmlObject($this);
    }

    /** {@inheritdoc} */
    public static function parse($xml, $type = null, $options = null)
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        if ($options instanceof XmlOptions) {
            $doc->preserveWhiteSpace = !$options->getLoadStripWhitespace();
        }

        libxml_use_internal_errors(true);
        $success = $doc->loadXML($xml);

        if (!$success) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $xmlErrors = [];
            foreach ($errors as $err) {
                $xmlErrors[] = new XmlError(
                    $err->level === LIBXML_ERR_WARNING ? XmlError::SEVERITY_WARNING : XmlError::SEVERITY_ERROR,
                    trim($err->message),
                    $err->file ?: null,
                    $err->line,
                    $err->column
                );
            }
            if ($options instanceof XmlOptions) {
                $errRef = &$options->getValidationErrors();
                foreach ($xmlErrors as $e) {
                    $errRef[] = $e;
                }
            }
            throw new \RuntimeException('XML parse error: ' . ($xmlErrors ? (string) $xmlErrors[0] : 'unknown'));
        }
        libxml_use_internal_errors(false);

        if ($options instanceof XmlOptions && $options->getLoadStripComments()) {
            $xpath = new DOMXPath($doc);
            $comments = $xpath->query('//comment()');
            foreach ($comments as $comment) {
                $comment->parentNode->removeChild($comment);
            }
        }

        $obj = new static($doc, $type);
        return $obj;
    }

    /** {@inheritdoc} */
    public static function parseFile($filename, $type = null, $options = null)
    {
        $xml = file_get_contents($filename);
        if ($xml === false) {
            throw new \RuntimeException("Cannot read file: $filename");
        }
        return static::parse($xml, $type, $options);
    }

    /** {@inheritdoc} */
    public static function newInstance()
    {
        return new static();
    }

    // -----------------------------------------------------------------
    // Convenience accessors for typed child elements and attributes
    // -----------------------------------------------------------------

    /**
     * Get the text content of a child element by local name.
     *
     * @param string $localName
     * @param string $ns
     * @return string|null
     */
    protected function getChildText($localName, $ns = '')
    {
        $el = $this->getChildElement($localName, $ns);
        return $el ? $el->textContent : null;
    }

    /**
     * Set the text content of a child element, creating it if needed.
     *
     * @param string $localName
     * @param string $value
     * @param string $ns
     */
    protected function setChildText($localName, $value, $ns = '')
    {
        $el = $this->getChildElement($localName, $ns);
        $parent = ($this->domNode instanceof DOMDocument)
            ? $this->domNode->documentElement
            : $this->domNode;

        if (!$parent) {
            return;
        }

        if ($el === null) {
            if ($ns !== '') {
                $el = $this->ownerDoc->createElementNS($ns, $localName);
            } else {
                $el = $this->ownerDoc->createElement($localName);
            }
            $parent->appendChild($el);
        } else {
            while ($el->firstChild) {
                $el->removeChild($el->firstChild);
            }
        }
        $el->appendChild($this->ownerDoc->createTextNode((string) $value));
    }

    /**
     * Get a child element DOM node.
     *
     * @param string $localName
     * @param string $ns
     * @return DOMElement|null
     */
    protected function getChildElement($localName, $ns = '')
    {
        $parent = ($this->domNode instanceof DOMDocument)
            ? $this->domNode->documentElement
            : $this->domNode;

        if (!$parent) {
            return null;
        }

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($ns !== '' && $child->localName === $localName && $child->namespaceURI === $ns) {
                    return $child;
                } elseif ($ns === '' && ($child->localName === $localName || $child->nodeName === $localName)) {
                    return $child;
                }
            }
        }
        return null;
    }

    /**
     * Get all child elements with the given name.
     *
     * @param string $localName
     * @param string $ns
     * @return DOMElement[]
     */
    protected function getChildElements($localName, $ns = '')
    {
        $parent = ($this->domNode instanceof DOMDocument)
            ? $this->domNode->documentElement
            : $this->domNode;

        if (!$parent) {
            return [];
        }

        $result = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($ns !== '' && $child->localName === $localName && $child->namespaceURI === $ns) {
                    $result[] = $child;
                } elseif ($ns === '' && ($child->localName === $localName || $child->nodeName === $localName)) {
                    $result[] = $child;
                }
            }
        }
        return $result;
    }

    /**
     * Wrap child elements as XmlObject array.
     *
     * @param string $localName
     * @param string $ns
     * @return XmlObjectBase[]
     */
    protected function getChildObjectArray($localName, $ns = '')
    {
        $elements = $this->getChildElements($localName, $ns);
        $result = [];
        foreach ($elements as $el) {
            $result[] = new static($el, null);
        }
        return $result;
    }

    /**
     * Get an attribute value.
     *
     * @param string $name
     * @param string $ns
     * @return string|null
     */
    protected function getAttributeValue($name, $ns = '')
    {
        if (!($this->domNode instanceof DOMElement)) {
            return null;
        }
        if ($ns !== '') {
            $val = $this->domNode->getAttributeNS($ns, $name);
            return $val !== '' ? $val : null;
        }
        if ($this->domNode->hasAttribute($name)) {
            return $this->domNode->getAttribute($name);
        }
        return null;
    }

    /**
     * Set an attribute value.
     *
     * @param string $name
     * @param string $value
     * @param string $ns
     */
    protected function setAttributeValue($name, $value, $ns = '')
    {
        if (!($this->domNode instanceof DOMElement)) {
            return;
        }
        if ($ns !== '') {
            $this->domNode->setAttributeNS($ns, $name, $value);
        } else {
            $this->domNode->setAttribute($name, $value);
        }
    }

    /**
     * Add a new child element with text content.
     *
     * @param string $localName
     * @param string $value
     * @param string $ns
     * @return DOMElement
     */
    protected function addChildElement($localName, $value = '', $ns = '')
    {
        $parent = ($this->domNode instanceof DOMDocument)
            ? $this->domNode->documentElement
            : $this->domNode;

        if ($ns !== '') {
            $el = $this->ownerDoc->createElementNS($ns, $localName);
        } else {
            $el = $this->ownerDoc->createElement($localName);
        }

        if ($value !== '') {
            $el->appendChild($this->ownerDoc->createTextNode($value));
        }

        $parent->appendChild($el);
        return $el;
    }

    /**
     * Remove a child element by local name.
     *
     * @param string $localName
     * @param string $ns
     * @return bool
     */
    protected function removeChildElement($localName, $ns = '')
    {
        $el = $this->getChildElement($localName, $ns);
        if ($el && $el->parentNode) {
            $el->parentNode->removeChild($el);
            return true;
        }
        return false;
    }
}
