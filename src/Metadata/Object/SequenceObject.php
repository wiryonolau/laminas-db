<?php

namespace Itseasy\Database\Metadata\Object;

class SequenceObject
{
    protected $name;
    protected $dataType;
    protected $numericPrecision;
    protected $numericPrecisionRadix;
    protected $numericScale;
    protected $startValue;
    protected $minimumValue;
    protected $maximumValue;
    protected $increment;
    protected $cycleOption;

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getDataType()
    {
        return $this->dataType;
    }

    public function setDataType($dataType)
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function getNumericPrecision()
    {
        return $this->numericPrecision;
    }

    public function setNumericPrecision($numericPrecision)
    {
        $this->numericPrecision = $numericPrecision;
        return $this;
    }

    public function getNumericPrecisionRadix()
    {
        return $this->numericPrecisionRadix;
    }

    public function setNumericPrecisionRadix($numericPrecisionRadix)
    {
        $this->numericPrecisionRadix = $numericPrecisionRadix;
        return $this;
    }

    public function getNumericScale()
    {
        return $this->numericScale;
    }

    public function setNumericScale($numericScale)
    {
        $this->numericScale = $numericScale;
        return $this;
    }

    public function getStartValue()
    {
        return $this->startValue;
    }

    public function setStartValue($startValue)
    {
        $this->startValue = $startValue;
        return $this;
    }

    public function getMinimumValue()
    {
        return $this->minimumValue;
    }

    public function setMinimumValue($minimumValue)
    {
        $this->minimumValue = $minimumValue;
        return $this;
    }

    public function getMaximumValue()
    {
        return $this->maximumValue;
    }

    public function setMaximumValue($maximumValue)
    {
        $this->maximumValue = $maximumValue;
        return $this;
    }

    public function getIncrement()
    {
        return $this->increment;
    }

    public function setIncrement($increment)
    {
        $this->increment = $increment;
        return $this;
    }

    public function getCycleOption()
    {
        return $this->cycleOption;
    }

    public function setCycleOption($cycleOption)
    {
        $this->cycleOption = $cycleOption;
        return $this;
    }
}
