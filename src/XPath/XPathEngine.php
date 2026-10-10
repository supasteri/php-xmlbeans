<?php

namespace XmlBeans\XPath;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Binding\TypeMapper;
use XmlBeans\SchemaType;

/**
 * Full-featured XPath engine with function registration, variables,
 * typed result binding, and cursor integration.
 *
 * Compatible with PHP 7.2+.
 */
class XPathEngine
{
    /** @var DOMXPath|null */
    private $domXPath;

    /** @var DOMDocument */
    private $document;

    /** @var XPathNamespaceContext */
    private $namespaceContext;

    /** @var array<string, callable> Custom PHP functions */
    private $customFunctions = array();

    /** @var string Namespace URI for custom PHP functions */
    private $phpFunctionNamespace = 'http://xmlbeans.php/xpath/functions';

    /** @var string Prefix for custom PHP functions */
    private $phpFunctionPrefix = 'php';

    /** @var bool Whether PHP functions have been registered with DOMXPath */
    private $phpFunctionsRegistered = false;

    /** @var SchemaType|null Schema type for result binding */
    private $resultSchemaType = null;

    /**
     * @param DOMDocument $document
     * @param XPathNamespaceContext|null $namespaceContext
     */
    public function __construct(DOMDocument $document, $namespaceContext = null)
    {
        $this->document = $document;
        $this->namespaceContext = $namespaceContext ?: new XPathNamespaceContext();
        $this->domXPath = null;
    }

    /**
     * Create an engine from an XmlObjectBase.
     *
     * @param XmlObjectBase $xmlObject
     * @return XPathEngine
     */
    public static function fromXmlObject(XmlObjectBase $xmlObject)
    {
        $dom = $xmlObject->getDomNode();
        $doc = ($dom instanceof DOMDocument) ? $dom : $dom->ownerDocument;

        $context = new XPathNamespaceContext();
        $context->loadFromDocument($doc);

        return new self($doc, $context);
    }

    /**
     * Create an engine from an XML string.
     *
     * @param string $xml
     * @return XPathEngine
     */
    public static function fromXml($xml)
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $context = new XPathNamespaceContext();
        $context->loadFromDocument($doc);

        return new self($doc, $context);
    }

    /**
     * Execute an XPath query and return raw DOMNodeList.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return DOMNodeList|false
     */
    public function query($expression, $contextNode = null)
    {
        $xpath = $this->getDomXPath();
        $expr = $this->resolveExpression($expression);

        if ($contextNode === null) {
            return $xpath->query($expr);
        }
        return $xpath->query($expr, $contextNode);
    }

    /**
     * Evaluate an XPath expression (can return string, float, bool, or DOMNodeList).
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return mixed
     */
    public function evaluate($expression, $contextNode = null)
    {
        $xpath = $this->getDomXPath();
        $expr = $this->resolveExpression($expression);

        if ($contextNode === null) {
            return $xpath->evaluate($expr);
        }
        return $xpath->evaluate($expr, $contextNode);
    }

    /**
     * Execute XPath and return results as typed XmlObject array.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return XmlObjectBase[]
     */
    public function selectTyped($expression, $contextNode = null)
    {
        $result = $this->query($expression, $contextNode);
        $objects = array();

        if ($result === false) {
            return $objects;
        }

        foreach ($result as $node) {
            $obj = $this->wrapNode($node);
            if ($obj !== null) {
                $objects[] = $obj;
            }
        }

        return $objects;
    }

    /**
     * Execute XPath and return the first result as a typed XmlObject.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return XmlObjectBase|null
     */
    public function selectFirst($expression, $contextNode = null)
    {
        $results = $this->selectTyped($expression, $contextNode);
        return count($results) > 0 ? $results[0] : null;
    }

    /**
     * Execute XPath and return scalar string value.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return string
     */
    public function selectString($expression, $contextNode = null)
    {
        $result = $this->evaluate($expression, $contextNode);
        if ($result instanceof DOMNodeList && $result->length > 0) {
            return $result->item(0)->nodeValue;
        }
        return (string) $result;
    }

    /**
     * Execute XPath and return numeric value.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return float
     */
    public function selectNumber($expression, $contextNode = null)
    {
        $result = $this->evaluate($expression, $contextNode);
        if ($result instanceof DOMNodeList && $result->length > 0) {
            return (float) $result->item(0)->nodeValue;
        }
        return (float) $result;
    }

    /**
     * Execute XPath and return boolean value.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return bool
     */
    public function selectBoolean($expression, $contextNode = null)
    {
        $result = $this->evaluate($expression, $contextNode);
        if ($result instanceof DOMNodeList) {
            return $result->length > 0;
        }
        return (bool) $result;
    }

    /**
     * Execute XPath and return string values of all matching nodes.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return string[]
     */
    public function selectStrings($expression, $contextNode = null)
    {
        $result = $this->query($expression, $contextNode);
        $strings = array();

        if ($result !== false) {
            foreach ($result as $node) {
                $strings[] = $node->nodeValue;
            }
        }

        return $strings;
    }

    /**
     * Count matching nodes.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return int
     */
    public function count($expression, $contextNode = null)
    {
        $result = $this->query($expression, $contextNode);
        if ($result === false) {
            return 0;
        }
        return $result->length;
    }

    /**
     * Check if any nodes match the expression.
     *
     * @param string|XPathExpression $expression
     * @param DOMNode|null $contextNode
     * @return bool
     */
    public function exists($expression, $contextNode = null)
    {
        return $this->count($expression, $contextNode) > 0;
    }

    /**
     * Select child elements matching a local name.
     *
     * @param DOMNode $contextNode
     * @param string $localName
     * @param string|null $namespaceUri
     * @return XmlObjectBase[]
     */
    public function selectChildren($contextNode, $localName, $namespaceUri = null)
    {
        if ($namespaceUri !== null) {
            $prefix = $this->namespaceContext->getPrefix($namespaceUri);
            if ($prefix === null) {
                // Register a temporary prefix
                $prefix = '_sc' . substr(md5($namespaceUri), 0, 6);
                $this->namespaceContext->registerNamespace($prefix, $namespaceUri);
                $this->resetDomXPath();
            }
            $expr = $prefix . ':' . $localName;
        } else {
            $expr = $localName;
        }

        return $this->selectTyped($expr, $contextNode);
    }

    /**
     * Select child elements matching any name (wildcard).
     *
     * @param DOMNode $contextNode
     * @return XmlObjectBase[]
     */
    public function selectAllChildren($contextNode)
    {
        return $this->selectTyped('*', $contextNode);
    }

    /**
     * Select elements by local name anywhere in the subtree.
     *
     * @param DOMNode $contextNode
     * @param string $localName
     * @param string|null $namespaceUri
     * @return XmlObjectBase[]
     */
    public function selectElements($contextNode, $localName, $namespaceUri = null)
    {
        if ($namespaceUri !== null) {
            $prefix = $this->namespaceContext->getPrefix($namespaceUri);
            if ($prefix === null) {
                $prefix = '_se' . substr(md5($namespaceUri), 0, 6);
                $this->namespaceContext->registerNamespace($prefix, $namespaceUri);
                $this->resetDomXPath();
            }
            $expr = './/' . $prefix . ':' . $localName;
        } else {
            $expr = './/' . $localName;
        }

        return $this->selectTyped($expr, $contextNode);
    }

    /**
     * Register a custom PHP function for use in XPath expressions.
     *
     * The function will be callable as php:functionName() in XPath.
     *
     * @param string $name Function name (without prefix)
     * @param callable $callback PHP callable
     * @return $this
     */
    public function registerFunction($name, $callback)
    {
        $this->customFunctions[$name] = $callback;
        $this->phpFunctionsRegistered = false;
        $this->resetDomXPath();
        return $this;
    }

    /**
     * Register multiple custom PHP functions.
     *
     * @param array<string, callable> $functions name => callable
     * @return $this
     */
    public function registerFunctions(array $functions)
    {
        foreach ($functions as $name => $callback) {
            $this->customFunctions[$name] = $callback;
        }
        $this->phpFunctionsRegistered = false;
        $this->resetDomXPath();
        return $this;
    }

    /**
     * Unregister a custom PHP function.
     *
     * @param string $name
     * @return $this
     */
    public function unregisterFunction($name)
    {
        unset($this->customFunctions[$name]);
        $this->phpFunctionsRegistered = false;
        $this->resetDomXPath();
        return $this;
    }

    /**
     * Get all registered custom function names.
     *
     * @return string[]
     */
    public function getRegisteredFunctions()
    {
        return array_keys($this->customFunctions);
    }

    /**
     * Set the custom function namespace prefix and URI.
     *
     * @param string $prefix
     * @param string $namespaceUri
     * @return $this
     */
    public function setFunctionNamespace($prefix, $namespaceUri)
    {
        $this->phpFunctionPrefix = $prefix;
        $this->phpFunctionNamespace = $namespaceUri;
        $this->phpFunctionsRegistered = false;
        $this->resetDomXPath();
        return $this;
    }

    /**
     * Get the namespace context.
     *
     * @return XPathNamespaceContext
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
        $this->resetDomXPath();
        return $this;
    }

    /**
     * Set the schema type used for result binding.
     *
     * @param SchemaType|null $type
     * @return $this
     */
    public function setResultSchemaType($type)
    {
        $this->resultSchemaType = $type;
        return $this;
    }

    /**
     * Get the underlying DOMXPath instance.
     *
     * @return DOMXPath
     */
    public function getDomXPath()
    {
        if ($this->domXPath === null) {
            $this->domXPath = new DOMXPath($this->document);
            $this->namespaceContext->applyTo($this->domXPath);
            $this->registerPhpFunctions();
        }
        return $this->domXPath;
    }

    /**
     * Get the underlying DOMDocument.
     *
     * @return DOMDocument
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * Callback dispatcher for custom PHP functions in XPath.
     * This is the single entry point registered with DOMXPath::registerPhpFunctions().
     *
     * @param string $name Function name
     * @return mixed
     */
    public function callFunction($name)
    {
        $args = func_get_args();
        array_shift($args); // remove $name

        if (!isset($this->customFunctions[$name])) {
            return '';
        }

        // Convert DOMNodeList args to string values
        $convertedArgs = array();
        foreach ($args as $arg) {
            if ($arg instanceof DOMNodeList) {
                $values = array();
                foreach ($arg as $node) {
                    $values[] = $node->nodeValue;
                }
                $convertedArgs[] = count($values) === 1 ? $values[0] : $values;
            } else {
                $convertedArgs[] = $arg;
            }
        }

        return call_user_func_array($this->customFunctions[$name], $convertedArgs);
    }

    /**
     * Resolve an expression to its string form.
     *
     * @param string|XPathExpression $expression
     * @return string
     */
    private function resolveExpression($expression)
    {
        if ($expression instanceof XPathExpression) {
            // Apply expression's namespace context
            $exprContext = $expression->getNamespaceContext();
            if ($exprContext !== null) {
                $this->namespaceContext->merge($exprContext);
                $this->resetDomXPath();
            }
            return $expression->getNormalizedExpression();
        }
        return (string) $expression;
    }

    /**
     * Wrap a DOMNode in a typed XmlObjectBase.
     *
     * @param DOMNode $node
     * @return XmlObjectBase|null
     */
    private function wrapNode(DOMNode $node)
    {
        if ($this->resultSchemaType !== null) {
            $class = TypeMapper::getClassForType($this->resultSchemaType);
            if ($class !== null) {
                return new $class($node, $this->resultSchemaType);
            }
        }

        // Try to determine type from xsi:type attribute if present
        if ($node instanceof DOMElement) {
            $xsiType = $node->getAttributeNS(
                'http://www.w3.org/2001/XMLSchema-instance',
                'type'
            );
            if ($xsiType !== '') {
                // Try to resolve via BuiltinTypeSystem
                $builtinSystem = \XmlBeans\Schema\BuiltinTypeSystem::getInstance();
                $type = $builtinSystem->findTypeByName($xsiType);
                if ($type !== null) {
                    $class = TypeMapper::getClassForType($type);
                    if ($class !== null) {
                        return new $class($node, $type);
                    }
                }
            }
        }

        // Default: wrap in generic XmlObjectBase
        return new XmlObjectBase($node);
    }

    /**
     * Register PHP functions with DOMXPath if needed.
     *
     * @return void
     */
    private function registerPhpFunctions()
    {
        if ($this->phpFunctionsRegistered || empty($this->customFunctions)) {
            return;
        }

        // Register the function namespace
        $this->domXPath->registerNamespace(
            $this->phpFunctionPrefix,
            $this->phpFunctionNamespace
        );

        // Register the callback dispatcher
        $this->domXPath->registerPhpFunctions(array(
            get_class($this) . '::staticCallFunction'
        ));

        // Store instance reference for static callback
        self::$currentInstance = $this;

        $this->phpFunctionsRegistered = true;
    }

    /** @var XPathEngine|null Current instance for static callback */
    private static $currentInstance = null;

    /**
     * Static callback for DOMXPath PHP function registration.
     *
     * @return mixed
     */
    public static function staticCallFunction()
    {
        $args = func_get_args();
        if (self::$currentInstance === null) {
            return '';
        }
        return call_user_func_array(
            array(self::$currentInstance, 'callFunction'),
            $args
        );
    }

    /**
     * Reset the DOMXPath instance (needed when namespace context changes).
     *
     * @return void
     */
    private function resetDomXPath()
    {
        $this->domXPath = null;
        $this->phpFunctionsRegistered = false;
    }
}
