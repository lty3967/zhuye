<?php
// 定义JSON文件列表
$files = [
    'a.json', 'b.json', 'c.json', 'd.json',
    'e.json', 'f.json', 'g.json', 'h.json',
    'i.json', 'j.json', 'k.json', 'l.json'
];

// 随机选择一个文件
$randomFile = $files[array_rand($files)];

// 读取并解析JSON文件
$jsonData = file_get_contents($randomFile);
$hitokotoArray = json_decode($jsonData, true);

// 检查是否成功解析JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error parsing JSON from file: $randomFile");
}

// 随机选择一条hitokoto和creator
$randomIndex = array_rand($hitokotoArray);
$randomHitokoto = $hitokotoArray[$randomIndex]['hitokoto'];
$randomCreator = $hitokotoArray[$randomIndex]['creator'];

// 输出结果
echo "$randomHitokoto";
?>