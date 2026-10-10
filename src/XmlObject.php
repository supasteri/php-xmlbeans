<?php
/**
 * PHP port of Apache XMLBeans
 *
 * @license Apache-2.0
 */

namespace XmlBeans;

use DOMNode;
use DOMDocument;

/**
 * XmlObject is the base interface for all XML Bean types.
 * Corresponds to xs:anyType in XML Schema.
 *
 * Every XML Bean implements this interface, providing common operations
 * for XML manipulation, validation, serialization, and cursor navigation.
 */
interface XmlObject
{
    /**
     * The constant {@link SchemaType} object representing this type.
     *
     * @return SchemaType
     */
    public function schemaType();

    /**
     * Validates the XML content against its schema.
     *
     * @param XmlOptions|null $options Optional validation options
     * @return bool True if valid
     */
    public function validate($options = null);

    /**
     * Returns a new cursor positioned at the start of this XML.
     *
     * @return Cursor\XmlCursor
     */
    public function newCursor();

    /**
     * Returns the DOM node underlying this XmlObject.
     *
     * @return DOMNode
     */
    public function getDomNode();

    /**
     * Sets the value from a string.
     *
     * @param string $value
     * @return void
     */
    public function setStringValue($value);

    /**
     * Returns the string value.
     *
     * @return string
     */
    public function getStringValue();

    /**
     * Serializes the XML to a string.
     *
     * @param XmlOptions|null $options Optional serialization options
     * @return string
     */
    public function xmlText($options = null);

    /**
     * Saves the XML to a file.
     *
     * @param string $filename
     * @param XmlOptions|null $options
     * @return void
     */
    public function save($filename, $options = null);

    /**
     * Creates a deep copy of this XmlObject.
     *
     * @return XmlObject
     */
    public function copy();

    /**
     * Returns true if the value is nil (xsi:nil="true").
     *
     * @return bool
     */
    public function isNil();

    /**
     * Sets this element to nil.
     *
     * @param bool $nil
     * @return void
     */
    public function setNil($nil = true);

    /**
     * Tests whether this XmlObject is an instance of the given SchemaType.
     *
     * @param SchemaType $type
     * @return bool
     */
    public function instanceType(SchemaType $type);

    /**
     * Selects elements/attributes matching an XPath expression.
     *
     * @param string $path XPath expression
     * @param XmlOptions|null $options
     * @return XmlObject[]
     */
    public function selectPath($path, $options = null);

    /**
     * Executes an XPath and returns the first match.
     *
     * @param string $path
     * @param XmlOptions|null $options
     * @return XmlObject|null
     */
    public function selectAttribute($path, $options = null);

    /**
     * Parse a string into this type of XmlObject.
     *
     * @param string $xml
     * @param SchemaType|null $type
     * @param XmlOptions|null $options
     * @return XmlObject
     */
    public static function parse($xml, $type = null, $options = null);

    /**
     * Parse from a file.
     *
     * @param string $filename
     * @param SchemaType|null $type
     * @param XmlOptions|null $options
     * @return XmlObject
     */
    public static function parseFile($filename, $type = null, $options = null);

    /**
     * Creates a new empty instance.
     *
     * @return XmlObject
     */
    public static function newInstance();
}
