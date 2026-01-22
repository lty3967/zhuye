<?php
// 定义JSON文件列表
$files = ['a.json', 'b.json', 'c.json', 'd.json', 'e.json', 'f.json', 'g.json', 'h.json', 'i.json', 'j.json'];

// 随机选择一个文件
$selectedFile = $files[array_rand($files)];

// 读取选中的文件内容
$jsonData = file_get_contents($selectedFile);

// 将JSON字符串转换为数组
$dataArray = json_decode($jsonData, true);

// 随机选择一条内容
$randomItem = $dataArray[array_rand($dataArray)];

// 获取请求参数
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'json';

// 设置响应头并处理不同格式
switch ($format) {
    case 'json':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($randomItem, JSON_UNESCAPED_UNICODE);
        break;
    case 'js':
        header('Content-Type: application/javascript; charset=utf-8');
        echo 'var id = ' . json_encode($randomItem['id'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var uuid = ' . json_encode($randomItem['uuid'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var hitokoto = ' . json_encode($randomItem['hitokoto'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var type = ' . json_encode($randomItem['type'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var from = ' . json_encode($randomItem['from'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var from_who = ' . json_encode($randomItem['from_who'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var creator = ' . json_encode($randomItem['creator'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var creator_uid = ' . json_encode($randomItem['creator_uid'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var reviewer = ' . json_encode($randomItem['reviewer'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var commit_from = ' . json_encode($randomItem['commit_from'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var created_at = ' . json_encode($randomItem['created_at'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo 'var length = ' . json_encode($randomItem['length'], JSON_UNESCAPED_UNICODE) . ';' . "\n";
        break;
    case 'text':
        header('Content-Type: text/plain; charset=utf-8');
        echo "ID: " . $randomItem['id'] . "\n";
        echo "UUID: " . $randomItem['uuid'] . "\n";
        echo "Hitokoto: " . $randomItem['hitokoto'] . "\n";
        echo "Type: " . $randomItem['type'] . "\n";
        echo "From: " . $randomItem['from'] . "\n";
        echo "From Who: " . (is_null($randomItem['from_who']) ? 'null' : $randomItem['from_who']) . "\n";
        echo "Creator: " . $randomItem['creator'] . "\n";
        echo "Creator UID: " . $randomItem['creator_uid'] . "\n";
        echo "Reviewer: " . $randomItem['reviewer'] . "\n";
        echo "Commit From: " . $randomItem['commit_from'] . "\n";
        echo "Created At: " . $randomItem['created_at'] . "\n";
        echo "Length: " . $randomItem['length'] . "\n";
        break;
    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Invalid format'], JSON_UNESCAPED_UNICODE);
        break;
}
?>