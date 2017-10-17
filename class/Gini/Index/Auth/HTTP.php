<?php

namespace Gini\Index\Auth;

use \Sabre\HTTP\Auth\AbstractAuth;

class HTTP extends AbstractAuth
{
    public function getCredentials()
    {
        $auth = $this->request->getHeader('Authorization');
        if (!$auth) {
            return null;
        }

        if (strtolower(substr($auth, 0, 5)) !== 'gini ') {
            return null;
        }

        return explode(':', base64_decode(substr($auth, 5)), 2);
    }

    public function requireLogin()
    {
        $this->response->setHeader('WWW-Authenticate', 'Gini');
        $this->response->setStatus(401);
    }
}
