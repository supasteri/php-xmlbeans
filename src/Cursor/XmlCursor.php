<?php

namespace XmlBeans\Cursor;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMComment;
use DOMProcessingInstruction;

/**
 * Provides cursor-based navigation over an XML document.
 * Mirrors org.apache.xmlbeans.XmlCursor.
 *
 * A cursor is always positioned at a specific location within an XML
 * document tree. It can move forward, backward, into children,
 * insert, delete, and read/write values.
 */
class XmlCursor
{
    // Token types
    const TOKEN_NONE = 0;
    const TOKEN_STARTDOC = 1;
    const TOKEN_ENDDOC = 2;
    const TOKEN_START = 3;
    const TOKEN_END = 4;
    const TOKEN_TEXT = 5;
    const TOKEN_ATTR = 6;
    const TOKEN_NAMESPACE = 7;
    const TOKEN_COMMENT = 8;
    const TOKEN_PROCINST = 9;

    /** @var DOMNode */
    private $currentNode;

    /** @var DOMDocument */
    private $document;

    /** @var bool Whether we are at the end-tag position of an element */
    private $atEnd = false;

    /** @var int Index into attributes when iterating */
    private $attrIndex = -1;

    /** @var array Bookmark storage */
    private $bookmarks = [];

    /**
     * @param DOMNode $node Starting position
     */
    public function __construct(DOMNode $node)
    {
        $this->currentNode = $node;
        $this->document = ($node instanceof DOMDocument) ? $node : $node->ownerDocument;
    }

    /**
     * Get the current token type.
     *
     * @return int One of the TOKEN_* constants
     */
    public function currentTokenType()
    {
        if ($this->atEnd) {
            if ($this->currentNode instanceof DOMDocument) {
                return self::TOKEN_ENDDOC;
            }
            return self::TOKEN_END;
        }

        if ($this->currentNode instanceof DOMDocument) {
            return self::TOKEN_STARTDOC;
        }
        if ($this->currentNode instanceof DOMElement) {
            return self::TOKEN_START;
        }
        if ($this->currentNode instanceof DOMText) {
            return self::TOKEN_TEXT;
        }
        if ($this->currentNode instanceof DOMAttr) {
            return self::TOKEN_ATTR;
        }
        if ($this->currentNode instanceof DOMComment) {
            return self::TOKEN_COMMENT;
        }
        if ($this->currentNode instanceof DOMProcessingInstruction) {
            return self::TOKEN_PROCINST;
        }
        return self::TOKEN_NONE;
    }

    /**
     * Returns a human-readable name for a token type.
     *
     * @param int $type
     * @return string
     */
    public static function tokenTypeName($type)
    {
        $names = [
            self::TOKEN_NONE => 'NONE',
            self::TOKEN_STARTDOC => 'STARTDOC',
            self::TOKEN_ENDDOC => 'ENDDOC',
            self::TOKEN_START => 'START',
            self::TOKEN_END => 'END',
            self::TOKEN_TEXT => 'TEXT',
            self::TOKEN_ATTR => 'ATTR',
            self::TOKEN_NAMESPACE => 'NAMESPACE',
            self::TOKEN_COMMENT => 'COMMENT',
            self::TOKEN_PROCINST => 'PROCINST',
        ];
        return isset($names[$type]) ? $names[$type] : 'UNKNOWN';
    }

    /**
     * Move to the next token (depth-first traversal).
     *
     * @return int Token type at new position
     */
    public function toNextToken()
    {
        if ($this->atEnd) {
            // Move from end-tag to next sibling or parent's end
            $next = $this->currentNode->nextSibling;
            if ($next !== null) {
                $this->currentNode = $next;
                $this->atEnd = false;
            } else {
                // Move up
                $parent = $this->currentNode->parentNode;
                if ($parent !== null) {
                    $this->currentNode = $parent;
                    $this->atEnd = true;
                } else {
                    return self::TOKEN_NONE;
                }
            }
            return $this->currentTokenType();
        }

        // If we have children, descend
        if ($this->currentNode->firstChild !== null
            && !($this->currentNode instanceof DOMText)
            && !($this->currentNode instanceof DOMAttr)
            && !($this->currentNode instanceof DOMComment)) {
            $this->currentNode = $this->currentNode->firstChild;
            $this->atEnd = false;
            return $this->currentTokenType();
        }

        // For elements with no children, move to end-tag
        if ($this->currentNode instanceof DOMElement) {
            $this->atEnd = true;
            return self::TOKEN_END;
        }

        // Move to next sibling
        $next = $this->currentNode->nextSibling;
        if ($next !== null) {
            $this->currentNode = $next;
            $this->atEnd = false;
            return $this->currentTokenType();
        }

        // No next sibling, move up
        $parent = $this->currentNode->parentNode;
        if ($parent !== null) {
            $this->currentNode = $parent;
            $this->atEnd = true;
            return $this->currentTokenType();
        }

        return self::TOKEN_NONE;
    }

    /**
     * Move to the previous token.
     *
     * @return int Token type at new position
     */
    public function toPrevToken()
    {
        if ($this->atEnd) {
            // Move from end-tag back to last child or start-tag
            $last = $this->currentNode->lastChild;
            if ($last !== null) {
                $this->currentNode = $last;
                if ($last instanceof DOMElement) {
                    $this->atEnd = true; // at end of last child element
                } else {
                    $this->atEnd = false;
                }
                return $this->currentTokenType();
            }
            // No children, move to start-tag
            $this->atEnd = false;
            return $this->currentTokenType();
        }

        // Move to previous sibling's end, or to parent
        $prev = $this->currentNode->previousSibling;
        if ($prev !== null) {
            $this->currentNode = $prev;
            if ($prev instanceof DOMElement) {
                $this->atEnd = true;
            }
            return $this->currentTokenType();
        }

        // No previous sibling, move to parent start
        $parent = $this->currentNode->parentNode;
        if ($parent !== null) {
            $this->currentNode = $parent;
            $this->atEnd = false;
            return $this->currentTokenType();
        }

        return self::TOKEN_NONE;
    }

    /**
     * Move to the first child of the current element.
     *
     * @return bool True if moved successfully
     */
    public function toFirstChild()
    {
        $node = $this->currentNode;
        if ($node instanceof DOMDocument) {
            $node = $node->documentElement;
            if ($node) {
                $this->currentNode = $node;
                $this->atEnd = false;
                return true;
            }
            return false;
        }

        if ($node instanceof DOMElement && !$this->atEnd) {
            $child = $node->firstChild;
            while ($child !== null) {
                if ($child instanceof DOMElement) {
                    $this->currentNode = $child;
                    $this->atEnd = false;
                    return true;
                }
                $child = $child->nextSibling;
            }
        }
        return false;
    }

    /**
     * Move to the last child of the current element.
     *
     * @return bool
     */
    public function toLastChild()
    {
        $node = $this->currentNode;
        if ($node instanceof DOMDocument) {
            $node = $node->documentElement;
        }

        if ($node instanceof DOMElement) {
            $child = $node->lastChild;
            while ($child !== null) {
                if ($child instanceof DOMElement) {
                    $this->currentNode = $child;
                    $this->atEnd = false;
                    return true;
                }
                $child = $child->previousSibling;
            }
        }
        return false;
    }

    /**
     * Move to the next sibling element.
     *
     * @return bool
     */
    public function toNextSibling()
    {
        $node = $this->currentNode;
        $next = $node->nextSibling;
        while ($next !== null) {
            if ($next instanceof DOMElement) {
                $this->currentNode = $next;
                $this->atEnd = false;
                return true;
            }
            $next = $next->nextSibling;
        }
        return false;
    }

    /**
     * Move to the previous sibling element.
     *
     * @return bool
     */
    public function toPrevSibling()
    {
        $node = $this->currentNode;
        $prev = $node->previousSibling;
        while ($prev !== null) {
            if ($prev instanceof DOMElement) {
                $this->currentNode = $prev;
                $this->atEnd = false;
                return true;
            }
            $prev = $prev->previousSibling;
        }
        return false;
    }

    /**
     * Move to the parent.
     *
     * @return bool
     */
    public function toParent()
    {
        if ($this->atEnd) {
            $this->atEnd = false;
            return true;
        }
        $parent = $this->currentNode->parentNode;
        if ($parent !== null) {
            $this->currentNode = $parent;
            $this->atEnd = false;
            return true;
        }
        return false;
    }

    /**
     * Move to the start of the document.
     *
     * @return void
     */
    public function toStartDoc()
    {
        $this->currentNode = $this->document;
        $this->atEnd = false;
    }

    /**
     * Move to the end of the document.
     *
     * @return void
     */
    public function toEndDoc()
    {
        $this->currentNode = $this->document;
        $this->atEnd = true;
    }

    /**
     * Move to a child element by name.
     *
     * @param string $localName
     * @param string $ns
     * @return bool
     */
    public function toChild($localName, $ns = '')
    {
        $node = $this->currentNode;
        if ($node instanceof DOMDocument) {
            $node = $node->documentElement;
            if ($node === null) {
                return false;
            }
            if ($this->matchesName($node, $localName, $ns)) {
                $this->currentNode = $node;
                $this->atEnd = false;
                return true;
            }
        }

        if ($node instanceof DOMElement) {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && $this->matchesName($child, $localName, $ns)) {
                    $this->currentNode = $child;
                    $this->atEnd = false;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Move to the first attribute.
     *
     * @return bool
     */
    public function toFirstAttribute()
    {
        $node = $this->currentNode;
        if ($node instanceof DOMElement && $node->attributes->length > 0) {
            $this->currentNode = $node->attributes->item(0);
            $this->atEnd = false;
            $this->attrIndex = 0;
            return true;
        }
        return false;
    }

    /**
     * Move to the next attribute.
     *
     * @return bool
     */
    public function toNextAttribute()
    {
        if ($this->currentNode instanceof DOMAttr) {
            $owner = $this->currentNode->ownerElement;
            $this->attrIndex++;
            if ($owner && $this->attrIndex < $owner->attributes->length) {
                $this->currentNode = $owner->attributes->item($this->attrIndex);
                return true;
            }
            $this->attrIndex = -1;
        }
        return false;
    }

    /**
     * Get the name of the current element or attribute.
     *
     * @return \XmlBeans\Common\QName|null
     */
    public function getName()
    {
        if ($this->currentNode instanceof DOMElement || $this->currentNode instanceof DOMAttr) {
            return new \XmlBeans\Common\QName(
                $this->currentNode->namespaceURI ?: '',
                $this->currentNode->localName ?: $this->currentNode->nodeName,
                $this->currentNode->prefix ?: ''
            );
        }
        return null;
    }

    /**
     * Get the text value at the current position.
     *
     * @return string
     */
    public function getTextValue()
    {
        if ($this->currentNode instanceof DOMAttr) {
            return $this->currentNode->value;
        }
        return $this->currentNode->textContent;
    }

    /**
     * Set the text value at the current position.
     *
     * @param string $value
     */
    public function setTextValue($value)
    {
        if ($this->currentNode instanceof DOMAttr) {
            $this->currentNode->value = $value;
            return;
        }
        if ($this->currentNode instanceof DOMElement) {
            while ($this->currentNode->firstChild) {
                $this->currentNode->removeChild($this->currentNode->firstChild);
            }
            $text = $this->document->createTextNode($value);
            $this->currentNode->appendChild($text);
        }
    }

    /**
     * Get the attribute value by name from the current element.
     *
     * @param string $name
     * @param string $ns
     * @return string|null
     */
    public function getAttributeText($name, $ns = '')
    {
        if ($this->currentNode instanceof DOMElement) {
            if ($ns !== '') {
                $val = $this->currentNode->getAttributeNS($ns, $name);
                return $val !== '' ? $val : null;
            }
            if ($this->currentNode->hasAttribute($name)) {
                return $this->currentNode->getAttribute($name);
            }
        }
        return null;
    }

    /**
     * Set an attribute value on the current element.
     *
     * @param string $name
     * @param string $value
     * @param string $ns
     * @return bool
     */
    public function setAttributeText($name, $value, $ns = '')
    {
        if ($this->currentNode instanceof DOMElement) {
            if ($ns !== '') {
                $this->currentNode->setAttributeNS($ns, $name, $value);
            } else {
                $this->currentNode->setAttribute($name, $value);
            }
            return true;
        }
        return false;
    }

    /**
     * Insert an element before the current position.
     *
     * @param string $localName
     * @param string $ns
     * @return void
     */
    public function beginElement($localName, $ns = '')
    {
        if ($ns !== '') {
            $el = $this->document->createElementNS($ns, $localName);
        } else {
            $el = $this->document->createElement($localName);
        }

        if ($this->currentNode instanceof DOMDocument) {
            $this->document->appendChild($el);
        } elseif ($this->atEnd || $this->currentNode instanceof DOMElement) {
            $this->currentNode->appendChild($el);
        } else {
            $this->currentNode->parentNode->insertBefore($el, $this->currentNode);
        }

        $this->currentNode = $el;
        $this->atEnd = false;
    }

    /**
     * Insert text at the current position.
     *
     * @param string $text
     */
    public function insertChars($text)
    {
        $textNode = $this->document->createTextNode($text);
        if ($this->currentNode instanceof DOMElement && !$this->atEnd) {
            $this->currentNode->appendChild($textNode);
        } elseif ($this->currentNode->parentNode) {
            $this->currentNode->parentNode->insertBefore($textNode, $this->currentNode->nextSibling);
        }
    }

    /**
     * Insert a comment at the current position.
     *
     * @param string $text
     */
    public function insertComment($text)
    {
        $comment = $this->document->createComment($text);
        if ($this->currentNode instanceof DOMElement) {
            $this->currentNode->appendChild($comment);
        }
    }

    /**
     * Remove the current node and its content. Cursor moves to next token.
     *
     * @return bool
     */
    public function removeXml()
    {
        $node = $this->currentNode;
        if ($node instanceof DOMDocument) {
            return false;
        }
        $next = $node->nextSibling;
        $parent = $node->parentNode;
        if ($parent) {
            $parent->removeChild($node);
        }
        if ($next) {
            $this->currentNode = $next;
        } elseif ($parent) {
            $this->currentNode = $parent;
            $this->atEnd = true;
        }
        return true;
    }

    /**
     * Copy the current node to the target cursor position.
     *
     * @param XmlCursor $target
     * @return bool
     */
    public function copyXml(XmlCursor $target)
    {
        $clone = $this->currentNode->cloneNode(true);
        $targetNode = $target->getDomNode();
        $targetDoc = ($targetNode instanceof DOMDocument) ? $targetNode : $targetNode->ownerDocument;
        $imported = $targetDoc->importNode($clone, true);

        if ($targetNode instanceof DOMElement) {
            $targetNode->appendChild($imported);
        } elseif ($targetNode instanceof DOMDocument) {
            $targetNode->appendChild($imported);
        }
        return true;
    }

    /**
     * Move the current node to the target cursor position.
     *
     * @param XmlCursor $target
     * @return bool
     */
    public function moveXml(XmlCursor $target)
    {
        if ($this->copyXml($target)) {
            return $this->removeXml();
        }
        return false;
    }

    /**
     * Save a named bookmark at the current position.
     *
     * @param string $name
     */
    public function setBookmark($name)
    {
        $this->bookmarks[$name] = [
            'node' => $this->currentNode,
            'atEnd' => $this->atEnd,
        ];
    }

    /**
     * Return to a previously saved bookmark.
     *
     * @param string $name
     * @return bool
     */
    public function toBookmark($name)
    {
        if (isset($this->bookmarks[$name])) {
            $this->currentNode = $this->bookmarks[$name]['node'];
            $this->atEnd = $this->bookmarks[$name]['atEnd'];
            return true;
        }
        return false;
    }

    /**
     * Get the current DOM node the cursor is on.
     *
     * @return DOMNode
     */
    public function getDomNode()
    {
        return $this->currentNode;
    }

    /**
     * Check if cursor is at the end position.
     *
     * @return bool
     */
    public function isAtEnd()
    {
        return $this->atEnd;
    }

    /**
     * Compare positions of two cursors.
     *
     * @param XmlCursor $other
     * @return int -1, 0, or 1
     */
    public function comparePosition(XmlCursor $other)
    {
        if ($this->currentNode === $other->currentNode && $this->atEnd === $other->atEnd) {
            return 0;
        }
        // Use DOM position comparison
        $cmp = $this->currentNode->compareDocumentPosition($other->currentNode);
        if ($cmp & 4) { // DOCUMENT_POSITION_FOLLOWING
            return -1;
        }
        if ($cmp & 2) { // DOCUMENT_POSITION_PRECEDING
            return 1;
        }
        return 0;
    }

    /**
     * Get the XML text from cursor to end of current element.
     *
     * @return string
     */
    public function xmlText()
    {
        if ($this->currentNode instanceof DOMDocument) {
            return $this->document->saveXML();
        }
        return $this->document->saveXML($this->currentNode);
    }

    /**
     * @param DOMNode $node
     * @param string  $localName
     * @param string  $ns
     * @return bool
     */
    private function matchesName(DOMNode $node, $localName, $ns)
    {
        $nameMatch = ($node->localName === $localName || $node->nodeName === $localName);
        if ($ns !== '') {
            return $nameMatch && $node->namespaceURI === $ns;
        }
        return $nameMatch;
    }

    /**
     * Execute an XPath expression from the current cursor position.
     * Returns an array of XmlCursor instances positioned at each result node.
     *
     * @param string $xpath XPath expression
     * @param array<string, string> $namespaces Optional prefix => URI mappings
     * @return XmlCursor[]
     */
    public function selectPath($xpath, array $namespaces = array())
    {
        $doc = ($this->currentNode instanceof DOMDocument)
            ? $this->currentNode
            : $this->currentNode->ownerDocument;

        $domXPath = new \DOMXPath($doc);

        // Register namespaces
        foreach ($namespaces as $prefix => $uri) {
            $domXPath->registerNamespace($prefix, $uri);
        }

        // Auto-register document namespaces
        if ($doc->documentElement) {
            $attrs = $doc->documentElement->attributes;
            if ($attrs !== null) {
                for ($i = 0; $i < $attrs->length; $i++) {
                    $attr = $attrs->item($i);
                    if (strpos($attr->nodeName, 'xmlns:') === 0) {
                        $prefix = substr($attr->nodeName, 6);
                        if (!isset($namespaces[$prefix])) {
                            $domXPath->registerNamespace($prefix, $attr->nodeValue);
                        }
                    }
                }
            }
        }

        $result = $domXPath->query($xpath, $this->currentNode);
        $cursors = array();

        if ($result !== false) {
            foreach ($result as $node) {
                $cursor = new self($doc);
                $cursor->currentNode = $node;
                $cursors[] = $cursor;
            }
        }

        return $cursors;
    }

    /**
     * Select child elements by local name from the current cursor position.
     *
     * @param string $localName
     * @param string|null $namespaceUri
     * @return XmlCursor[]
     */
    public function selectChildren($localName, $namespaceUri = null)
    {
        $cursors = array();
        $doc = ($this->currentNode instanceof DOMDocument)
            ? $this->currentNode
            : $this->currentNode->ownerDocument;

        if (!$this->currentNode->hasChildNodes()) {
            return $cursors;
        }

        foreach ($this->currentNode->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            if ($localName === '*' || $child->localName === $localName) {
                if ($namespaceUri === null || $child->namespaceURI === $namespaceUri) {
                    $cursor = new self($doc);
                    $cursor->currentNode = $child;
                    $cursors[] = $cursor;
                }
            }
        }

        return $cursors;
    }

    /**
     * Select elements by local name in the subtree from the current cursor position.
     *
     * @param string $localName
     * @param string|null $namespaceUri
     * @return XmlCursor[]
     */
    public function selectElements($localName, $namespaceUri = null)
    {
        $expr = './/' . $localName;
        $namespaces = array();

        if ($namespaceUri !== null) {
            $prefix = '_ce' . substr(md5($namespaceUri), 0, 6);
            $namespaces[$prefix] = $namespaceUri;
            $expr = './/' . $prefix . ':' . $localName;
        }

        return $this->selectPath($expr, $namespaces);
    }
}
