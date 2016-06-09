<?php

namespace Gini\Index\Auth;

use \Sabre\DAV\Auth\Backend\BackendInterface;
use \Sabre\DAV\Server;
use \Sabre\DAV\Exception\NotAuthenticated;

const RANDOM_BYTES_LENGTH=20;

class DAV implements BackendInterface
{
    private function tokensFile() {
        return sys_get_temp_dir().'/gini-index-tokens.json';
    }

    public function createToken($username) {
        $token = sha1($username . ':' . openssl_random_pseudo_bytes(RANDOM_BYTES_LENGTH));
        $bcrypt = password_hash($token, PASSWORD_BCRYPT);

        $file = $this->tokensFile();
        $tokenInfo = (array) @json_decode(file_get_contents($file), true);
        $tokenInfo[$username] = $bcrypt;
        file_put_contents($file, J($tokenInfo));
        return base64_encode($username . ':' . $token);
    }

    protected function validateUserToken($username, $token) {
        $tokenInfo = (array) @json_decode(file_get_contents($this->tokensFile()), true);
        return isset($tokenInfo[$username]) && password_verify($token, $tokenInfo[$username]);
    }

    public function authenticate(Server $server, $realm) {
        $auth = new HTTP($realm, $server->httpRequest, $server->httpResponse);
        $usertoken = $auth->getCredentials($server->httpRequest);
        if (!$usertoken) {
            $auth->requireLogin();
            throw new NotAuthenticated('No Gini authentication headers were found');
        }

        // Authenticates the user
        if (!$this->validateUserToken($usertoken[0], $usertoken[1])) {
            $auth->requireLogin();
            throw new NotAuthenticated('Username or token does not match');
        }

        $this->currentUser = $usertoken[0];
        return true;
    }

    private $currentUser;
    public function getCurrentUser() {
        return $this->currentUser;
    }
}
