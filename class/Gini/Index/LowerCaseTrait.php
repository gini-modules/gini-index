<?php

namespace Gini\Index;

trait LowerCaseTrait
{
    public function createFile($name, $data = null)
    {
        return parent::createFile(mb_strtolower($name), $data);
    }

    public function createDirectory($name)
    {
        return parent::createDirectory(mb_strtolower($name));
    }

    public function setName($name)
    {
        return parent::setName(mb_strlower($name));
    }
}
