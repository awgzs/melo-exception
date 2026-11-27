<?php

declare(strict_types=1);

namespace Melo\ExceptionHandler;

/**
 * webman 插件安装器：安装/卸载时复制或删除配置文件。
 */
class Install
{
    public const WEBMAN_PLUGIN = true;

    /** @var array 包内配置目录 => 项目配置目录 */
    protected static array $pathRelation = [
        'config/plugin/melo/exception-handler' => 'config/plugin/melo/exception-handler',
    ];

    public static function install(): void
    {
        foreach (static::$pathRelation as $source => $dest) {
            $parent = dirname(base_path() . '/' . $dest);
            if (!is_dir($parent)) {
                mkdir($parent, 0777, true);
            }
            copy_dir(__DIR__ . '/' . $source, base_path() . '/' . $dest);
        }
    }

    public static function uninstall(): void
    {
        foreach (static::$pathRelation as $dest) {
            $path = base_path() . '/' . $dest;
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            remove_dir($path);
        }
    }
}
