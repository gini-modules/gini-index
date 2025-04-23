<?php

namespace Gini\Module;

class GiniIndex extends Prototype
{
    public static function modulePath($path = null)
    {
        $modulePath = \Gini\Config::get('dav.root') ?: APP_PATH . '/' . DATA_DIR . '/modules';
        return $modulePath . '/' . ltrim($path, '/');
    }

    public static function aclConfig($aclConf = null)
    {
        $file = APP_PATH.'/'.DATA_DIR.'/acl.yml';
        if ($aclConf === null) {
            return (array) @yaml_parse_file($file);
        }
        if (is_array($aclConf)) {
            asort($aclConf);
            file_put_contents($file, yaml_emit($aclConf, YAML_UTF8_ENCODING));
        }
        return $aclConf;
    }

    public static function setup()
    {
        date_default_timezone_set(\Gini\Config::get('system.timezone') ?: 'Asia/Shanghai');
        \Gini\URI::setup();
    }
}
