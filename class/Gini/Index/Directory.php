<?php

namespace Gini\Index;

use Sabre\DAV\Exception\NotFound;
use Sabre\HTTP\URLUtil;
use Sabre\DAVACL\PrincipalCollection;
use Sabre\DAV\FS\Directory as DAVDirectory;
use Sabre\DAVACL\IACL;

class Directory extends DAVDirectory implements IACL
{
    use ACLTrait;
    use NameingTrait;

    private $isRoot = false;

    public function __construct($collection, $isRoot = false)
    {
        parent::__construct($collection);
        $this->isRoot = $isRoot;
    }

    public function isRoot()
    {
        return $this->isRoot;
    }

    public function getChild($name)
    {
        if ($this->isRoot && $name == 'principals') {
            $principalBackend = new PrincipalBackend();
            return new PrincipalCollection($principalBackend, 'principals');
        }

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
            if ($file[0] === '.') {
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
