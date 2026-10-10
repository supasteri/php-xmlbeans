<?php

namespace XmlBeans\Validation;

use DOMDocument;
use DOMElement;
use DOMNode;
use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Common\QName;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaParticle;
use XmlBeans\SchemaProperty;
use XmlBeans\SchemaType;
use XmlBeans\XmlError;
use XmlBeans\XmlObject;
use XmlBeans\XmlOptions;

/**
 * Validates an XmlObject against its SchemaType.
 * Supports facet validation, occurrence constraints, content model checking.
 */
class Validator
{
    /** @var XmlError[] */
    private $errors = [];

    /**
     * Validate an XmlObject against its schema type.
     *
     * @param XmlObject    $obj
     * @param XmlOptions|null $options
     * @return bool
     */
    public function validate(XmlObject $obj, $options = null)
    {
        $this->errors = [];
        $type = $obj->schemaType();

        if ($type === null) {
            // No schema type - try XSD validation via libxml
            return $this->validateWithXsd($obj);
        }

        $node = $obj->getDomNode();
        if ($node instanceof DOMDocument) {
            $node = $node->documentElement;
        }

        if ($node instanceof DOMElement) {
            $this->validateElement($node, $type);
        }

        // Report errors
        if ($options instanceof XmlOptions) {
            $errRef = &$options->getValidationErrors();
            foreach ($this->errors as $e) {
                $errRef[] = $e;
            }
            foreach ($options->getErrorListeners() as $listener) {
                foreach ($this->errors as $e) {
                    call_user_func($listener, $e);
                }
            }
        }

        return count($this->errors) === 0;
    }

    /**
     * Get all validation errors from the last run.
     *
     * @return XmlError[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Validate an element against a schema type.
     *
     * @param DOMElement $element
     * @param SchemaType $type
     */
    private function validateElement(DOMElement $element, SchemaType $type)
    {
        // Validate attributes
        foreach ($type->getAttributeProperties() as $attrProp) {
            $this->validateAttribute($element, $attrProp);
        }

        // Validate content
        if ($type->isSimpleType() || $type->getContentType() === SchemaType::SIMPLE_CONTENT) {
            $this->validateSimpleContent($element->textContent, $type, $element);
        } elseif ($type->getContentType() === SchemaType::ELEMENT_CONTENT
            || $type->getContentType() === SchemaType::MIXED_CONTENT) {
            $this->validateChildElements($element, $type);
        } elseif ($type->getContentType() === SchemaType::EMPTY_CONTENT) {
            if (trim($element->textContent) !== '') {
                $this->addError("Element '{$element->localName}' must be empty", $element);
            }
        }
    }

    /**
     * Validate an attribute against its SchemaProperty.
     *
     * @param DOMElement     $element
     * @param SchemaProperty $prop
     */
    private function validateAttribute(DOMElement $element, SchemaProperty $prop)
    {
        $name = $prop->getName();
        $ns = $name->getNamespaceURI();
        $local = $name->getLocalPart();

        $value = null;
        if ($ns !== '') {
            $val = $element->getAttributeNS($ns, $local);
            $value = ($val !== '') ? $val : null;
        } else {
            if ($element->hasAttribute($local)) {
                $value = $element->getAttribute($local);
            }
        }

        // Check required
        if ($prop->isRequired() && $value === null) {
            $this->addError(
                "Required attribute '{$local}' missing on element '{$element->localName}'",
                $element
            );
            return;
        }

        // Check fixed value
        if ($value !== null && $prop->getFixedValue() !== null) {
            if ($value !== $prop->getFixedValue()) {
                $this->addError(
                    "Attribute '{$local}' must have fixed value '{$prop->getFixedValue()}'",
                    $element
                );
            }
        }

        // Validate type facets
        if ($value !== null && $prop->getType() !== null) {
            $this->validateSimpleContent($value, $prop->getType(), $element);
        }
    }

    /**
     * Validate child elements against type's element properties.
     *
     * @param DOMElement $element
     * @param SchemaType $type
     */
    private function validateChildElements(DOMElement $element, SchemaType $type)
    {
        $childCounts = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $key = ($child->namespaceURI ?: '') . '|' . $child->localName;
                if (!isset($childCounts[$key])) {
                    $childCounts[$key] = 0;
                }
                $childCounts[$key]++;

                // Find matching property and validate recursively
                $qname = new QName($child->namespaceURI ?: '', $child->localName);
                $childProp = $type->getElementProperty($qname);
                if ($childProp !== null && $childProp->getType() !== null) {
                    $this->validateElement($child, $childProp->getType());
                }
            }
        }

        // Check occurrence constraints
        foreach ($type->getElementProperties() as $prop) {
            $key = $prop->getName()->hashKey();
            $count = isset($childCounts[$key]) ? $childCounts[$key] : 0;

            if ($count < $prop->getMinOccurs()) {
                $this->addError(
                    "Element '{$prop->getName()->getLocalPart()}' must appear at least " .
                    "{$prop->getMinOccurs()} time(s) in '{$element->localName}', found {$count}",
                    $element
                );
            }

            $max = $prop->getMaxOccurs();
            if ($max !== -1 && $count > $max) {
                $this->addError(
                    "Element '{$prop->getName()->getLocalPart()}' must appear at most " .
                    "{$max} time(s) in '{$element->localName}', found {$count}",
                    $element
                );
            }
        }
    }

    /**
     * Validate a simple type value against facets.
     *
     * @param string     $value
     * @param SchemaType $type
     * @param DOMElement $context
     */
    private function validateSimpleContent($value, SchemaType $type, DOMElement $context)
    {
        $facets = $type->getFacets();
        $value = trim($value);

        // Pattern
        $pattern = $type->getPatternFacet();
        if ($pattern !== null) {
            if (!preg_match('/^' . $pattern . '$/', $value)) {
                $this->addError(
                    "Value '{$value}' does not match pattern '{$pattern}'",
                    $context
                );
            }
        }

        // Enumeration
        $enums = $type->getEnumerationValues();
        if (!empty($enums) && !in_array($value, $enums, true)) {
            $this->addError(
                "Value '{$value}' is not in enumeration [" . implode(', ', $enums) . "]",
                $context
            );
        }

        // Length facets
        if (isset($facets['minLength']) && mb_strlen($value) < (int) $facets['minLength']) {
            $this->addError(
                "Value length " . mb_strlen($value) . " is less than minLength {$facets['minLength']}",
                $context
            );
        }
        if (isset($facets['maxLength']) && mb_strlen($value) > (int) $facets['maxLength']) {
            $this->addError(
                "Value length " . mb_strlen($value) . " exceeds maxLength {$facets['maxLength']}",
                $context
            );
        }
        if (isset($facets['length']) && mb_strlen($value) !== (int) $facets['length']) {
            $this->addError(
                "Value length " . mb_strlen($value) . " must be exactly {$facets['length']}",
                $context
            );
        }

        // Numeric range facets (for numeric types)
        if (isset($facets['minInclusive']) && is_numeric($value)) {
            if (self::numericCompare($value, $facets['minInclusive']) < 0) {
                $this->addError(
                    "Value '{$value}' is less than minInclusive '{$facets['minInclusive']}'",
                    $context
                );
            }
        }
        if (isset($facets['maxInclusive']) && is_numeric($value)) {
            if (self::numericCompare($value, $facets['maxInclusive']) > 0) {
                $this->addError(
                    "Value '{$value}' exceeds maxInclusive '{$facets['maxInclusive']}'",
                    $context
                );
            }
        }
        if (isset($facets['minExclusive']) && is_numeric($value)) {
            if (self::numericCompare($value, $facets['minExclusive']) <= 0) {
                $this->addError(
                    "Value '{$value}' must be greater than minExclusive '{$facets['minExclusive']}'",
                    $context
                );
            }
        }
        if (isset($facets['maxExclusive']) && is_numeric($value)) {
            if (self::numericCompare($value, $facets['maxExclusive']) >= 0) {
                $this->addError(
                    "Value '{$value}' must be less than maxExclusive '{$facets['maxExclusive']}'",
                    $context
                );
            }
        }

        // totalDigits / fractionDigits
        if (isset($facets['totalDigits']) && is_numeric($value)) {
            $digits = strlen(str_replace(['.', '-', '+'], '', $value));
            if ($digits > (int) $facets['totalDigits']) {
                $this->addError(
                    "Value '{$value}' has {$digits} digits, exceeds totalDigits {$facets['totalDigits']}",
                    $context
                );
            }
        }
        if (isset($facets['fractionDigits']) && strpos($value, '.') !== false) {
            $parts = explode('.', $value);
            $fracLen = strlen($parts[1]);
            if ($fracLen > (int) $facets['fractionDigits']) {
                $this->addError(
                    "Value '{$value}' has {$fracLen} fraction digits, exceeds fractionDigits {$facets['fractionDigits']}",
                    $context
                );
            }
        }

        // Validate against base type chain
        $base = $type->getBaseType();
        if ($base !== null && $base->isSimpleType()) {
            $this->validateSimpleContent($value, $base, $context);
        }
    }

    /**
     * Fall back to XSD validation via libxml.
     *
     * @param XmlObject $obj
     * @return bool
     */
    private function validateWithXsd(XmlObject $obj)
    {
        $node = $obj->getDomNode();
        $doc = ($node instanceof DOMDocument) ? $node : $node->ownerDocument;

        // Check for xsi:schemaLocation or xsi:noNamespaceSchemaLocation
        if ($doc->documentElement) {
            $xsiNs = 'http://www.w3.org/2001/XMLSchema-instance';
            $schemaLoc = $doc->documentElement->getAttributeNS($xsiNs, 'noNamespaceSchemaLocation');
            if ($schemaLoc) {
                libxml_use_internal_errors(true);
                $valid = $doc->schemaValidate($schemaLoc);
                $libErrors = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors(false);

                foreach ($libErrors as $err) {
                    $this->errors[] = new XmlError(
                        $err->level === LIBXML_ERR_WARNING ? XmlError::SEVERITY_WARNING : XmlError::SEVERITY_ERROR,
                        trim($err->message),
                        $err->file ?: null,
                        $err->line,
                        $err->column
                    );
                }
                return $valid;
            }
        }

        // No schema available, assume valid
        return true;
    }

    /**
     * @param string   $message
     * @param DOMElement|null $context
     */
    private function addError($message, DOMElement $context = null)
    {
        $line = null;
        $source = null;
        if ($context) {
            $source = $context->localName;
            $line = $context->getLineNo();
        }
        $this->errors[] = new XmlError(XmlError::SEVERITY_ERROR, $message, $source, $line);
    }

    /**
     * Validate XML string against an XSD schema string.
     *
     * @param string $xml
     * @param string $xsd
     * @return bool
     */
    public static function validateXmlAgainstXsd($xml, $xsd)
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadXML($xml);
        $result = $doc->schemaValidateSource($xsd);
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        return $result;
    }

    /**
     * Compare two numeric values as strings (bccomp fallback).
     *
     * @param string $a
     * @param string $b
     * @return int -1, 0, or 1
     */
    private static function numericCompare($a, $b)
    {
        if (function_exists('bccomp')) {
            return bccomp((string) $a, (string) $b, 20);
        }
        $fa = (float) $a;
        $fb = (float) $b;
        if ($fa < $fb) return -1;
        if ($fa > $fb) return 1;
        return 0;
    }
}
