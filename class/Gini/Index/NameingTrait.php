<?php

namespace Gini\Index;

trait NameingTrait
{
    private function normalize($name)
    {
        return strtr(mb_strtolower($name), '_', '-');
    }

    public function createFile($name, $data = null)
    {
        return parent::createFile($this->normalize($name), $data);
    }

    public function createDirectory($name)
    {
        return parent::createDirectory($this->normalize($name));
    }

    public function setName($name)
    {
        return parent::setName($this->normalize($name));
    }
}
