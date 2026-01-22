<?php
function generateSearchCondition($search, $fields, &$params, $prefix = 'search') {
    if (empty($search) || empty($fields)) return '';
    
    $conditions = [];
    $i = 0;
    foreach ($fields as $field) {
        $paramName = ":$prefix$i";
        $conditions[] = "$field LIKE $paramName";
        $params[$paramName] = "%$search%";
        $i++;
    }
    return '('.implode(' OR ', $conditions). ')';
}

function getCounts($pdo) {
    $table_prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : '';
    
    $counts = [];
    
    try {
        $counts['pending_links'] = $pdo->query("
            SELECT COUNT(*) 
            FROM " . $table_prefix . "friend_links 
            WHERE status = 0
        ")->fetchColumn();
        
        $counts['approved_links'] = $pdo->query("
            SELECT COUNT(*) 
            FROM " . $table_prefix . "friend_links 
            WHERE status = 1
        ")->fetchColumn();
        
        $counts['pending_messages'] = $pdo->query("
            SELECT COUNT(*) 
            FROM " . $table_prefix . "messages 
            WHERE status = 0
        ")->fetchColumn();
        
        $counts['approved_messages'] = $pdo->query("
            SELECT COUNT(*) 
            FROM " . $table_prefix . "messages 
            WHERE status = 1
        ")->fetchColumn();
        
        $counts['forbidden'] = $pdo->query("
            SELECT COUNT(*) 
            FROM " . $table_prefix . "forbidden_words
        ")->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("统计计数失败: " . $e->getMessage());
        // 返回默认值
        return [
            'pending_links' => 0,
            'approved_links' => 0,
            'pending_messages' => 0,
            'approved_messages' => 0,
            'forbidden' => 0
        ];
    }
    
    return $counts;
}