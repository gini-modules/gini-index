<?php

namespace Gini\Index\Auth;

use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\DAV\Server;
use Sabre\DAV\Exception\NotAuthenticated;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

const RANDOM_BYTES_LENGTH=20;

class DAV implements BackendInterface
{
    private $request;
    private $response;
    private $currentUser;

    public function __construct(RequestInterface $request, ResponseInterface $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    private function tokensFile()
    {
        return sys_get_temp_dir().'/gini-index-tokens.json';
    }

    public function createToken($username)
    {
        $token = sha1($username . ':' . openssl_random_pseudo_bytes(RANDOM_BYTES_LENGTH));
        $bcrypt = password_hash($token, PASSWORD_BCRYPT);

        $file = $this->tokensFile();
        $tokenInfo = (array) @json_decode(file_get_contents($file), true);
        $tokenInfo[$username] = $bcrypt;
        file_put_contents($file, json_encode($tokenInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return base64_encode($username . ':' . $token);
    }

    protected function validateUserToken($username, $token)
    {
        $tokenInfo = (array) @json_decode(file_get_contents($this->tokensFile()), true);
        return isset($tokenInfo[$username]) && password_verify($token, $tokenInfo[$username]);
    }

    public function check(RequestInterface $request, ResponseInterface $response)
    {
        $auth = new HTTP('', $request, $response);
        $usertoken = $auth->getCredentials($request);
        if (!$usertoken) {
            return [false, 'No Gini authentication headers were found'];
        }

        if ($this->validateUserToken($usertoken[0], $usertoken[1])) {
            $this->currentUser = $usertoken[0];
            return [true, 'principals/' . $this->currentUser];
        }
        return [false, 'Username or token does not match'];
    }

    public function challenge(RequestInterface $request, ResponseInterface $response)
    {
        $auth = new HTTP('', $request, $response);
        $auth->requireLogin();
    }

    public function getCurrentUser()
    {
        return $this->currentUser;
    }
}
