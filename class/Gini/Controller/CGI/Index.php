<?php

namespace Gini\Controller\CGI;

use \Gini\Controller\CGI;

class Index extends CGI
{
    private function modulePath($path=null)
    {
        return \Gini\Module\GiniIndex::modulePath($path);
    }

    private function writeInfoFile($filename, $data)
    {
        return file_put_contents($filename, $data, LOCK_EX);
    }

    public function _fileModified($path)
    {
        if (!preg_match('`^([\w-]+)/([\w-.]+)\.tgz$`', $path, $parts)) {
            return;
        }

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

    public function _fileUnbind($path)
    {
        if (!preg_match('`^([\w-]+)/([\w-.]+)\.tgz$`', $path, $parts)) {
            return;
        }

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

    private function lockFile()
    {
        return sys_get_temp_dir().'/gini-index.lock';
    }

    private function digestFile()
    {
        return APP_PATH.'/'.DATA_DIR.'/digest';
    }

    public function __index()
    {
        $conf = \Gini\Config::get('database.default');

        $rootPath = $this->modulePath();
        \Gini\File::ensureDir($rootPath);
        $rootDirectory = new \Gini\Index\Directory($rootPath, true);

        // The server object is responsible for making sense out of the WebDAV protocol
        $server = new \Sabre\DAV\Server($rootDirectory);
        
        // If your server is not on your webroot, make sure the following line has the
        // correct information
        // $server->setBaseUri('/url/to/server.php');

        // The lock manager is reponsible for making sure users don't overwrite
        // each others changes.
        $lockBackend = new \Sabre\DAV\Locks\Backend\File($this->lockFile());
        $server->addPlugin(new \Sabre\DAV\Locks\Plugin($lockBackend));

        // This ensures that we get a pretty index in the browser, but it is
        // optional.
        // $browserPlugin = new \Sabre\DAV\Browser\Plugin();
        // $server->addPlugin($browserPlugin);

        $realm = \Gini\Config::get('dav.auth')['realm'];

        // Adding the plugin to the server
        $authArray = preg_split('/\s+/', strval(
            $server->httpRequest->getHeader('Authorization')
        ), 2, PREG_SPLIT_NO_EMPTY);
        $authScheme = empty($authArray) ? null : strtolower($authArray[0]);
        if ($authScheme == 'gini') {
            $auth = new \Gini\Index\Auth\DAV;
            $authPlugin = new \Sabre\DAV\Auth\Plugin($auth, $realm);
            $server->addPlugin($authPlugin);
        } else {
            $digestAuth = new \Sabre\DAV\Auth\Backend\File($this->digestFile());
            $authPlugin = new \Sabre\DAV\Auth\Plugin($digestAuth, $realm);
            $server->addPlugin($authPlugin);
        }

        $aclPlugin = new \Sabre\DAVACL\Plugin();
        // $aclPlugin->principalCollectionSet = [ '@principals' ];
        // $aclPlugin->defaultUsernamePath = '@principals';
        $server->addPlugin($aclPlugin);
        
        $server->on('afterWriteContent', [$this, '_fileModified']);
        $server->on('afterCreateFile', [$this, '_fileModified']);

        $server->on('afterUnbind', [$this, '_fileUnbind']);

        // All we need to do now, is to fire up the server
        $server->exec();
    }
}
