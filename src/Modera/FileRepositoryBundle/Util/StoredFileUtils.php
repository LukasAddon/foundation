<?php

namespace Modera\FileRepositoryBundle\Util;

/**
 * @copyright 2015 Modera Foundation
 */
class StoredFileUtils
{
    public static function formatFileSize(int $size): string
    {
        $power = $size > 0 ? \floor(\log($size, 1024)) : 0;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $index = (int) $power;

        return \number_format($size / \pow(1024, $power), 2, '.', ',').' '.$units[$index];
    }

    final private function __construct()
    {
    }
}
