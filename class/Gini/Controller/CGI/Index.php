<?php

namespace Gini\Controller\CGI;

class Index extends \Gini\Controller\CGI {

    private function modulePath($path=null) {
        $modulePath = \Gini\Config::get('dav.root') ?: APP_PATH . '/' . DATA_DIR . '/modules';
        return $modulePath . '/' . ltrim($path, '/');
    }

    private function writeInfoFile($filename, $data) {
        return file_put_contents($filename, $data, LOCK_EX);
    }

    public function _fileModified($path) {

        if (!preg_match('`^([\w-]+)/([\w-.]+)\.tgz$`', $path, $parts)) return;

        $module = $parts[1];
        $version = $parts[2];

        $fullPath = escapeshellcmd($this->modulePath($path));
        // Convert json to an array instead of an object for `mergeSatisInfo`.
        $info = json_decode(`tar -zxOf $fullPath gini.json`, true);

        $indexPath = $this->modulePath($module.'/index.json');
        $indexInfo = @json_decode(file_get_contents($indexPath), true);
        if (!$indexInfo) {
            $indexInfo = [];
        }

        $indexInfo[$version] = $info;
        $this->writeInfoFile($indexPath, J($indexInfo));

        $totalIndexPath = $this->modulePath('index.json');
        $totalIndexInfo = @json_decode(file_get_contents($totalIndexPath), true);
        if (!$totalIndexInfo) {
            $totalIndexInfo = [];
        }

        $totalIndexInfo[$module] = $indexInfo;
        $this->writeInfoFile($totalIndexPath, J($totalIndexInfo));
    }

    public function _fileUnbind($path) {
        if (!preg_match('`^([\w-]+)/([\w-.]+)\.tgz$`', $path, $parts)) return;

        $module = $parts[1];
        $version = $parts[2];

        $indexPath = $this->modulePath($module.'/index.json');
        $indexInfo = @json_decode(file_get_contents($indexPath), true);
        if (is_array($indexInfo)) {
            unset($indexInfo[$version]);
            $this->writeInfoFile($indexPath, J($indexInfo));
        }

        $totalIndexPath = $this->modulePath('index.json');
        $totalIndexInfo = @json_decode(file_get_contents($totalIndexPath), true);
        if (is_array($totalIndexInfo)) {
            unset($totalIndexInfo[$module][$version]);
            $this->writeInfoFile($totalIndexPath, J($totalIndexInfo));
        }

    }

    private function lockFile() {
        return sys_get_temp_dir().'/gini-index.lock';
    }

    private function digestFile() {
        return APP_PATH.'/'.DATA_DIR.'/digest';
    }

    public function __index() {

        $rootPath = $this->modulePath();
        \Gini\File::ensureDir($rootPath);

        // Now we're creating a whole bunch of objects
        $rootDirectory = new \Sabre\DAV\FS\Directory($rootPath);

        // The server object is responsible for making sense out of the WebDAV protocol
        $server = new \Sabre\DAV\Server($rootDirectory);
        
        // If your server is not on your webroot, make sure the following line has the
        // correct information
        // $server->setBaseUri('/url/to/server.php');

        // The lock manager is reponsible for making sure users don't overwrite
        // each others changes.
        $lockBackend = new \Sabre\DAV\Locks\Backend\File($this->lockFile());
        $lockPlugin = new \Sabre\DAV\Locks\Plugin($lockBackend);
        $server->addPlugin($lockPlugin);

        // This ensures that we get a pretty index in the browser, but it is
        // optional.
        // $server->addPlugin(new \Sabre\DAV\Browser\Plugin());

        $realm = \Gini\Config::get('dav.auth')['realm'];

        // Adding the plugin to the server
        $authArray = preg_split('/\s+/', strval(
            $server->httpRequest->getHeader('Authorization')), 2, PREG_SPLIT_NO_EMPTY);
        $authScheme = empty($authArray) ? NULL : strtolower($authArray[0]);
        if ($server->httpRequest->getMethod() == 'GET') {
            // No need to auth.
        } elseif ($authScheme == 'gini') {
            $auth = new \Gini\Index\Auth\DAV;
            $authPlugin = new \Sabre\DAV\Auth\Plugin($auth, $realm);
            $server->addPlugin($authPlugin);
        } else {
            $digestAuth = new \Sabre\DAV\Auth\Backend\File($this->digestFile());
            $authPlugin = new \Sabre\DAV\Auth\Plugin($digestAuth, $realm);
            $server->addPlugin($authPlugin);
        }

        $server->on('afterWriteContent', [$this, '_fileModified']);
        $server->on('afterCreateFile', [$this, '_fileModified']);

        $server->on('afterUnbind', [$this, '_fileUnbind']);

        // All we need to do now, is to fire up the server
        $server->exec();
    }
    
}