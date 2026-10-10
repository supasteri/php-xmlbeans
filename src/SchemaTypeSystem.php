<?php

namespace XmlBeans;

use XmlBeans\Common\QName;

/**
 * A type system compiled from one or more XSD schemas.
 * Contains all global types, elements, and attributes.
 * Mirrors org.apache.xmlbeans.SchemaTypeSystem.
 */
class SchemaTypeSystem
{
    /** @var string */
    private $name;

    /** @var SchemaType[] keyed by QName hash */
    private $globalTypes = [];

    /** @var SchemaType[] keyed by QName hash (document types wrapping global elements) */
    private $documentTypes = [];

    /** @var SchemaType[] keyed by QName hash */
    private $attributeTypes = [];

    /** @var array Global element declarations: QName hash => SchemaType */
    private $globalElements = [];

    /** @var array Global attribute declarations: QName hash => SchemaType */
    private $globalAttributes = [];

    /** @var string|null Base namespace */
    private $namespace;

    /** @var SchemaTypeSystem|null Parent type system (for layered compilation) */
    private $parentTypeSystem;

    /**
     * @param string $name
     */
    public function __construct($name = 'default')
    {
        $this->name = $name;
    }

    /** @return string */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Resolve a global type by QName.
     *
     * @param QName $name
     * @return SchemaType|null
     */
    public function findType(QName $name)
    {
        $key = $name->hashKey();
        if (isset($this->globalTypes[$key])) {
            return $this->globalTypes[$key];
        }
        if ($this->parentTypeSystem) {
            return $this->parentTypeSystem->findType($name);
        }
        return null;
    }

    /**
     * Resolve a document type by element QName.
     *
     * @param QName $name
     * @return SchemaType|null
     */
    public function findDocumentType(QName $name)
    {
        $key = $name->hashKey();
        if (isset($this->documentTypes[$key])) {
            return $this->documentTypes[$key];
        }
        if ($this->parentTypeSystem) {
            return $this->parentTypeSystem->findDocumentType($name);
        }
        return null;
    }

    /**
     * Resolve a global element by QName.
     *
     * @param QName $name
     * @return SchemaType|null
     */
    public function findElement(QName $name)
    {
        $key = $name->hashKey();
        if (isset($this->globalElements[$key])) {
            return $this->globalElements[$key];
        }
        if ($this->parentTypeSystem) {
            return $this->parentTypeSystem->findElement($name);
        }
        return null;
    }

    /**
     * @param QName      $name
     * @param SchemaType $type
     */
    public function addGlobalType(QName $name, SchemaType $type)
    {
        $type->setTypeSystem($this);
        $this->globalTypes[$name->hashKey()] = $type;
    }

    /**
     * @param QName      $elementName
     * @param SchemaType $docType
     */
    public function addDocumentType(QName $elementName, SchemaType $docType)
    {
        $docType->setTypeSystem($this);
        $this->documentTypes[$elementName->hashKey()] = $docType;
    }

    /**
     * @param QName      $name
     * @param SchemaType $type
     */
    public function addGlobalElement(QName $name, SchemaType $type)
    {
        $this->globalElements[$name->hashKey()] = $type;
    }

    /**
     * @param QName      $name
     * @param SchemaType $type
     */
    public function addGlobalAttribute(QName $name, SchemaType $type)
    {
        $this->globalAttributes[$name->hashKey()] = $type;
    }

    /** @return SchemaType[] */
    public function getGlobalTypes()
    {
        return array_values($this->globalTypes);
    }

    /** @return SchemaType[] */
    public function getDocumentTypes()
    {
        return array_values($this->documentTypes);
    }

    /** @return string|null */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /** @param string $ns */
    public function setNamespace($ns)
    {
        $this->namespace = $ns;
    }

    /** @param SchemaTypeSystem $parent */
    public function setParentTypeSystem(SchemaTypeSystem $parent)
    {
        $this->parentTypeSystem = $parent;
    }

    /** @return SchemaTypeSystem|null */
    public function getParentTypeSystem()
    {
        return $this->parentTypeSystem;
    }
}
