<?php

namespace Gini\Index;

class Server
{
    private $server;

    public function __construct()
    {
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
        $browserPlugin = new Browser();
        $server->addPlugin($browserPlugin);

        $realm = \Gini\Config::get('dav.auth')['realm'];

        // Adding the plugin to the server
        $authArray = preg_split('/\s+/', strval(
            $server->httpRequest->getHeader('Authorization')
        ), 2, PREG_SPLIT_NO_EMPTY);
        $authScheme = empty($authArray) ? null : strtolower($authArray[0]);
        if ($authScheme == 'gini') {
            $authBackend = new Auth\DAV;
        } else {
            $authBackend = new \Sabre\DAV\Auth\Backend\File($this->digestFile());
        }

        $authPlugin = new \Sabre\DAV\Auth\Plugin($authBackend, $realm);
        $server->addPlugin($authPlugin);

        $aclPlugin = new \Sabre\DAVACL\Plugin();
        $server->addPlugin($aclPlugin);
        
        $server->on('afterWriteContent', [$this, 'fileModified']);
        $server->on('afterCreateFile', [$this, 'fileModified']);

        $server->on('beforeBind', [$this, 'fileBeforeBind']);
        $server->on('afterUnbind', [$this, 'fileAfterUnbind']);

        $this->server = $server;
    }

    public function execute()
    {
        $this->server->exec();
    }

    public function modulePath($path=null)
    {
        return \Gini\Module\GiniIndex::modulePath($path);
    }

    public function writeInfoFile($filename, $data)
    {
        return file_put_contents($filename, $data, LOCK_EX);
    }

    public function fileModified($path)
    {
        if (!preg_match('`^([\w-]+)/([\w-.]+)\.tgz$`', $path, $parts)) {
            return;
        }

        $module = $parts[1];
        $version = $parts[2];

        $fullPath = escapeshellcmd($this->modulePath($path));
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

    public function fileBeforeBind($path)
    {
        if (!preg_match('`^([\w-]+)$`', $path, $parts)) {
            return;
        }

        $module = $parts[1];
        $aclConf = \Gini\Module\GiniIndex::aclConfig();

        $authPlugin = $this->server->getPlugin('auth');
        if (!isset($acl[$module])) {
            $aclConf[$module] = [
                $authPlugin->getCurrentUser() => 'rw'
            ];
        }

        \Gini\Module\GiniIndex::aclConfig($aclConf);
    }

    public function fileAfterUnbind($path)
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

    public function lockFile()
    {
        return sys_get_temp_dir().'/gini-index.lock';
    }

    public function digestFile()
    {
        return APP_PATH.'/'.DATA_DIR.'/digest';
    }
}
