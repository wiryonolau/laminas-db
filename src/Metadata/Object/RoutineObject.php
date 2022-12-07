<?php

namespace Itseasy\Database\Metadata\Object;

class RoutineObject
{
    protected $name;
    protected $type;
    protected $body;
    protected $definition;
    protected $dataType;
    protected $externalName;
    protected $externalLanguage;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setDataType($dataType)
    {
        $this->dataType = $dataType;
    }

    public function getDataType()
    {
        return $this->dataType;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setDefinition($definition)
    {
        $this->definition = $definition;
    }

    public function getDefinition()
    {
        return $this->definition;
    }

    public function setExternalName($externalName)
    {
        $this->externalName = $externalName;
    }

    public function getExternalName()
    {
        return $this->externalName;
    }

    public function setExternalLanguage($externalLanguage)
    {
        $this->externalLanguage = $externalLanguage;
    }

    public function getExternalLanguage()
    {
        return $this->externalLanguage;
    }
}
