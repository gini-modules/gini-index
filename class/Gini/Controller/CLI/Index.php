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

        $rootPath = GiniIndex::modulePath();
        if (!is_dir($rootPath)) {
            echo "错误: 根目录不存在\n";
            return;
        }

        $totalIndexInfo = [];

        // 扫描所有模块目录
        foreach (glob($rootPath . '/*', GLOB_ONLYDIR) as $modulePath) {
            $module = basename($modulePath);
            // 忽略以 . 开头的目录
            if ($module[0] === '.') {
                continue;
            }
            echo "处理模块: {$module}\n";

            $moduleIndexInfo = [];

            // 扫描模块目录下的 tgz 文件
            foreach (glob($modulePath . '/*.tgz') as $tgzFile) {
                $version = basename($tgzFile, '.tgz');
                // 忽略以 . 开头的文件
                if ($version[0] === '.') {
                    continue;
                }
                echo "  - 处理版本: {$version}\n";

                // 从 tgz 文件中提取 gini.json 信息
                $info = json_decode(`tar -zxOf $tgzFile gini.json`, true);
                if (!$info) {
                    echo "    警告: 无法从 {$tgzFile} 中提取 gini.json 信息\n";
                    continue;
                }

                $moduleIndexInfo[$version] = $info;
            }

            // 更新模块的 index.json
            $moduleIndexPath = $modulePath . '/index.json';
            if (!empty($moduleIndexInfo)) {
                File::ensureDir(dirname($moduleIndexPath));
                file_put_contents(
                    $moduleIndexPath,
                    json_encode($moduleIndexInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    LOCK_EX
                );
                echo "  - 已更新 {$module}/index.json\n";
            } else {
                // 如果模块下没有 tgz 文件，删除 index.json
                if (file_exists($moduleIndexPath)) {
                    unlink($moduleIndexPath);
                    echo "  - 已删除空的 {$module}/index.json\n";
                }
            }

            if (!empty($moduleIndexInfo)) {
                $totalIndexInfo[$module] = $moduleIndexInfo;
            }
        }

        // 更新全局的 index.json
        $totalIndexPath = $rootPath . '/index.json';
        if (!empty($totalIndexInfo)) {
            file_put_contents(
                $totalIndexPath,
                json_encode($totalIndexInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
            echo "已更新全局 index.json\n";
        } else {
            // 如果没有模块，删除全局 index.json
            if (file_exists($totalIndexPath)) {
                unlink($totalIndexPath);
                echo "已删除空的全局 index.json\n";
            }
        }

        echo "同步完成\n";
    }
}
