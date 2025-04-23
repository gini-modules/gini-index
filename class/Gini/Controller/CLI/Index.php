<?php

namespace Gini\Controller\CLI;

use \Gini\Controller\CLI;
use Gini\Module\GiniIndex;
use Gini\File;

class Index extends CLI
{
    public function actionSync()
    {
        echo "开始同步 index.json...\n";

        $rootPath = GiniIndex::modulePath('');
        if (!is_dir($rootPath)) {
            echo "错误: 根目录不存在\n";
            return;
        }

        $totalIndexInfo = [];

        // 扫描所有模块目录
        foreach (glob($rootPath . '/*', GLOB_ONLYDIR) as $moduleDir) {
            $moduleName = basename($moduleDir);
            $moduleIndexInfo = [];

            // 检查每个 .tgz 文件
            foreach (glob($moduleDir . '/*.tgz') as $tgzFile) {
                $version = basename($tgzFile, '.tgz');
                
                // 提取 gini.json 信息
                $fullPath = escapeshellcmd($tgzFile);
                $info = json_decode(`tar -zxOf $fullPath gini.json`, true);
                if ($info) {
                    $moduleIndexInfo[$version] = $info;
                }
            }

            // 更新或删除模块的 index.json
            $moduleIndexPath = $moduleDir . '/index.json';
            if (!empty($moduleIndexInfo)) {
                file_put_contents($moduleIndexPath, json_encode($moduleIndexInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $totalIndexInfo[$moduleName] = $moduleIndexInfo;
                echo "已更新 {$moduleName} 的 index.json\n";
            } else {
                if (file_exists($moduleIndexPath)) {
                    unlink($moduleIndexPath);
                    echo "已删除 {$moduleName} 的空 index.json\n";
                }
            }
        }

        // 更新或删除全局 index.json
        $totalIndexPath = $rootPath . '/index.json';
        if (!empty($totalIndexInfo)) {
            file_put_contents($totalIndexPath, json_encode($totalIndexInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "已更新全局 index.json\n";
        } else {
            if (file_exists($totalIndexPath)) {
                unlink($totalIndexPath);
                echo "已删除空的全局 index.json\n";
            }
        }

        echo "同步完成\n";
    }
}
