<!-- 移动端菜单切换按钮 -->
<button class="mobile-menu-toggle" aria-label="切换菜单">
    <i class="fas fa-bars"></i>
</button>

<!-- 遮罩层 -->
<div class="overlay"></div>

<nav class="sidebar" role="navigation" aria-label="主菜单">
    <div class="logo">
        <i class="fas fa-cube"></i> 管理后台
    </div>
    
    <div class="menu-group">
        <div class="menu-title">首页</div>
        <a href="index.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> 控制面板
        </a>
    </div>
    <div class="menu-group">
        <div class="menu-title">友情链接管理</div>
        <a href="apply.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'apply.php' ? 'active' : '' ?>">
            <i class="fas fa-hourglass-half"></i> 待审核友链
        </a>
        <a href="links.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'links.php' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> 已审核友链
        </a>
    </div>
    <div class="menu-group">
        <div class="menu-title">留言管理</div>
        <a href="add_messages.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'add_messages.php' ? 'active' : '' ?>">
            <i class="fas fa-comment-dots"></i> 待审核留言
        </a>
        <a href="messages.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> 已审核留言
        </a>
    </div>
    <div class="menu-group">
        <div class="menu-title">系统设置</div>
        <a href="blacklist.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'blacklist.php' ? 'active' : '' ?>">
            <i class="fas fa-ban"></i> 违禁词管理
        </a>
        <a href="email.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'email.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> 发信功能配置
        </a>
    </div>
    
    <div class="menu-group">
        <div class="menu-title">账户管理</div>
        <a href="user.php" class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'user.php' ? 'active' : '' ?>">
            <i class="fas fa-user-cog"></i> 管理员信息
        </a>
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i> 退出登录
        </a>
    </div>
</nav>