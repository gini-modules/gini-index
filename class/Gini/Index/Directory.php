<?php

namespace Gini\Index;

use \Sabre\DAV\Exception\NotFound;
use \Sabre\HTTP\URLUtil;
use \Sabre\DAVACL\PrincipalCollection;

class Directory extends \Sabre\DAV\FS\Directory implements \Sabre\DAVACL\IACL
{
    use ACLTrait;

    private $isRoot = false;

    public function __construct($collection, $isRoot=false)
    {
        parent::__construct($collection);
        $this->isRoot = $isRoot;
    }

    public function getChild($name)
    {
        if ($this->isRoot && $name == 'principals') {
            $principalBackend = new PrincipalBackend();
            return new PrincipalCollection($principalBackend);
        }

        $bb = $this->path;
        $path = rtrim($this->path, '/') . '/' . ltrim($name, '/');
        if (!file_exists($path)) {
            throw new NotFound('File with name ' . $path . ' could not be located');
        }

        if (is_dir($path)) {
            return new Directory($path);
        } else {
            return new File($path);
        }
    }
        
    public function getChildren()
    {
        $result = [];
        foreach (scandir($this->path) as $file) {
            if ($file==='.' || $file==='..') {
                continue;
            }
            $result[] = $this->getChild($file);
        }

        if ($this->isRoot) {
            $result[] = $this->getChild('principals');
        }

        return $result;
    }
}
