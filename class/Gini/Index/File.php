<?php
namespace Gini\Index;

use Sabre\DAV\FS\File as DAVFile;
use Sabre\DAVACL\IACL;
use Sabre\DAV\Exception\Forbidden;

class File extends DAVFile implements IACL
{
    use ACLTrait;
    use NameingTrait;

    public function delete()
    {
        if (basename($this->path) == 'index.json') {
            throw new Forbidden('Not allowed to delete index.json');
        }
        return parent::delete();
    }
}
