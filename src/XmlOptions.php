<?php

namespace XmlBeans;

/**
 * Options for XML parsing, validation, and serialization.
 * Mirrors org.apache.xmlbeans.XmlOptions.
 */
class XmlOptions
{
    /** @var bool */
    private $savePrettyPrint = false;

    /** @var int */
    private $savePrettyPrintIndent = 2;

    /** @var string|null */
    private $characterEncoding = null;

    /** @var bool */
    private $loadStripWhitespace = false;

    /** @var bool */
    private $loadStripComments = false;

    /** @var bool */
    private $loadStripProcinsts = false;

    /** @var bool */
    private $loadLineNumbers = false;

    /** @var bool */
    private $validateOnSet = false;

    /** @var array */
    private $errorListeners = [];

    /** @var array */
    private $validationErrors = [];

    /** @var string|null */
    private $documentType = null;

    /** @var array */
    private $additionalNamespaces = [];

    /** @var bool */
    private $saveNamespacesFirst = false;

    /** @var bool */
    private $saveAggressiveNamespaces = false;

    /** @var string|null */
    private $saveSyntheticDocumentElement = null;

    /** @var bool */
    private $saveOuter = false;

    /** @var bool */
    private $saveInner = false;

    /** @var bool */
    private $useDefaultNamespace = false;

    /** @var string|null */
    private $saveImplicitNamespaces = null;

    /**
     * @return self
     */
    public function setSavePrettyPrint($enabled = true)
    {
        $this->savePrettyPrint = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSavePrettyPrint()
    {
        return $this->savePrettyPrint;
    }

    /**
     * @param int $indent
     * @return self
     */
    public function setSavePrettyPrintIndent($indent)
    {
        $this->savePrettyPrintIndent = (int) $indent;
        return $this;
    }

    /**
     * @return int
     */
    public function getSavePrettyPrintIndent()
    {
        return $this->savePrettyPrintIndent;
    }

    /**
     * @param string $encoding
     * @return self
     */
    public function setCharacterEncoding($encoding)
    {
        $this->characterEncoding = $encoding;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCharacterEncoding()
    {
        return $this->characterEncoding;
    }

    /**
     * @return self
     */
    public function setLoadStripWhitespace($enabled = true)
    {
        $this->loadStripWhitespace = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLoadStripWhitespace()
    {
        return $this->loadStripWhitespace;
    }

    /**
     * @return self
     */
    public function setLoadStripComments($enabled = true)
    {
        $this->loadStripComments = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLoadStripComments()
    {
        return $this->loadStripComments;
    }

    /**
     * @return self
     */
    public function setLoadStripProcinsts($enabled = true)
    {
        $this->loadStripProcinsts = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLoadStripProcinsts()
    {
        return $this->loadStripProcinsts;
    }

    /**
     * @return self
     */
    public function setLoadLineNumbers($enabled = true)
    {
        $this->loadLineNumbers = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLoadLineNumbers()
    {
        return $this->loadLineNumbers;
    }

    /**
     * @return self
     */
    public function setValidateOnSet($enabled = true)
    {
        $this->validateOnSet = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getValidateOnSet()
    {
        return $this->validateOnSet;
    }

    /**
     * @param callable $listener function(XmlError $error)
     * @return self
     */
    public function addErrorListener(callable $listener)
    {
        $this->errorListeners[] = $listener;
        return $this;
    }

    /**
     * @return callable[]
     */
    public function getErrorListeners()
    {
        return $this->errorListeners;
    }

    /**
     * @param array $errors
     * @return self
     */
    public function setErrorCollection(array &$errors)
    {
        $this->validationErrors = &$errors;
        return $this;
    }

    /**
     * @return array
     */
    public function &getValidationErrors()
    {
        return $this->validationErrors;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setDocumentType($type)
    {
        $this->documentType = $type;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDocumentType()
    {
        return $this->documentType;
    }

    /**
     * @param string $prefix
     * @param string $uri
     * @return self
     */
    public function addAdditionalNamespace($prefix, $uri)
    {
        $this->additionalNamespaces[$prefix] = $uri;
        return $this;
    }

    /**
     * @return array
     */
    public function getAdditionalNamespaces()
    {
        return $this->additionalNamespaces;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setSaveNamespacesFirst($enabled = true)
    {
        $this->saveNamespacesFirst = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSaveNamespacesFirst()
    {
        return $this->saveNamespacesFirst;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setSaveOuter($enabled = true)
    {
        $this->saveOuter = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSaveOuter()
    {
        return $this->saveOuter;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setSaveInner($enabled = true)
    {
        $this->saveInner = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSaveInner()
    {
        return $this->saveInner;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setUseDefaultNamespace($enabled = true)
    {
        $this->useDefaultNamespace = (bool) $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getUseDefaultNamespace()
    {
        return $this->useDefaultNamespace;
    }
}
