<?php
$counts = getCounts($pdo);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>龙腾云个人主页后台管理</title>
    <style>
        :root {
            --primary-color: #409EFF;
            --success-color: #67C23A;
            --danger-color: #F56C6C;
            --bg-color: #f8f9fa;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: var(--bg-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            padding: 24px;
        }

        .nav-tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 12px 24px;
            text-decoration: none;
            color: #666;
            border-radius: 4px 4px 0 0;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .nav-link.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            font-weight: 500;
        }

        .nav-link:hover {
            background-color: #f8f9fa;
        }

        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-width: 300px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #fafafa;
            font-weight: 500;
        }

        tr:hover {
            background-color: #fafafa;
        }

        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            margin: 2px;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .count-badge {
            background: #eee;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.8em;
            margin-left: 6px;
        }

        .empty-msg {
            text-align: center;
            color: #999;
            padding: 40px 0;
        }

        .word-management {
            margin-top: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
        }

        .word-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .word-form input[type="text"] {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-width: 300px;
        }

        .word-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .word-list li:last-child {
            border-bottom: none;
        }

        .search-info {
            margin-bottom: 15px;
            color: #666;
        }

        .search-info a {
            color: var(--primary-color);
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-tabs">
            <a href="pending_links.php" class="nav-link <?= $current_page === 'pending_links.php' ? 'active' : '' ?>">
                待审友链
                <span class="count-badge"><?= $counts['pending_links'] ?></span>
            </a>
            <a href="approved_links.php" class="nav-link <?= $current_page === 'approved_links.php' ? 'active' : '' ?>">
                已过友链
                <span class="count-badge"><?= $counts['approved_links'] ?></span>
            </a>
            <a href="pending_messages.php" class="nav-link <?= $current_page === 'pending_messages.php' ? 'active' : '' ?>">
                待审留言
                <span class="count-badge"><?= $counts['pending_messages'] ?></span>
            </a>
            <a href="approved_messages.php" class="nav-link <?= $current_page === 'approved_messages.php' ? 'active' : '' ?>">
                已过留言
                <span class="count-badge"><?= $counts['approved_messages'] ?></span>
            </a>
            <a href="forbidden.php" class="nav-link <?= $current_page === 'forbidden.php' ? 'active' : '' ?>">
                违禁词
                <span class="count-badge"><?= $counts['forbidden'] ?></span>
            </a>
        </div>