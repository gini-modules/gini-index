<?php

namespace Gini\Controller;

class API
{
    private function modulePath($path=null)
    {
        $modulePath = \Gini\Config::get('dav.root') ?: APP_PATH . '/' . DATA_DIR . '/modules';
        return $modulePath . '/' . ltrim($path, '/');
    }

    private function digestFile()
    {
        return APP_PATH.'/'.DATA_DIR.'/digest';
    }

    private function verify($username, $password)
    {
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

    public function actionCreateToken($username=null, $password=null)
    {
        if (!$this->verify($username, $password)) {
            return false;
        }
        $auth = new \Gini\Index\Auth\DAV;
        return $auth->createToken($username);
    }

    public function actionSearch($keyword=null)
    {
        $regex = '{(?:'.implode('|', preg_split('{\s+}', $keyword)).')}i';
        $result = [];
        $info = @json_decode(file_get_contents($this->modulePath('index.json')), true);
        foreach ($info as $pkgname => $pkgs) {
            if (!$pkgs) {
                continue;
            }
            if (isset($result[$pkgname])) {
                continue;
            }
            if (preg_match($regex, $pkgname)) {
                $vers = array_keys($pkgs);
                // find latest match version
                foreach ($vers as $version) {
                    $v = new \Gini\Version($version);
                    if ($matched) {
                        if ($matched->compare($v) > 0) {
                            continue;
                        }
                    }
                    $matched = $v;
                }
                if ($matched) {
                    $result[$pkgname] = $pkgs[$matched->fullVersion];
                }
            }
        }
        return $result;
    }
}
