<?php

namespace XmlBeans;

/**
 * Represents an error encountered during parsing, validation, or compilation.
 */
class XmlError
{
    const SEVERITY_ERROR = 0;
    const SEVERITY_WARNING = 1;
    const SEVERITY_INFO = 2;

    /** @var int */
    private $severity;

    /** @var string */
    private $message;

    /** @var string|null */
    private $source;

    /** @var int|null */
    private $line;

    /** @var int|null */
    private $column;

    /** @var int|null */
    private $offset;

    /**
     * @param int    $severity
     * @param string $message
     * @param string|null $source
     * @param int|null $line
     * @param int|null $column
     */
    public function __construct($severity, $message, $source = null, $line = null, $column = null)
    {
        $this->severity = $severity;
        $this->message = $message;
        $this->source = $source;
        $this->line = $line;
        $this->column = $column;
    }

    /** @return int */
    public function getSeverity()
    {
        return $this->severity;
    }

    /** @return string */
    public function getMessage()
    {
        return $this->message;
    }

    /** @return string|null */
    public function getSource()
    {
        return $this->source;
    }

    /** @return int|null */
    public function getLine()
    {
        return $this->line;
    }

    /** @return int|null */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @param int $offset
     * @return self
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /** @return int|null */
    public function getOffset()
    {
        return $this->offset;
    }

    /** @return string */
    public function getSeverityLabel()
    {
        switch ($this->severity) {
            case self::SEVERITY_ERROR:
                return 'error';
            case self::SEVERITY_WARNING:
                return 'warning';
            case self::SEVERITY_INFO:
                return 'info';
            default:
                return 'unknown';
        }
    }

    /** @return string */
    public function __toString()
    {
        $loc = '';
        if ($this->source !== null) {
            $loc .= $this->source;
        }
        if ($this->line !== null) {
            $loc .= ':' . $this->line;
            if ($this->column !== null) {
                $loc .= ':' . $this->column;
            }
        }
        $prefix = $loc ? "[$loc] " : '';
        return $prefix . $this->getSeverityLabel() . ': ' . $this->message;
    }
}
