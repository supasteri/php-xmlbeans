<?php

namespace XmlBeans;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Compiler\SchemaCompiler;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\Common\QName;

/**
 * Main factory class for PHP XMLBeans.
 * Provides static methods for parsing XML, loading schemas, and creating typed objects.
 *
 * Mirrors org.apache.xmlbeans.XmlBeans.
 */
class XmlBeans
{
    /** @var SchemaTypeSystem[] Loaded type systems */
    private static $typeSystems = [];

    /**
     * Parse an XML string into an XmlObject.
     *
     * @param string          $xml
     * @param SchemaType|null $type Expected type
     * @param XmlOptions|null $options
     * @return XmlObject
     */
    public static function parse($xml, $type = null, $options = null)
    {
        return XmlObjectBase::parse($xml, $type, $options);
    }

    /**
     * Parse an XML file into an XmlObject.
     *
     * @param string          $filename
     * @param SchemaType|null $type
     * @param XmlOptions|null $options
     * @return XmlObject
     */
    public static function parseFile($filename, $type = null, $options = null)
    {
        return XmlObjectBase::parseFile($filename, $type, $options);
    }

    /**
     * Compile an XSD schema file into a SchemaTypeSystem.
     *
     * @param string $xsdFilename
     * @param string $name
     * @return SchemaTypeSystem
     */
    public static function compileXsd($xsdFilename, $name = 'compiled')
    {
        $compiler = new SchemaCompiler();
        $ts = $compiler->compile($xsdFilename, $name);
        self::$typeSystems[$name] = $ts;
        return $ts;
    }

    /**
     * Compile an XSD string into a SchemaTypeSystem.
     *
     * @param string $xsdContent
     * @param string $name
     * @return SchemaTypeSystem
     */
    public static function compileXsdString($xsdContent, $name = 'compiled')
    {
        $compiler = new SchemaCompiler();
        $ts = $compiler->compileXsd($xsdContent, $name);
        self::$typeSystems[$name] = $ts;
        return $ts;
    }

    /**
     * Compile multiple XSD files.
     *
     * @param string[] $xsdFilenames
     * @param string   $name
     * @return SchemaTypeSystem
     */
    public static function compileXsdFiles(array $xsdFilenames, $name = 'compiled')
    {
        $compiler = new SchemaCompiler();
        $ts = $compiler->compileMultiple($xsdFilenames, $name);
        self::$typeSystems[$name] = $ts;
        return $ts;
    }

    /**
     * Get the built-in type system.
     *
     * @return SchemaTypeSystem
     */
    public static function getBuiltinTypeSystem()
    {
        return BuiltinTypeSystem::get();
    }

    /**
     * Find a built-in type by local name.
     *
     * @param string $localName e.g., "string", "int", "boolean"
     * @return SchemaType|null
     */
    public static function getBuiltinType($localName)
    {
        return BuiltinTypeSystem::findByLocalName($localName);
    }

    /**
     * Get a previously compiled type system by name.
     *
     * @param string $name
     * @return SchemaTypeSystem|null
     */
    public static function getTypeSystem($name)
    {
        return isset(self::$typeSystems[$name]) ? self::$typeSystems[$name] : null;
    }

    /**
     * Register a type system.
     *
     * @param string           $name
     * @param SchemaTypeSystem $ts
     */
    public static function registerTypeSystem($name, SchemaTypeSystem $ts)
    {
        self::$typeSystems[$name] = $ts;
    }

    /**
     * Resolve a type across all loaded type systems.
     *
     * @param QName $name
     * @return SchemaType|null
     */
    public static function resolveType(QName $name)
    {
        // Check built-ins first
        if ($name->getNamespaceURI() === BuiltinTypeSystem::XSD_NS) {
            $bt = BuiltinTypeSystem::findByLocalName($name->getLocalPart());
            if ($bt) {
                return $bt;
            }
        }

        // Check loaded type systems
        foreach (self::$typeSystems as $ts) {
            $found = $ts->findType($name);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Create a new empty XML document.
     *
     * @param string      $rootElement
     * @param string|null $namespace
     * @return XmlObject
     */
    public static function newDocument($rootElement, $namespace = null)
    {
        if ($namespace) {
            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<{$rootElement} xmlns=\"{$namespace}\"/>";
        } else {
            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<{$rootElement}/>";
        }
        return self::parse($xml);
    }

    /**
     * Validate an XML string against an XSD schema string.
     *
     * @param string $xml
     * @param string $xsd
     * @return bool
     */
    public static function validate($xml, $xsd)
    {
        return Validation\Validator::validateXmlAgainstXsd($xml, $xsd);
    }

    /**
     * Create an XmlOptions builder.
     *
     * @return XmlOptions
     */
    public static function newOptions()
    {
        return new XmlOptions();
    }

    /**
     * Get the library version.
     *
     * @return string
     */
    public static function getVersion()
    {
        return '1.0.0';
    }
}
