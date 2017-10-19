<?php

namespace Gini\Controller\CGI;

use \Gini\Controller\CGI;

class Index extends CGI
{
    public function __index()
    {
        $server = new \Gini\Index\Server();
        $server->execute();
        return false;
    }
}
