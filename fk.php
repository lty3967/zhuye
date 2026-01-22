<?php

// 定义文件路径
$stats_file = 'stats.dat';

// 设置超时时间（单位：秒）
$timeout = 30;

// 获取当前时间戳
$current_time = time();

// 初始化统计数据
$stats = [
    'online' => [],
    'today' => 0,
    'yesterday' => 0,
    'total' => 0,
    'last_update_date' => ''
];

// 从文件中读取统计数据
if (file_exists($stats_file)) {
    $fp = fopen($stats_file, 'c+');
    if (flock($fp, LOCK_EX)) { // 获取排他锁
        $stats = unserialize(fread($fp, filesize($stats_file)));
        flock($fp, LOCK_UN); // 释放锁
    }
    fclose($fp);
}

// 检查是否需要更新昨日访问人数
$current_date = date('Ymd');
if ($current_date != $stats['last_update_date']) {
    $stats['yesterday'] = $stats['today'];
    $stats['today'] = 0;
    $stats['last_update_date'] = $current_date;
}

// 更新在线人数
$ip = $_SERVER['REMOTE_ADDR'];
if (!isset($stats['online'][$ip]) || ($current_time - $stats['online'][$ip] > $timeout)) {
    $stats['online'][$ip] = $current_time;
    $stats['today']++;
    $stats['total']++;
}

// 清理超时的在线记录
foreach ($stats['online'] as $key => $value) {
    if ($current_time - $value > $timeout) {
        unset($stats['online'][$key]);
    }
}

// 将统计数据写回文件
$fp = fopen($stats_file, 'c+');
if (flock($fp, LOCK_EX)) { // 获取排他锁
    ftruncate($fp, 0); // 清空文件内容
    rewind($fp); // 将文件指针移动到文件开头
    fwrite($fp, serialize($stats));
    flock($fp, LOCK_UN); // 释放锁
}
fclose($fp);

// 定义一个函数来获取统计数据
function get_stats() {
    global $stats;
    return [
        'online' => count($stats['online']),
        'today' => $stats['today'],
        'yesterday' => $stats['yesterday'],
        'total' => $stats['total']
    ];
}

?>