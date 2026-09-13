<?php

pm_Scheduler::getInstance()->removeAllTasks();

$rootDir = dirname(__DIR__);
foreach (array('var/cache', 'var/tmp') as $relativeDir) {
    $dir = $rootDir . '/' . $relativeDir;
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
            continue;
        }

        @unlink($item->getPathname());
    }
}
