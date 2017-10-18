<?php
namespace Gini\Index;

class File extends \Sabre\DAV\FS\File implements \Sabre\DAVACL\IACL
{
    use ACLTrait;

    public function delete()
    {
        if (basename($this->path) == 'index.json') {
            throw new \Sabre\DAV\Exception\Forbidden('Not allowed to delete index.json');
        }
        return parent::delete();
    }
}
