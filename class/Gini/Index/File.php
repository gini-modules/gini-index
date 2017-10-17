<?php
namespace Gini\Index;

class File extends \Sabre\DAV\FS\File implements \Sabre\DAVACL\IACL
{
    use ACLTrait;
}
