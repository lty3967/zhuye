// 移动端侧边栏控制脚本

document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.overlay');
    const mainContent = document.querySelector('.main-content');
    
    // 检查是否在移动设备上
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    // 检查是否在平板设备上
    function isTablet() {
        return window.innerWidth <= 1024 && window.innerWidth > 768;
    }
    
    // 切换侧边栏显示
    function toggleSidebar() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        
        // 更新菜单按钮图标
        if (mobileMenuToggle) {
            const icon = mobileMenuToggle.querySelector('i');
            if (icon) {
                if (sidebar.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                } else {
                    icon.className = 'fas fa-bars';
                }
            }
        }
    }
    
    // 关闭侧边栏
    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        
        // 恢复菜单按钮图标
        if (mobileMenuToggle) {
            const icon = mobileMenuToggle.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-bars';
            }
        }
    }
    
    // 检查必要的元素是否存在
    if (!sidebar) {
        console.warn('侧边栏元素 (.sidebar) 未找到');
        return;
    }
    
    if (!overlay) {
        console.warn('遮罩层元素 (.overlay) 未找到');
        return;
    }
    
    // 点击菜单按钮切换侧边栏
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
    } else {
        console.warn('移动端菜单按钮 (.mobile-menu-toggle) 未找到');
    }
    
    // 点击遮罩层关闭侧边栏
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // 点击侧边栏链接在移动端自动关闭侧边栏
    const menuItems = document.querySelectorAll('.sidebar .menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            if (isMobile()) {
                closeSidebar();
            }
        });
    });
    
    // 窗口大小改变时调整布局
    window.addEventListener('resize', function() {
        if (!isMobile()) {
            closeSidebar();
        }
    });
    
    // ESC键关闭侧边栏
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isMobile() && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });
    
    // 点击主内容区域关闭侧边栏（移动端）
    if (mainContent) {
        mainContent.addEventListener('click', function() {
            if (isMobile() && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
    }
    
    // 表格响应式处理
    const tables = document.querySelectorAll('.data-table');
    tables.forEach(table => {
        if (table.scrollWidth > table.clientWidth) {
            // 为可滚动的表格添加提示
            const hasScrollHint = table.querySelector('.scroll-hint');
            if (!hasScrollHint && (isMobile() || isTablet())) {
                const hint = document.createElement('div');
                hint.className = 'scroll-hint';
                hint.innerHTML = '<i class="fas fa-arrows-left-right"></i> 左右滑动查看更多';
                hint.style.cssText = 'text-align:center;padding:8px;color:#666;font-size:12px;background:#f5f5f5;border-top:1px solid #ddd;';
                table.parentNode.insertBefore(hint, table.nextSibling);
            }
        }
    });
    
    // 优化移动端输入框
    if (isMobile()) {
        // 输入框获得焦点时自动放大
        const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.fontSize = '16px'; // 防止iOS自动缩放
            });
            
            input.addEventListener('blur', function() {
                this.style.fontSize = '';
            });
        });
    }
    
    // 页面加载时初始化移动端状态
    if (isMobile()) {
        // 确保侧边栏初始状态是关闭的
        closeSidebar();
    }
    
    // 阻止侧边栏点击事件冒泡
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});