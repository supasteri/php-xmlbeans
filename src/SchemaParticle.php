<?php

namespace XmlBeans;

use XmlBeans\Common\QName;

/**
 * Represents a particle in a content model (sequence, choice, all, element, wildcard).
 * Mirrors org.apache.xmlbeans.SchemaParticle.
 */
class SchemaParticle
{
    const ELEMENT = 1;
    const ALL = 2;
    const CHOICE = 3;
    const SEQUENCE = 4;
    const WILDCARD = 5;

    /** @var int */
    private $particleType;

    /** @var int */
    private $minOccurs = 1;

    /** @var int -1 = unbounded */
    private $maxOccurs = 1;

    /** @var QName|null Element name (for ELEMENT type) */
    private $name;

    /** @var SchemaType|null Element type (for ELEMENT type) */
    private $type;

    /** @var SchemaParticle[] Child particles (for ALL, CHOICE, SEQUENCE) */
    private $children = [];

    /** @var bool */
    private $nillable = false;

    /** @var string|null Default value */
    private $defaultValue;

    /** @var string|null Wildcard namespace constraint */
    private $wildcardNamespace;

    /** @var int Wildcard process contents: strict=1, lax=2, skip=3 */
    private $wildcardProcess = 1;

    /**
     * @param int $particleType
     */
    public function __construct($particleType)
    {
        $this->particleType = $particleType;
    }

    /** @return int */
    public function getParticleType()
    {
        return $this->particleType;
    }

    /** @return int */
    public function getMinOccurs()
    {
        return $this->minOccurs;
    }

    /** @param int $min */
    public function setMinOccurs($min)
    {
        $this->minOccurs = (int) $min;
    }

    /** @return int */
    public function getMaxOccurs()
    {
        return $this->maxOccurs;
    }

    /** @param int $max Use -1 for unbounded */
    public function setMaxOccurs($max)
    {
        $this->maxOccurs = (int) $max;
    }

    /** @return bool */
    public function isUnbounded()
    {
        return $this->maxOccurs === -1;
    }

    /** @return QName|null */
    public function getName()
    {
        return $this->name;
    }

    /** @param QName $name */
    public function setName(QName $name)
    {
        $this->name = $name;
    }

    /** @return SchemaType|null */
    public function getType()
    {
        return $this->type;
    }

    /** @param SchemaType $type */
    public function setType(SchemaType $type)
    {
        $this->type = $type;
    }

    /** @return SchemaParticle[] */
    public function getChildren()
    {
        return $this->children;
    }

    /** @param SchemaParticle $child */
    public function addChild(SchemaParticle $child)
    {
        $this->children[] = $child;
    }

    /** @param SchemaParticle[] $children */
    public function setChildren(array $children)
    {
        $this->children = $children;
    }

    /** @return int Child count */
    public function countChildren()
    {
        return count($this->children);
    }

    /** @return bool */
    public function isNillable()
    {
        return $this->nillable;
    }

    /** @param bool $flag */
    public function setNillable($flag)
    {
        $this->nillable = (bool) $flag;
    }

    /** @return string|null */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /** @param string $value */
    public function setDefaultValue($value)
    {
        $this->defaultValue = $value;
    }

    /** @return string|null */
    public function getWildcardNamespace()
    {
        return $this->wildcardNamespace;
    }

    /** @param string $ns */
    public function setWildcardNamespace($ns)
    {
        $this->wildcardNamespace = $ns;
    }

    /** @return int */
    public function getWildcardProcess()
    {
        return $this->wildcardProcess;
    }

    /** @param int $process */
    public function setWildcardProcess($process)
    {
        $this->wildcardProcess = (int) $process;
    }
}
