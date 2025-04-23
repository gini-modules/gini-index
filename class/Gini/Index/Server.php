<?php

namespace Gini\Index;

use Sabre\DAV\Server as DAVServer;
use Sabre\DAV\Locks\Backend\File as LocksBackend;
use Sabre\DAV\Locks\Plugin as LocksPlugin;
use Sabre\DAV\Auth\Backend\File as AuthBackend;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAVACL\Plugin as AclPlugin;
use Gini\File;
use Gini\Config;
use Gini\Logger;
use Gini\Module\GiniIndex;

class Server
{
    private $server;

    public function __construct()
    {
        $rootPath = $this->modulePath();
        File::ensureDir($rootPath);
        $rootDirectory = new \Gini\Index\Directory($rootPath, true);

        // The server object is responsible for making sense out of the WebDAV protocol
        $server = new DAVServer($rootDirectory);
        
        // If your server is not on your webroot, make sure the following line has the
        // correct information
        // $server->setBaseUri('/url/to/server.php');

        // The lock manager is reponsible for making sure users don't overwrite
        // each others changes.
        $lockBackend = new LocksBackend($this->lockFile());
        $server->addPlugin(new LocksPlugin($lockBackend));

        // This ensures that we get a pretty index in the browser, but it is
        // optional.
        $browserPlugin = new Browser();
        $server->addPlugin($browserPlugin);

        $realm = Config::get('dav.auth')['realm'];

        // Adding the plugin to the server
        $authArray = preg_split('/\s+/', strval(
            $server->httpRequest->getHeader('Authorization')
        ), 2, PREG_SPLIT_NO_EMPTY);
        $authScheme = empty($authArray) ? null : strtolower($authArray[0]);
        if ($authScheme == 'gini') {
            $authBackend = new Auth\DAV();
        } else {
            $authBackend = new AuthBackend($this->digestFile());
            $authBackend->setRealm($realm);
        }

        $authPlugin = new AuthPlugin($authBackend);
        $server->addPlugin($authPlugin);

        $aclPlugin = new AclPlugin();
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
        return GiniIndex::modulePath($path);
    }

    public function writeInfoFile($filename, $data)
    {
        return file_put_contents($filename, $data, LOCK_EX);
    }

    public function fileModified($path)
    {
        if (!preg_match('`^([\w-]+)/([\w\-\.]+)\.tgz$`', $path, $parts)) {
            return;
        }

        $module = $parts[1];
        $version = $parts[2];

        $fullPath = escapeshellcmd($this->modulePath($path));
        $info = json_decode(`tar -zxOf $fullPath gini.json`, true);

        $authPlugin = $this->server->getPlugin('auth');
        Logger::of('gini-index-log')->info('{user} 发布了 {module.name}({module.id}/{module.version})', [
            'user' => $authPlugin->getCurrentUser(),
            'module' => [
                'id' => $module,
                'name' => $info['title'],
                'version' => $info['version']
            ],
            'operation' => 'publish'
        ]);

        $indexPath = $this->modulePath($module.'/index.json');
        $indexInfo = @json_decode(file_get_contents($indexPath), true);
        if (!$indexInfo) {
            $indexInfo = [];
        }

        $indexInfo[$version] = $info;
        $this->writeInfoFile($indexPath, json_encode($indexInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $totalIndexPath = $this->modulePath('index.json');
        $totalIndexInfo = @json_decode(file_get_contents($totalIndexPath), true);
        if (!$totalIndexInfo) {
            $totalIndexInfo = [];
        }

        $totalIndexInfo[$module] = $indexInfo;
        $this->writeInfoFile($totalIndexPath, json_encode($totalIndexInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function fileBeforeBind($path)
    {
        if (!preg_match('`^([\w-]+)$`', $path, $parts)) {
            return;
        }

        $module = $parts[1];
        $aclConf = GiniIndex::aclConfig();

        $authPlugin = $this->server->getPlugin('auth');
        if (!isset($acl[$module])) {
            $aclConf[$module] = [
                $authPlugin->getCurrentUser() => 'rw'
            ];
        }

        GiniIndex::aclConfig($aclConf);
    }

    public function fileAfterUnbind($path)
    {
        if (!preg_match('`^([\w-]+)/([\w\-\.]+)\.tgz$`', $path, $parts)) {
            return;
        }

        $module = $parts[1];
        $version = $parts[2];

        $indexPath = $this->modulePath($module.'/index.json');
        $indexInfo = @json_decode(file_get_contents($indexPath), true);
        if (is_array($indexInfo)) {
            unset($indexInfo[$version]);
            $this->writeInfoFile($indexPath, json_encode($indexInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $totalIndexPath = $this->modulePath('index.json');
        $totalIndexInfo = @json_decode(file_get_contents($totalIndexPath), true);
        if (is_array($totalIndexInfo)) {
            unset($totalIndexInfo[$module][$version]);
            $this->writeInfoFile($totalIndexPath, json_encode($totalIndexInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
