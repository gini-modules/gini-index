<?php

namespace Gini\Controller;

class API {

    private function digestFile() {
        return APP_PATH.'/'.DATA_DIR.'/digest';
    }

    private function verify($username, $password) {
        foreach (file($this->digestFile(), FILE_IGNORE_NEW_LINES) as $line) {
            if (substr_count($line, ':') !== 2) {
                return false;
            }
            list($username, $realm, $A1) = explode(':', $line);
            if (!preg_match('/^[a-zA-Z0-9]{32}$/', $A1)) {
                return false;
            }
            if ($A1 === false || is_null($A1)) {
                return false;
            }
            if (!is_string($A1)) {
                return false;
            }
            if ($A1 === md5($username . ':' . $realm . ':' . $password)) {
                return true;
            }
        }
        return false;
    }

    public function actionCreateToken($username=null, $password=null) {
        if (!$this->verify($username, $password)) {
            return false;
        }
        $auth = new \Gini\Index\Auth\DAV;
        return $auth->createToken($username);
    }

}