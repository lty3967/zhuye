<?php $hitokoto = file_get_contents('https://www.ltywl.top/yiyan/api.php'); 
// 安装检测
$install_dir = __DIR__ . '/install';
$install_lock = __DIR__ . '/config/install.lock';

// 检查是否已安装
if (!file_exists($install_lock)) {
    // 检查是否有install目录
    if (is_dir($install_dir) && file_exists($install_dir . '/install.php')) {
        header('Location: /install/install.php');
        exit;
    } else {
        // 如果config/config.php不存在，也视为未安装
        if (!file_exists(__DIR__ . '/config/config.php')) {
            die('<h2 style="text-align:center;margin-top:100px;">系统未安装</h2><p style="text-align:center;">请上传install目录到网站根目录，然后访问<a href="/install/">/install/</a>进行安装</p>');
        }
    }
}

// 如果已安装，继续加载配置文件
include 'config/config.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>

    <meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=0, width=device-width">

    <meta itemprop="name" content="龙毅 | 一个喜欢瞎折腾的博主 | 每天努力奋斗">

    <meta itemprop="image" content="龙毅 | 一个喜欢瞎折腾的博主 | 每天努力奋斗">

    <meta name="keywords" content="每天努力奋斗"> 

    <meta name="description" itemprop="description" content="龙毅 | 一个喜欢瞎折腾的博主 | 每天努力奋斗">


    <title>每天努力奋斗 | 将来你会感谢现在奋斗的你</title>

    <link rel="shortcut icon" href="/assets/img/favicon.ico">

    <link rel="stylesheet" href="/assets/cucss/style.css" media="screen" type="text/css">

    <link rel="stylesheet" href="/assets/cucss/demo.css" type="text/css">

    <script src="/assets/cujs/script.js" type="text/javascript"></script>
    
<meta name="template" content="Amore">
<style>
        .zuobiao i {
            line-height: 1.8;
            margin-right: 6px;
            vertical-align: middle;
            background-image: url(/assets/img/zuobiao.svg);
            background-size: 100%;
            width: 16px;
            height: 16px;
            display: inline-block;
            margin-top: -2px;
        }
    </style>

</head>

<body>
        <script>
    /***
 * 愚人节彩蛋 - 你屏幕上有根毛
 * 出处：https://www.baidu.com/s?ie=UTF-8&wd=%E6%84%9A%E4%BA%BA%E8%8A%82
 * 整理：mengkun https://mkblog.cn/
 */
!function() {
    var bottom = Math.floor(60 * Math.random()),
        right = Math.floor(50 * Math.random()),
        rotate = Math.floor(360 * Math.random());
    var foolsEgg = document.createElement("img");
    foolsEgg.src = "/assets/img/mao.png";
    foolsEgg.style.position = "fixed"; 
    foolsEgg.style.bottom = "".concat(bottom, "%");
    foolsEgg.style.right = "".concat(right, "%"); 
    foolsEgg.style.zIndex = "9999"; 
    foolsEgg.style.pointerEvents = "none";
    foolsEgg.style.width = "40%";
    foolsEgg.style.maxWidth = "190px";
    foolsEgg.style.transform = "".concat("rotate(", rotate, "deg)"); 
    document.body.append(foolsEgg);
} ();
    </script>
    
<div class="wrapper">
<header>
    <nav class="navbar" id="mobile-toggle-theme">
        <div class="container"></div>
    </nav>
</header> 

    <div class="main">

        <div class="container">
            <div class="intro" style=" line-height: 50px">
                <img src="/assets/img/logo.png" class="logo">
                <div class="description">
                    <p>每天努力奋斗 | 将来你会感谢现在奋斗的你</p>

                </div>


                <div class="zuobiao">
                    <i class="ico_map"></i>中国 · 广东
                    <span style="margin-left: 30px">
                            <input id="switch_default" type="checkbox" class="switch_default">
                            <label for="switch_default" class="toggleBtn"></label></span>
                </div>
                
                <div class="menu navbar-right links">

                    <!-- 获取页面列表 -->

                    <a class="menu-item" href="/">
                    我的主页</a> · 
                    
                     <a class="menu-item" href="/liuyan.html">
                    留言板块</a> · 
                    
                    <a href="/friends.html">

                      友链申请                        
                    </a> · 

                    
                    <a href="https://www.770a.cn">

                      我的博客                        
                    </a>

                    




                </div>
                <br>
                <div style=" line-height: 20px;font-size: 9pt;">
                    <p>" <?php echo $hitokoto; ?> "</p>
                        <p style="margin-left: 8rem;font-size: 8pt;"><small>......</small></p>

                </div>
            </div>

        </div>

</div>

<footer id="footer" class="footer">
    <p><?php
    // 引入 fk.php 文件
    require 'fk.php';
    
    // 获取统计数据
    $stats = get_stats();
    
    // 显示统计数据
    echo "当前在线人数: <strong><font color='red'>" . $stats['online'] . "</font></strong> - ";
    echo "今日访问人数: <strong><font color='red'>" . $stats['today'] . "</font></strong> - ";
    echo "昨日访问人数: <strong><font color='red'>" . $stats['yesterday'] . "</font></strong> - ";
    echo "总访问人数: <strong><font color='red'>" . $stats['total'] . "</font></strong>";
    ?></p>
        <img style="width:32px;height:32px;margin-bottom:0px" src="/assets/img/icp.svg"><a href="http://www.beian.miit.gov.cn/" rel="nofollow" target="_blank">粤ICP备2020124673号-1</a> | 
  <img style="width:32px;height:32px;margin-bottom:0px" src="https://www.ltywl.top/assets/img/beian.png"><a href="https://beian.mps.gov.cn/#/query/webSearch?code=44010502002900" rel="noreferrer" target="_blank">粤公网安备44010502002900</a>
  
  <!-- 弹窗代码开始 -->
<script src="/assets/cujs/sweetalert.min.js"></script>
<script>
 
swal('每天努力奋斗','\n\n龙毅 | 一个喜欢瞎折腾的博主 | 每天努力奋斗\n\n如有任何问题请联系站长QQ:825703967','success'); function AddFavorite(title, url) {
 
  try {
 
      window.external.addFavorite(url, title);
 
  }
 
catch (e) {
 
     try {
 
       window.sidebar.addPanel(title, url,);
 
    }
 
     catch (e) {
 
         alert("抱歉，您所使用的浏览器无法完成此操作。");
 
     }
 
  }
 
}
 
</script>
<!-- 弹窗代码结束 -->
</footer>

</div>

</body></html>