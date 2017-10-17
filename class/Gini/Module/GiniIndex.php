<?php

namespace Gini\Module;

class GiniIndex
{
    public static function modulePath($path = null)
    {
        $modulePath = \Gini\Config::get('dav.root') ?: APP_PATH . '/' . DATA_DIR . '/modules';
        return $modulePath . '/' . ltrim($path, '/');
    }
}
