<?php

namespace XmlBeans\Compiler;

use DOMDocument;
use DOMElement;
use DOMXPath;
use XmlBeans\Common\QName;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaParticle;
use XmlBeans\SchemaProperty;
use XmlBeans\SchemaType;
use XmlBeans\SchemaTypeSystem;
use XmlBeans\XmlError;

/**
 * Compiles XSD schema(s) into a SchemaTypeSystem.
 * Equivalent to the scomp tool in Apache XMLBeans.
 *
 * Usage:
 *   $compiler = new SchemaCompiler();
 *   $typeSystem = $compiler->compile('path/to/schema.xsd');
 *   // or
 *   $typeSystem = $compiler->compileXsd($xsdString);
 *
 * To generate PHP classes:
 *   $generator = new PhpCodeGenerator($typeSystem);
 *   $generator->generate('/output/dir', 'MyNamespace');
 */
class SchemaCompiler
{
    const XSD_NS = 'http://www.w3.org/2001/XMLSchema';

    /** @var XmlError[] */
    private $errors = [];

    /** @var SchemaTypeSystem */
    private $typeSystem;

    /** @var DOMXPath */
    private $xpath;

    /** @var string Target namespace of current schema */
    private $targetNamespace = '';

    /** @var array Pending type references to resolve */
    private $pendingTypeRefs = [];

    /** @var array Already-loaded schema URIs (for import/include) */
    private $loadedSchemas = [];

    /**
     * Compile an XSD file into a SchemaTypeSystem.
     *
     * @param string $filename
     * @param string $systemName
     * @return SchemaTypeSystem
     */
    public function compile($filename, $systemName = 'compiled')
    {
        $xsd = file_get_contents($filename);
        if ($xsd === false) {
            throw new \RuntimeException("Cannot read schema file: $filename");
        }
        $this->loadedSchemas[realpath($filename)] = true;
        return $this->compileXsd($xsd, $systemName, dirname(realpath($filename)));
    }

    /**
     * Compile an XSD string into a SchemaTypeSystem.
     *
     * @param string $xsd
     * @param string $systemName
     * @param string $baseDir Base directory for resolving imports
     * @return SchemaTypeSystem
     */
    public function compileXsd($xsd, $systemName = 'compiled', $baseDir = '')
    {
        $this->errors = [];
        $this->typeSystem = new SchemaTypeSystem($systemName);
        $this->typeSystem->setParentTypeSystem(BuiltinTypeSystem::get());

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$doc->loadXML($xsd)) {
            $this->collectLibxmlErrors();
            throw new \RuntimeException('Failed to parse XSD: ' . ($this->errors ? (string) $this->errors[0] : 'unknown'));
        }
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $this->processSchemaDocument($doc, $baseDir);
        $this->resolveTypeReferences();

        return $this->typeSystem;
    }

    /**
     * Compile multiple XSD files.
     *
     * @param string[] $filenames
     * @param string   $systemName
     * @return SchemaTypeSystem
     */
    public function compileMultiple(array $filenames, $systemName = 'compiled')
    {
        $this->errors = [];
        $this->typeSystem = new SchemaTypeSystem($systemName);
        $this->typeSystem->setParentTypeSystem(BuiltinTypeSystem::get());

        foreach ($filenames as $filename) {
            $xsd = file_get_contents($filename);
            if ($xsd === false) {
                $this->errors[] = new XmlError(XmlError::SEVERITY_ERROR, "Cannot read: $filename");
                continue;
            }
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            if ($doc->loadXML($xsd)) {
                $this->loadedSchemas[realpath($filename)] = true;
                $this->processSchemaDocument($doc, dirname(realpath($filename)));
            }
            libxml_clear_errors();
            libxml_use_internal_errors(false);
        }

        $this->resolveTypeReferences();
        return $this->typeSystem;
    }

    /**
     * @return XmlError[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Process a single schema document.
     *
     * @param DOMDocument $doc
     * @param string      $baseDir
     */
    private function processSchemaDocument(DOMDocument $doc, $baseDir)
    {
        $this->xpath = new DOMXPath($doc);
        $this->xpath->registerNamespace('xs', self::XSD_NS);

        $root = $doc->documentElement;
        if (!$root || $root->localName !== 'schema') {
            $this->errors[] = new XmlError(XmlError::SEVERITY_ERROR, 'Root element must be xs:schema');
            return;
        }

        $this->targetNamespace = $root->getAttribute('targetNamespace') ?: '';
        $this->typeSystem->setNamespace($this->targetNamespace);

        // Process imports and includes
        $this->processImports($root, $baseDir);

        // Process global simple types
        foreach ($this->xpath->query('/xs:schema/xs:simpleType', $root) as $node) {
            $this->processSimpleType($node, true);
        }

        // Process global complex types
        foreach ($this->xpath->query('/xs:schema/xs:complexType', $root) as $node) {
            $this->processComplexType($node, true);
        }

        // Process global elements
        foreach ($this->xpath->query('/xs:schema/xs:element', $root) as $node) {
            $this->processGlobalElement($node);
        }

        // Process global attributes
        foreach ($this->xpath->query('/xs:schema/xs:attribute', $root) as $node) {
            $this->processGlobalAttribute($node);
        }
    }

    /**
     * Process xs:import and xs:include elements.
     *
     * @param DOMElement $root
     * @param string     $baseDir
     */
    private function processImports(DOMElement $root, $baseDir)
    {
        $nodes = $this->xpath->query('xs:import | xs:include', $root);
        foreach ($nodes as $node) {
            $schemaLoc = $node->getAttribute('schemaLocation');
            if (!$schemaLoc) {
                continue;
            }

            // Resolve relative paths
            if ($baseDir && !preg_match('#^https?://#', $schemaLoc)) {
                $schemaLoc = $baseDir . DIRECTORY_SEPARATOR . $schemaLoc;
            }

            $realPath = realpath($schemaLoc);
            if ($realPath && isset($this->loadedSchemas[$realPath])) {
                continue; // Already loaded
            }

            if ($realPath) {
                $this->loadedSchemas[$realPath] = true;
                $importXsd = file_get_contents($realPath);
                if ($importXsd !== false) {
                    $importDoc = new DOMDocument();
                    libxml_use_internal_errors(true);
                    if ($importDoc->loadXML($importXsd)) {
                        $savedNs = $this->targetNamespace;
                        $savedXpath = $this->xpath;
                        $this->processSchemaDocument($importDoc, dirname($realPath));
                        $this->targetNamespace = $savedNs;
                        $this->xpath = $savedXpath;
                    }
                    libxml_clear_errors();
                    libxml_use_internal_errors(false);
                }
            }
        }
    }

    /**
     * Process a simpleType definition.
     *
     * @param DOMElement $node
     * @param bool       $isGlobal
     * @return SchemaType
     */
    private function processSimpleType(DOMElement $node, $isGlobal = false)
    {
        $name = $node->getAttribute('name');
        $qname = $name ? new QName($this->targetNamespace, $name) : null;

        $type = new SchemaType($qname);
        $type->setIsSimpleType(true);
        $type->setContentType(SchemaType::SIMPLE_CONTENT);

        // Process restriction
        $restriction = $this->xpathFirst('xs:restriction', $node);
        if ($restriction) {
            $type->setDerivationType(SchemaType::DT_RESTRICTION);
            $baseName = $restriction->getAttribute('base');
            if ($baseName) {
                $this->setBaseTypeRef($type, $baseName, $node);
            }
            $type->setSimpleVariety(SchemaType::ATOMIC);
            $this->processFacets($restriction, $type);
        }

        // Process list
        $list = $this->xpathFirst('xs:list', $node);
        if ($list) {
            $type->setSimpleVariety(SchemaType::LIST);
            $itemType = $list->getAttribute('itemType');
            if ($itemType) {
                $this->setBaseTypeRef($type, $itemType, $node);
            }
        }

        // Process union
        $union = $this->xpathFirst('xs:union', $node);
        if ($union) {
            $type->setSimpleVariety(SchemaType::UNION);
        }

        if ($isGlobal && $qname) {
            $this->typeSystem->addGlobalType($qname, $type);
        }

        return $type;
    }

    /**
     * Process a complexType definition.
     *
     * @param DOMElement $node
     * @param bool       $isGlobal
     * @return SchemaType
     */
    private function processComplexType(DOMElement $node, $isGlobal = false)
    {
        $name = $node->getAttribute('name');
        $qname = $name ? new QName($this->targetNamespace, $name) : null;

        $type = new SchemaType($qname);
        $type->setIsSimpleType(false);
        $type->setIsAbstract($node->getAttribute('abstract') === 'true');

        // Check for simpleContent
        $simpleContent = $this->xpathFirst('xs:simpleContent', $node);
        if ($simpleContent) {
            $type->setContentType(SchemaType::SIMPLE_CONTENT);
            $ext = $this->xpathFirst('xs:extension', $simpleContent);
            $res = $this->xpathFirst('xs:restriction', $simpleContent);
            if ($ext) {
                $type->setDerivationType(SchemaType::DT_EXTENSION);
                $this->setBaseTypeRef($type, $ext->getAttribute('base'), $node);
                $this->processAttributes($ext, $type);
            } elseif ($res) {
                $type->setDerivationType(SchemaType::DT_RESTRICTION);
                $this->setBaseTypeRef($type, $res->getAttribute('base'), $node);
                $this->processFacets($res, $type);
                $this->processAttributes($res, $type);
            }
        }

        // Check for complexContent
        $complexContent = $this->xpathFirst('xs:complexContent', $node);
        if ($complexContent) {
            $ext = $this->xpathFirst('xs:extension', $complexContent);
            $res = $this->xpathFirst('xs:restriction', $complexContent);
            if ($ext) {
                $type->setDerivationType(SchemaType::DT_EXTENSION);
                $this->setBaseTypeRef($type, $ext->getAttribute('base'), $node);
                $this->processContentModel($ext, $type);
                $this->processAttributes($ext, $type);
            } elseif ($res) {
                $type->setDerivationType(SchemaType::DT_RESTRICTION);
                $this->setBaseTypeRef($type, $res->getAttribute('base'), $node);
                $this->processContentModel($res, $type);
                $this->processAttributes($res, $type);
            }
        }

        // Direct content model (no simpleContent/complexContent wrapper)
        if (!$simpleContent && !$complexContent) {
            $mixed = $node->getAttribute('mixed') === 'true';
            $type->setContentType($mixed ? SchemaType::MIXED_CONTENT : SchemaType::ELEMENT_CONTENT);
            $this->processContentModel($node, $type);
            $this->processAttributes($node, $type);

            // Check if it has any child elements; if not, might be empty
            if (count($type->getElementProperties()) === 0
                && $type->getContentModel() === null
                && !$mixed) {
                $type->setContentType(SchemaType::EMPTY_CONTENT);
            }
        }

        if ($isGlobal && $qname) {
            $this->typeSystem->addGlobalType($qname, $type);
        }

        return $type;
    }

    /**
     * Process a global element declaration.
     *
     * @param DOMElement $node
     */
    private function processGlobalElement(DOMElement $node)
    {
        $name = $node->getAttribute('name');
        if (!$name) {
            return;
        }

        $qname = new QName($this->targetNamespace, $name);

        // Create document type
        $docType = new SchemaType($qname);
        $docType->setIsDocumentType(true);

        // Determine element type
        $typeName = $node->getAttribute('type');
        $elemType = null;

        // Check for inline type
        $inlineSimple = $this->xpathFirst('xs:simpleType', $node);
        $inlineComplex = $this->xpathFirst('xs:complexType', $node);

        if ($inlineSimple) {
            $elemType = $this->processSimpleType($inlineSimple);
        } elseif ($inlineComplex) {
            $elemType = $this->processComplexType($inlineComplex);
        } elseif ($typeName) {
            // Will be resolved later
            $elemType = new SchemaType($qname);
            $this->pendingTypeRefs[] = [
                'target' => $docType,
                'refName' => $typeName,
                'context' => $node,
                'kind' => 'element_type',
                'elemType' => $elemType,
            ];
        } else {
            // No type specified, defaults to xs:anyType
            $elemType = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_ANY_TYPE);
        }

        // Create element property on the document type
        $prop = new SchemaProperty($qname, SchemaProperty::XML_ELEMENT);
        if ($elemType) {
            $prop->setType($elemType);
        }
        $prop->setMinOccurs($node->getAttribute('minOccurs') !== '' ? (int) $node->getAttribute('minOccurs') : 1);
        $prop->setMaxOccurs($node->getAttribute('maxOccurs') !== '' ? $this->parseMaxOccurs($node->getAttribute('maxOccurs')) : 1);
        $prop->setNillable($node->getAttribute('nillable') === 'true');

        if ($node->hasAttribute('default')) {
            $prop->setDefaultValue($node->getAttribute('default'));
        }
        if ($node->hasAttribute('fixed')) {
            $prop->setFixedValue($node->getAttribute('fixed'));
        }

        $prop->setPhpName($this->toPascalCase($name));
        $prop->setGetterName('get' . $this->toPascalCase($name));
        $prop->setSetterName('set' . $this->toPascalCase($name));

        $docType->addElementProperty($prop);

        $this->typeSystem->addDocumentType($qname, $docType);
        if ($elemType) {
            $this->typeSystem->addGlobalElement($qname, $elemType);
        }
    }

    /**
     * Process a global attribute declaration.
     *
     * @param DOMElement $node
     */
    private function processGlobalAttribute(DOMElement $node)
    {
        $name = $node->getAttribute('name');
        if (!$name) {
            return;
        }

        $qname = new QName($this->targetNamespace, $name);
        $attrType = new SchemaType($qname);
        $attrType->setIsAttributeType(true);
        $attrType->setIsSimpleType(true);

        $typeName = $node->getAttribute('type');
        if ($typeName) {
            $this->pendingTypeRefs[] = [
                'target' => $attrType,
                'refName' => $typeName,
                'context' => $node,
                'kind' => 'base',
            ];
        }

        $this->typeSystem->addGlobalAttribute($qname, $attrType);
    }

    /**
     * Process the content model (sequence, choice, all) within a type.
     *
     * @param DOMElement $container
     * @param SchemaType $type
     */
    private function processContentModel(DOMElement $container, SchemaType $type)
    {
        $seq = $this->xpathFirst('xs:sequence', $container);
        $choice = $this->xpathFirst('xs:choice', $container);
        $all = $this->xpathFirst('xs:all', $container);

        $modelNode = $seq ?: $choice ?: $all;
        if (!$modelNode) {
            return;
        }

        $particleType = SchemaParticle::SEQUENCE;
        if ($choice) $particleType = SchemaParticle::CHOICE;
        if ($all) $particleType = SchemaParticle::ALL;

        $particle = new SchemaParticle($particleType);
        $particle->setMinOccurs($modelNode->getAttribute('minOccurs') !== '' ? (int) $modelNode->getAttribute('minOccurs') : 1);
        $particle->setMaxOccurs($modelNode->getAttribute('maxOccurs') !== '' ? $this->parseMaxOccurs($modelNode->getAttribute('maxOccurs')) : 1);

        $this->processParticleChildren($modelNode, $particle, $type);
        $type->setContentModel($particle);

        if ($type->getContentType() === SchemaType::EMPTY_CONTENT) {
            $type->setContentType(SchemaType::ELEMENT_CONTENT);
        }
    }

    /**
     * Process children of a content model group.
     *
     * @param DOMElement     $groupNode
     * @param SchemaParticle $parent
     * @param SchemaType     $ownerType
     */
    private function processParticleChildren(DOMElement $groupNode, SchemaParticle $parent, SchemaType $ownerType)
    {
        foreach ($groupNode->childNodes as $child) {
            if (!($child instanceof DOMElement)) {
                continue;
            }
            if ($child->namespaceURI !== self::XSD_NS) {
                continue;
            }

            switch ($child->localName) {
                case 'element':
                    $this->processLocalElement($child, $parent, $ownerType);
                    break;
                case 'sequence':
                    $sub = new SchemaParticle(SchemaParticle::SEQUENCE);
                    $sub->setMinOccurs($child->getAttribute('minOccurs') !== '' ? (int) $child->getAttribute('minOccurs') : 1);
                    $sub->setMaxOccurs($child->getAttribute('maxOccurs') !== '' ? $this->parseMaxOccurs($child->getAttribute('maxOccurs')) : 1);
                    $this->processParticleChildren($child, $sub, $ownerType);
                    $parent->addChild($sub);
                    break;
                case 'choice':
                    $sub = new SchemaParticle(SchemaParticle::CHOICE);
                    $sub->setMinOccurs($child->getAttribute('minOccurs') !== '' ? (int) $child->getAttribute('minOccurs') : 1);
                    $sub->setMaxOccurs($child->getAttribute('maxOccurs') !== '' ? $this->parseMaxOccurs($child->getAttribute('maxOccurs')) : 1);
                    $this->processParticleChildren($child, $sub, $ownerType);
                    $parent->addChild($sub);
                    break;
                case 'all':
                    $sub = new SchemaParticle(SchemaParticle::ALL);
                    $sub->setMinOccurs($child->getAttribute('minOccurs') !== '' ? (int) $child->getAttribute('minOccurs') : 1);
                    $sub->setMaxOccurs(1);
                    $this->processParticleChildren($child, $sub, $ownerType);
                    $parent->addChild($sub);
                    break;
                case 'any':
                    $wildcard = new SchemaParticle(SchemaParticle::WILDCARD);
                    $wildcard->setMinOccurs($child->getAttribute('minOccurs') !== '' ? (int) $child->getAttribute('minOccurs') : 1);
                    $wildcard->setMaxOccurs($child->getAttribute('maxOccurs') !== '' ? $this->parseMaxOccurs($child->getAttribute('maxOccurs')) : 1);
                    $wildcard->setWildcardNamespace($child->getAttribute('namespace') ?: '##any');
                    $processContents = $child->getAttribute('processContents') ?: 'strict';
                    $wildcard->setWildcardProcess($processContents === 'strict' ? 1 : ($processContents === 'lax' ? 2 : 3));
                    $parent->addChild($wildcard);
                    break;
                case 'group':
                    // group ref - not fully supported yet
                    break;
            }
        }
    }

    /**
     * Process a local element declaration within a content model.
     *
     * @param DOMElement     $node
     * @param SchemaParticle $parentParticle
     * @param SchemaType     $ownerType
     */
    private function processLocalElement(DOMElement $node, SchemaParticle $parentParticle, SchemaType $ownerType)
    {
        $name = $node->getAttribute('name');
        $ref = $node->getAttribute('ref');

        if ($ref && !$name) {
            $refQName = $this->resolveQName($ref, $node);
            $name = $refQName->getLocalPart();
        }

        if (!$name) {
            return;
        }

        $qname = new QName($this->targetNamespace, $name);

        // Create element particle
        $elemParticle = new SchemaParticle(SchemaParticle::ELEMENT);
        $elemParticle->setName($qname);
        $elemParticle->setMinOccurs($node->getAttribute('minOccurs') !== '' ? (int) $node->getAttribute('minOccurs') : 1);
        $elemParticle->setMaxOccurs($node->getAttribute('maxOccurs') !== '' ? $this->parseMaxOccurs($node->getAttribute('maxOccurs')) : 1);
        $elemParticle->setNillable($node->getAttribute('nillable') === 'true');

        // Determine type
        $typeName = $node->getAttribute('type');
        $elemType = null;

        $inlineSimple = $this->xpathFirst('xs:simpleType', $node);
        $inlineComplex = $this->xpathFirst('xs:complexType', $node);

        if ($inlineSimple) {
            $elemType = $this->processSimpleType($inlineSimple);
        } elseif ($inlineComplex) {
            $elemType = $this->processComplexType($inlineComplex);
        }

        if ($elemType) {
            $elemParticle->setType($elemType);
        }

        $parentParticle->addChild($elemParticle);

        // Create property on the owner type
        $prop = new SchemaProperty($qname, SchemaProperty::XML_ELEMENT);
        $prop->setMinOccurs($elemParticle->getMinOccurs());
        $prop->setMaxOccurs($elemParticle->getMaxOccurs());
        $prop->setNillable($elemParticle->isNillable());

        if ($node->hasAttribute('default')) {
            $prop->setDefaultValue($node->getAttribute('default'));
        }
        if ($node->hasAttribute('fixed')) {
            $prop->setFixedValue($node->getAttribute('fixed'));
        }

        $prop->setPhpName($this->toPascalCase($name));
        $prop->setGetterName('get' . $this->toPascalCase($name));
        $prop->setSetterName('set' . $this->toPascalCase($name));

        if ($elemType) {
            $prop->setType($elemType);
        } elseif ($typeName) {
            $prop->setTypeName($typeName);
            $this->pendingTypeRefs[] = [
                'target' => $prop,
                'refName' => $typeName,
                'context' => $node,
                'kind' => 'property',
            ];
        }

        $ownerType->addElementProperty($prop);
    }

    /**
     * Process attribute declarations on a type.
     *
     * @param DOMElement $container
     * @param SchemaType $type
     */
    private function processAttributes(DOMElement $container, SchemaType $type)
    {
        $attrs = $this->xpath->query('xs:attribute', $container);
        foreach ($attrs as $attrNode) {
            $name = $attrNode->getAttribute('name');
            $ref = $attrNode->getAttribute('ref');
            if ($ref && !$name) {
                $refQName = $this->resolveQName($ref, $attrNode);
                $name = $refQName->getLocalPart();
            }
            if (!$name) {
                continue;
            }

            $qname = new QName('', $name); // attributes typically unqualified
            $prop = new SchemaProperty($qname, SchemaProperty::XML_ATTRIBUTE);

            $use = $attrNode->getAttribute('use');
            $prop->setMinOccurs($use === 'required' ? 1 : 0);
            $prop->setMaxOccurs(1);

            if ($attrNode->hasAttribute('default')) {
                $prop->setDefaultValue($attrNode->getAttribute('default'));
            }
            if ($attrNode->hasAttribute('fixed')) {
                $prop->setFixedValue($attrNode->getAttribute('fixed'));
            }

            $prop->setPhpName($this->toCamelCase($name));
            $prop->setGetterName('get' . $this->toPascalCase($name));
            $prop->setSetterName('set' . $this->toPascalCase($name));

            $typeName = $attrNode->getAttribute('type');
            if ($typeName) {
                $prop->setTypeName($typeName);
                $this->pendingTypeRefs[] = [
                    'target' => $prop,
                    'refName' => $typeName,
                    'context' => $attrNode,
                    'kind' => 'property',
                ];
            }

            // Inline simple type
            $inlineSimple = $this->xpathFirst('xs:simpleType', $attrNode);
            if ($inlineSimple) {
                $inlineType = $this->processSimpleType($inlineSimple);
                $prop->setType($inlineType);
            }

            $type->addAttributeProperty($prop);
        }
    }

    /**
     * Process facets on a restriction.
     *
     * @param DOMElement $restriction
     * @param SchemaType $type
     */
    private function processFacets(DOMElement $restriction, SchemaType $type)
    {
        $facetNames = [
            'minInclusive', 'maxInclusive', 'minExclusive', 'maxExclusive',
            'minLength', 'maxLength', 'length', 'totalDigits', 'fractionDigits',
            'whiteSpace',
        ];

        foreach ($facetNames as $facetName) {
            $facetNodes = $this->xpath->query("xs:{$facetName}", $restriction);
            foreach ($facetNodes as $facetNode) {
                $type->setFacet($facetName, $facetNode->getAttribute('value'));
            }
        }

        // Pattern
        $patternNodes = $this->xpath->query('xs:pattern', $restriction);
        foreach ($patternNodes as $patternNode) {
            $type->setPatternFacet($patternNode->getAttribute('value'));
        }

        // Enumeration
        $enumNodes = $this->xpath->query('xs:enumeration', $restriction);
        $enums = [];
        foreach ($enumNodes as $enumNode) {
            $enums[] = $enumNode->getAttribute('value');
        }
        if (!empty($enums)) {
            $type->setEnumerationValues($enums);
        }
    }

    /**
     * Resolve pending type references after all types are parsed.
     */
    private function resolveTypeReferences()
    {
        foreach ($this->pendingTypeRefs as $ref) {
            $resolvedType = $this->resolveTypeByName($ref['refName'], $ref['context']);
            if ($resolvedType === null) {
                $this->errors[] = new XmlError(
                    XmlError::SEVERITY_ERROR,
                    "Unresolved type reference: {$ref['refName']}"
                );
                continue;
            }

            switch ($ref['kind']) {
                case 'base':
                    $ref['target']->setBaseType($resolvedType);
                    break;
                case 'property':
                    $ref['target']->setType($resolvedType);
                    break;
                case 'element_type':
                    // Copy resolved type properties to the element type placeholder
                    if (isset($ref['elemType'])) {
                        // Wire the resolved type to the document type's element property
                        $docType = $ref['target'];
                        foreach ($docType->getElementProperties() as $prop) {
                            $prop->setType($resolvedType);
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Resolve a type name (possibly prefixed) to a SchemaType.
     *
     * @param string     $typeName
     * @param DOMElement $context
     * @return SchemaType|null
     */
    private function resolveTypeByName($typeName, DOMElement $context)
    {
        $qname = $this->resolveQName($typeName, $context);

        // Check built-in types first
        if ($qname->getNamespaceURI() === BuiltinTypeSystem::XSD_NS) {
            $builtin = BuiltinTypeSystem::findByLocalName($qname->getLocalPart());
            if ($builtin) {
                return $builtin;
            }
        }

        // Check compiled types
        $found = $this->typeSystem->findType($qname);
        if ($found) {
            return $found;
        }

        // Try without namespace
        $noNs = new QName('', $qname->getLocalPart());
        $found = $this->typeSystem->findType($noNs);
        if ($found) {
            return $found;
        }

        // Try with target namespace
        $withTns = new QName($this->targetNamespace, $qname->getLocalPart());
        return $this->typeSystem->findType($withTns);
    }

    /**
     * Set a pending base type reference.
     *
     * @param SchemaType $type
     * @param string     $refName
     * @param DOMElement $context
     */
    private function setBaseTypeRef(SchemaType $type, $refName, DOMElement $context)
    {
        // Try immediate resolution
        $resolved = $this->resolveTypeByName($refName, $context);
        if ($resolved) {
            $type->setBaseType($resolved);
        } else {
            $this->pendingTypeRefs[] = [
                'target' => $type,
                'refName' => $refName,
                'context' => $context,
                'kind' => 'base',
            ];
        }
    }

    /**
     * Resolve a prefixed name to a QName using namespace context.
     *
     * @param string     $prefixedName
     * @param DOMElement $context
     * @return QName
     */
    private function resolveQName($prefixedName, DOMElement $context)
    {
        if (strpos($prefixedName, ':') !== false) {
            list($prefix, $local) = explode(':', $prefixedName, 2);
            $ns = $context->lookupNamespaceUri($prefix) ?: '';
            return new QName($ns, $local, $prefix);
        }
        // No prefix - could be default namespace or target namespace
        $defaultNs = $context->lookupNamespaceUri(null) ?: '';
        if ($defaultNs === self::XSD_NS) {
            return new QName(self::XSD_NS, $prefixedName);
        }
        return new QName($this->targetNamespace, $prefixedName);
    }

    /**
     * Query first matching element.
     *
     * @param string     $expr
     * @param DOMElement $context
     * @return DOMElement|null
     */
    private function xpathFirst($expr, DOMElement $context)
    {
        $result = $this->xpath->query($expr, $context);
        if ($result && $result->length > 0) {
            return $result->item(0);
        }
        return null;
    }

    /**
     * Parse maxOccurs value (could be "unbounded").
     *
     * @param string $value
     * @return int
     */
    private function parseMaxOccurs($value)
    {
        if ($value === 'unbounded') {
            return -1;
        }
        return (int) $value;
    }

    /**
     * Convert a name to PascalCase.
     *
     * @param string $name
     * @return string
     */
    private function toPascalCase($name)
    {
        $name = str_replace(['-', '_', '.'], ' ', $name);
        return str_replace(' ', '', ucwords($name));
    }

    /**
     * Convert a name to camelCase.
     *
     * @param string $name
     * @return string
     */
    private function toCamelCase($name)
    {
        $pascal = $this->toPascalCase($name);
        return lcfirst($pascal);
    }

    /**
     * Collect libxml errors.
     */
    private function collectLibxmlErrors()
    {
        $errors = libxml_get_errors();
        foreach ($errors as $err) {
            $this->errors[] = new XmlError(
                $err->level === LIBXML_ERR_WARNING ? XmlError::SEVERITY_WARNING : XmlError::SEVERITY_ERROR,
                trim($err->message),
                $err->file ?: null,
                $err->line,
                $err->column
            );
        }
        libxml_clear_errors();
    }
}
