<!DOCTYPE html>
<?php
	$htmlclasses = "";
	$argon_page_layout = get_option('argon_page_layout');
	if ($argon_page_layout == "single"){
		$htmlclasses .= "single-column ";
	}
	if ($argon_page_layout == "triple"){
		$htmlclasses .= "triple-column ";
	}
	if ($argon_page_layout == "double-reverse"){
		$htmlclasses .= "double-column-reverse ";
	}
	if (get_option('argon_enable_immersion_color') == "true"){
		$htmlclasses .= "immersion-color ";
	}
	if (get_option('argon_enable_amoled_dark') == "true"){
		$htmlclasses .= "amoled-dark ";
	}
	if (get_option('argon_card_shadow') == 'big'){
		$htmlclasses .= 'use-big-shadow ';
	}
	if (get_option('argon_font') == 'serif'){
		$htmlclasses .= 'use-serif ';
	}
	if (get_option('argon_disable_codeblock_style') == 'true'){
		$htmlclasses .= 'disable-codeblock-style ';
	}
	if (get_option('argon_enable_headroom') == 'absolute'){
		$htmlclasses .= 'navbar-absolute ';
	}
	$banner_size = get_option('argon_banner_size', 'full');
	if ($banner_size != 'full'){
		if ($banner_size == 'mini'){
			$htmlclasses .= 'banner-mini ';
		}else if ($banner_size == 'hide'){
			$htmlclasses .= 'no-banner ';
		}else if ($banner_size == 'fullscreen'){
			$htmlclasses .= 'banner-as-cover ';
		}
	}
	if (get_option('argon_toolbar_blur', 'false') == 'true'){
		$htmlclasses .= 'toolbar-blur ';
	}
	$htmlclasses .= get_option('argon_article_header_style', 'article-header-style-default') . ' ';
	if(strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false && strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') === false){
		$htmlclasses .= ' using-safari';
	}
?>
<html <?php language_attributes(); ?> class="no-js <?php echo $htmlclasses;?>">
<?php
	/* 主题色 / 卡片圆角：服务端只输出站点默认值，不按 Cookie 分支。
	   原因：读取访客偏好会让 HTML 随人而异，整页缓存会把首个访客的偏好固化分发。
	   用户偏好改由 argontheme.js 从 localStorage 读取后在客户端覆盖 CSS 变量。 */
	$themecolor = get_option("argon_theme_color", "#5e72e4");
	$themecolor_origin = $themecolor;
	if (hex2gray($themecolor) < 50){
		echo '<script>document.getElementsByTagName("html")[0].classList.add("themecolor-toodark");</script>';
	}
?>
<?php
	$cardradius = get_option('argon_card_radius');
	if ($cardradius == ""){
		$cardradius = "4";
	}
	$cardradius_origin = $cardradius;
?>
<head>
	<?php /* 编码/视口声明必须紧跟 <head>：HTML 规范的编码预扫描只读取文档最前 1024 字节，
	         若 charset 落在其后，社交平台抓取器（QQ / 微信等，多不采信 HTTP 头的 charset）
	         会回退到本地默认编码（中文环境常为 GBK），使 og:title / og:description 的中文
	         变成乱码，卡片因而无法识别。故此处置于所有内联 style/script 之前。 */ ?>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php if (get_option('argon_enable_mobile_scale') != 'true'){ ?>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<?php }else{ ?>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
	<?php } ?>
	<?php /* 字体域名资源提示：仅衬线模式仍会请求 Google（Noto Serif SC）时才需要。
	         Open Sans 已改为自托管，非衬线模式下这些 preconnect/dns-prefetch 只会白白多开连接。 */ ?>
	<?php if (argon_get_option('argon_disable_googlefont') != 'true' && get_option('argon_font') == 'serif') { ?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="dns-prefetch" href="https://fonts.googleapis.com">
	<?php } ?>
	<?php /* 预加载遮罩：关键样式内联，确保首屏瞬时出现、不依赖异步 CSS，避免遮罩自身闪烁 */ ?>
	<?php if (get_option('argon_enable_preloader') != 'false') { ?>
	<style>
	/* 背景跟随主题深浅：浅色白(#fff)，深色统一用有设计的深灰(非纯黑) */
	#argon-preloader{position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--argon-preloader-bg,#fff);transition:opacity .6s ease;will-change:opacity;}
	/* 退出：遮罩整体淡出；模糊作用在内层内容(纯色遮罩模糊不可见) */
	#argon-preloader.ar--hidden,#argon-preloader.argon-preloader--hidden{opacity:0;pointer-events:none;}
	/* 仅当深浅开关真正打开(.darkmode)时用深色(纯黑)，amoled-dark 只是纯黑变体修饰，不能单独生效 */
	.darkmode{--argon-preloader-bg:#000;}
	.darkmode.amoled-dark{--argon-preloader-bg:#000;}
	.argon-preloader__inner{display:flex;flex-direction:column;align-items:center;animation:argon-preloader-in 1.2s ease both;}
	#argon-preloader.ar--hidden .argon-preloader__inner,#argon-preloader.argon-preloader--hidden .argon-preloader__inner{animation:argon-preloader-out 2s ease forwards;}
	/* 旋转圈：主题色弧 + 主题色浅轨道，浅底深底都清晰可见 */
	.argon-preloader__spinner{width:56px;height:56px;border:5px solid rgba(94,114,228,.25);border-top-color:var(--themecolor,#5e72e4);border-radius:50%;animation:argon-spin 1s linear infinite,argon-glow 2.4s ease-in-out infinite;}
	.argon-preloader__text{margin-top:18px;color:var(--themecolor,#5e72e4);font-size:15px;letter-spacing:.2em;}
	.argon-preloader__text::after{content:'';animation:argon-dots 1.8s steps(1,end) infinite;}
	@keyframes argon-spin{to{transform:rotate(360deg);}}
	/* 旋转圈外发光脉冲 */
	@keyframes argon-glow{0%,100%{box-shadow:0 0 0 0 rgba(94,114,228,0);}50%{box-shadow:0 0 16px 2px rgba(94,114,228,.4);}}
	/* 内层渐入：从深模糊浮现，渐变转浅模糊再清晰（与消失动画同款渐变模糊） */
	@keyframes argon-preloader-in{
		0%   {opacity:0;   filter:blur(16px);transform:scale(1.04);}  /* 深模糊浮现 */
		60%  {opacity:1;   filter:blur(4px); transform:scale(1.02);}  /* 渐变转浅模糊 */
		100% {opacity:1;   filter:blur(0);   transform:scale(1);}     /* 清晰 */
	}
	/* 内层渐出：先迅速加深到深模糊，再渐变回落到浅模糊并淡出（优雅的渐变模糊） */
	@keyframes argon-preloader-out{
		0%   {opacity:1;   filter:blur(0);   transform:none;}
		40%  {opacity:1;   filter:blur(16px);transform:scale(1.04);}  /* 深模糊 */
		100% {opacity:0;   filter:blur(4px); transform:scale(1.05);}  /* 渐变转浅模糊后淡出 */
	}
	/* 加载文字动态省略号 */
	@keyframes argon-dots{0%{content:'';}25%{content:'.';}50%{content:'..';}75%{content:'...';}100%{content:'';}}

	/* ============================================================
	   退场：遮罩从中心「化开」+ 内容从模糊收敛到清晰
	   ------------------------------------------------------------
	   为什么不用 clip-path 把内容「擦」出来：
	     clip-path 保留的是圆内，加在遮罩上会变成「遮罩从中心长出来盖住内容」，
	     方向正好相反；而把内容抬到遮罩之上，遮罩里转圈的模糊淡出又会被整个盖住，
	     于是既没有模糊过渡、也不连贯。
	     现在让遮罩【留在上层不动】，自己带一个「中心透明、向外不透明」的圆形遮罩，
	     半径从 0 长到 110% —— 遮罩从中心化开，转圈淡出全程可见。

	   两个数值上的坑（都实测过）：
	     · radial-gradient 里百分比圆是相对【最远角】的，写 150% 是它的 1.5 倍，
	       视觉上过渡跑到约 2/3 就完全揭示完了，后面一段看不出来。取 110% 刚好。
	     · cubic-bezier 的 y1 是「起步速度」，写 .8 是起步极快 ——
	       半径 0.8 秒就冲到 95%，剩下时间几乎静止，观感仍然「太快」。y1 取 0。
	   ============================================================ */
	@property --argon-reveal{syntax:'<percentage>';inherits:false;initial-value:0%;}
	#argon-preloader{
		--argon-reveal:0%;
		-webkit-mask-image:radial-gradient(circle at 50% 50%,transparent calc(var(--argon-reveal) - 7%),#000 var(--argon-reveal));
		mask-image:radial-gradient(circle at 50% 50%,transparent calc(var(--argon-reveal) - 7%),#000 var(--argon-reveal));
	}
	#argon-preloader.ar--hidden,#argon-preloader.argon-preloader--hidden{
		--argon-reveal:110%;
		/* opacity 必须显式压回 1：上面那条原有规则写的是 opacity:0，
		   它的过渡只有 .6s —— 遮罩会在 .6 秒内淡没，1.5s 的圆形化开还没看出形状就结束了，
		   观感就是「扩散效果缺失」（demo 里那版没有这条 opacity 规则，所以没暴露出来）。
		   这里让退场【只靠 mask 化开】，不要再叠一层淡出。 */
		opacity:1;
		transition:--argon-reveal 1.5s cubic-bezier(.55,0,.3,1);
	}
	/* ============================================================
	   模糊层（层级：圆环 → 模糊 → 博客内容）
	   ------------------------------------------------------------
	   之前是直接给 #toolbar / #content 加 filter:blur() —— 两个问题：
	     · 层级不对：模糊成了内容自身的属性，而不是夹在圆环与内容之间的一层；
	     · 不同步：内容整体均匀变清，跟圆环扩散没有空间上的对应。
	   现在改成独立的 backdrop-filter 层，夹在两者中间：

	     最上  #argon-preloader        圆环（带 mask，从中心化开）
	     中间  .argon-preloader__blur  只负责模糊它后面的东西
	     最下  #content                博客内容（本身不动、不加 filter）

	   关键点：这一层【不需要自己的 mask】。上面的遮罩是不透明的，
	   只在中心留出一个透明圆，所以模糊层天然只在「被揭示出来的那块」可见 ——
	   圆扩到哪儿，哪儿才露出模糊，天然同步。

	   顺带解决两个隐患：不再给内容加 filter，所以
	     · filter 创建包含块、导致 fixed 浮动按钮错位的风险消失；
	     · 整页反复重栅格化带来的文字抖动也消失。
	   ============================================================ */
	.argon-preloader__blur{
		position:fixed;inset:0;z-index:99998;pointer-events:none;
		/* 平时不做任何模糊，零开销 */
		-webkit-backdrop-filter:none;backdrop-filter:none;
	}
	/* 用 animation 而不是 transition：元素从首屏就在 DOM 里，
	   而动画在「元素首次匹配到规则」时一定会从头播，不依赖前一帧的样式，
	   也就天然与遮罩化开同步（两者都由 html.argon-revealing 触发、同时长同缓动）。 */
	html.argon-revealing .argon-preloader__blur{
		animation:argon-blur-out 1.5s cubic-bezier(.55,0,.3,1) forwards;
	}
	@keyframes argon-blur-out{
		0%  {-webkit-backdrop-filter:blur(8px) saturate(1.06);backdrop-filter:blur(8px) saturate(1.06);}
		60% {-webkit-backdrop-filter:blur(3px) saturate(1.02);backdrop-filter:blur(3px) saturate(1.02);}
		100%{-webkit-backdrop-filter:blur(0)   saturate(1);   backdrop-filter:blur(0)   saturate(1);}
	}
	/* 不支持 backdrop-filter 的浏览器：没有模糊层，遮罩化开照常 */
	@supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){
		.argon-preloader__blur{display:none;}
	}
	/* 不支持 @property 的老浏览器：遮罩直接淡出，不会卡住 */
	@supports not (background:paint(x)){
		html.argon-revealing #argon-preloader{opacity:0;transition:opacity .8s ease;}
	}
	@media (prefers-reduced-motion:reduce){
		#argon-preloader,#argon-preloader *,#argon-preloader.ar--hidden,
		.argon-preloader__blur,html.argon-revealing .argon-preloader__blur{
			animation-duration:.01s !important;transition-duration:.01s !important;
		}
	}
	</style>
	<noscript><style>#argon-preloader{display:none!important;}</style></noscript>
	<script>
	/* 早期(无 jQuery 依赖)依据存储/选项把深浅类加到 <html>，确保首屏预加载即跟随主题、不闪。
	   原深色脚本依赖 jQuery(合并 JS 在页尾)，首屏绘制前可能未执行，故此处先行判定。 */
	(function(){
		try{
			var html=document.documentElement;
			var auto="<?php echo (get_option('argon_darkmode_autoswitch')==''?'false':get_option('argon_darkmode_autoswitch'));?>";
			var s=sessionStorage.getItem('Argon_Enable_Dark_Mode');
			var dark=false;
			if(s==='true'){dark=true;}
			else if(s==='false'){dark=false;}
			else if(auto==='alwayson'){dark=true;}
			else if(auto==='system'){dark=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;}
			else if(auto==='time'){var h=new Date().getHours();dark=(<?php echo apply_filters('argon_darkmode_time_check','hour < 7 || hour >= 22');?>);}
			if(dark){html.classList.add('darkmode');}
			var a=localStorage.getItem('Argon_Enable_Amoled_Dark_Mode');
			if(a==='true'){html.classList.add('amoled-dark');}
			else if(a==='false'){html.classList.remove('amoled-dark');}
		}catch(e){}
	})();
	</script>
	<?php } ?>
	<!-- SEO / OG / Twitter -->
	<?php
		/* 站点图标与 PWA manifest 与「社交元标签」无关，独立于 argon_enable_social_meta 输出。
		   原先这两行被包在下面那个 if 里，导致关掉社交元标签会【连带丢掉 PWA manifest】
		   （实测：关闭后全站 rel="manifest" = 0；favicon 只是被 WP core 自己的输出兜住了，
		   并非没受影响）。见 docs/SEO与语义标签修复方案.md §3 */
		$argon_site_icon = get_site_icon_url();
		if ($argon_site_icon != ''){
	?>
	<link rel="icon" href="<?php echo esc_url($argon_site_icon); ?>">
	<?php } ?>
	<link rel="manifest" href="<?php echo esc_url(home_url('?argon_manifest=1')); ?>">

	<?php if (get_option('argon_enable_social_meta', 'true') != 'false'){ ?>
	<meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name'));?>">
	<meta property="og:title" content="<?php echo esc_attr(wp_get_document_title());?>">
	<meta property="og:type" content="<?php echo is_singular() ? 'article' : 'website'; ?>">
	<?php
		/* og:url 应与 canonical 一致。
		   原先用 home_url(add_query_arg(array(), $wp->request)) —— 在【朴素固定链接】下
		   $wp->request 为空，会退化成首页地址（实测测试站文章页 og:url = 首页，
		   而 canonical = /?p=1，两者不一致），分享出去的链接会指向首页。

		   注意分页：博客首页/归档的第 2 页起必须带上页码，否则 og:url 会退回第 1 页
		   （实测过：?paged=2 时 og:url 变成 "/"）。所以除【静态首页且不分页】外，
		   博客首页与各类归档统一走 get_pagenum_link() —— 它对第 1 页会自动省略页码。 */
		$argon_og_paged = max(1, (int) get_query_var('paged'));
		if (is_singular()){
			$argon_og_url = get_permalink();
		}elseif (is_front_page() && !is_paged()){
			/* 静态首页（未分页）：get_pagenum_link() 在这里可能取到文章页的 URL，故单独处理 */
			$argon_og_url = home_url('/');
		}elseif (is_home() || is_front_page() || is_category() || is_tag() || is_tax() || is_author() || is_post_type_archive()){
			$argon_og_url = get_pagenum_link($argon_og_paged);
		}elseif (is_search()){
			$argon_og_url = get_search_link();
		}else{
			$argon_og_url = home_url(add_query_arg(array(), $wp->request));
		}
	?>
	<meta property="og:url" content="<?php echo esc_url($argon_og_url);?>">
	<meta property="og:locale" content="<?php echo esc_attr(get_locale()); ?>">
	<?php
		/* 注意：$seo_description 由 get_seo_description() 返回，那里【不做】转义，
		   统一在这里 esc_attr()。摘要里若含 " 而未转义，会截断 content="…" 属性。
		   下面三个标签共用同一个变量，必须一起转义。 */
		$seo_description = get_seo_description();
		if ($seo_description != ''){ ?>
			<?php if (get_option('argon_enable_seo_description', 'true') != 'false'){ ?>
			<meta name="description" content="<?php echo esc_attr($seo_description)?>">
			<?php } ?>
			<meta property="og:description" content="<?php echo esc_attr($seo_description)?>">
			<meta name="twitter:description" content="<?php echo esc_attr($seo_description)?>">
	<?php } ?>

	<?php
		/* 关键词开关只控制 <meta name="keywords"> 本身。
		   Description 开关同理：只控制 <meta name="description">，
		   不影响上面的 og:description / twitter:description（那两个属社交元标签，
		   由 argon_enable_social_meta 管）。 */
		$seo_keywords = get_seo_keywords();
		if ($seo_keywords != '' && get_option('argon_enable_seo_keywords', 'true') != 'false'){ ?>
			<meta name="keywords" content="<?php echo esc_attr($seo_keywords);?>">
	<?php } ?>

	<?php
		$og_image = argon_get_social_image();
		if ($og_image != ''){
			$og_size = argon_get_image_size($og_image); ?>
			<meta property="og:image" content="<?php echo esc_url($og_image); ?>" />
			<meta name="twitter:image" content="<?php echo esc_url($og_image); ?>" />
			<?php if ($og_size){ ?>
			<meta property="og:image:width" content="<?php echo (int) $og_size[0]; ?>">
			<meta property="og:image:height" content="<?php echo (int) $og_size[1]; ?>">
			<?php } ?>
		<?php } ?>

	<?php if (is_singular() && !is_front_page()){ ?>
		<?php
			global $post;
			$author_id = $post -> post_author;
			$author_url = get_author_posts_url($author_id);
			$cats = get_the_category($post -> ID);
			$cat = !empty($cats) ? $cats[0] -> name : '';
			$tags = get_the_tags($post -> ID);
		?>
		<meta property="article:section" content="<?php echo esc_attr($cat); ?>">
		<meta property="article:author" content="<?php echo esc_url($author_url); ?>">
		<meta property="article:published_time" content="<?php echo get_the_date('c', $post -> ID); ?>">
		<meta property="article:modified_time" content="<?php echo get_the_modified_date('c', $post -> ID); ?>">
		<?php if ($tags){ foreach ($tags as $t){ ?>
			<meta property="article:tag" content="<?php echo esc_attr($t -> name); ?>">
		<?php } } ?>
	<?php } ?>

	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="<?php echo wp_get_document_title();?>">
	<?php } ?>

	<meta name="theme-color" content="<?php echo esc_attr($themecolor); ?>">
	<meta name="theme-color-rgb" content="<?php echo esc_attr(hex2str($themecolor)); ?>">
	<meta name="theme-color-origin" content="<?php echo esc_attr($themecolor_origin); ?>">
	<meta name="argon-enable-custom-theme-color" content="<?php echo (argon_get_option('argon_show_customize_theme_color_picker') != 'false' ? 'true' : 'false'); ?>">


	<meta name="theme-card-radius" content="<?php echo $cardradius; ?>">
	<meta name="theme-card-radius-origin" content="<?php echo $cardradius_origin; ?>">

	<meta name="theme-version" content="<?php echo $GLOBALS['theme_version']; ?>">

	<link rel="profile" href="http://gmpg.org/xfn/11">
	<?php if ( is_singular() && pings_open( get_queried_object() ) ) : ?>
	<link rel="pingback" href="<?php echo esc_url( get_bloginfo( 'pingback_url' ) ); ?>">
	<?php endif; ?>
	<?php
		wp_enqueue_style("argon_css_merged", $GLOBALS['assets_path'] . "/assets/argon_css_merged.css", null, $GLOBALS['theme_version']);
		// iziToast 弹窗样式（约 56KB）：已从合并包拆出（原占合并包 18.8%）。
		// 该组件只在用户交互后触发（点赞、评论提交、复制等 AJAX 回调，共 35 处调用），
		// 首屏渲染时不会有任何提示气泡出现，故随其他交互态样式一并异步加载。
		// 下方 style_loader_tag 中以 media=print + onload 方式放行阻塞。
		wp_enqueue_style("argon-izitoast", $GLOBALS['assets_path'] . "/assets/css/argon-izitoast.css", null, $GLOBALS['theme_version']);
		// Font Awesome 6 子集（同步）：由 tools/font-subset/ 按「全站实际用到的图标类名」生成，
		// 含 @font-face + 基类/工具类 + FA6 官方 v4 兼容层（fa-clock-o / fa-send 等旧类名）。
		// 字体同步子集化到同一份码点清单（FA6 三字体 292.6KB -> 13.5KB）。
		// 原 FA6 全量 CSS（all.min.css 100.6KB + v4-shims 27KB）已裁为 22.5KB，故改为同步加载，
		// 不再依赖合并包内 FA4 兜底首屏图标（FA4 已整体移除）。
		// 新增图标后需重跑：gen-codepoints.js -> subset.py -> trim-css.js -> verify.js
		wp_enqueue_style("font-awesome-subset", $GLOBALS['assets_path'] . "/assets/css/argon-fontawesome.css", null, $GLOBALS['theme_version']);
		// 网页字体：按「禁用 Google 字体」开关条件加载（关闭 = 完全不下任何网页字体，回退系统字体栈）。
		// Open Sans 已改为自托管 Latin 子集：单个 47.2KB 可变字体文件覆盖 300/400/600/700 全部字重，
		// 取代原先向 Google 请求的 6 个子集共 201.4KB，且不再依赖第三方域名。
		// 放在 style.css 之前，让字体下载尽早开始（@font-face 的可用性与顺序无关，这里只为提前发现）。
		// 衬线模式下不必加载：style.css 里 "Open Sans" 只出现在 :root 的 --font，
		// 而 html.use-serif body 会用 --font:"Noto Serif SC",... 覆盖掉它。
		// 实测（821 个元素）：非衬线 780 个在用；衬线 0 个在用、字体根本不加载 —— 纯浪费 47.2KB。
		if (argon_get_option('argon_disable_googlefont') != 'true' && get_option('argon_font') != 'serif') {
			wp_enqueue_style("argon-fonts", $GLOBALS['assets_path'] . "/assets/css/argon-fonts.css", null, $GLOBALS['theme_version']);
		}
		wp_enqueue_style("style", $GLOBALS['assets_path'] . "/style.css", null, $GLOBALS['theme_version']);
		// 衬线模式仍需要 Noto Serif SC（CJK 字体，体积过大不适合打包进主题）。
		// 它的 @font-face 占 Google CSS 的绝大部分（raw 220KB / gzip 60.6KB，202 个 face），
		// 非衬线模式下 use-serif 类不存在、这些字体永远不会被用到 —— 故只在衬线模式请求。
		if (argon_get_option('argon_disable_googlefont') != 'true' && get_option('argon_font') == 'serif') {
			wp_enqueue_style("googlefont", "//fonts.googleapis.com/css?family=Noto+Serif+SC:300,600&display=swap");
		}
		wp_enqueue_script("argon_js_merged", $GLOBALS['assets_path'] . "/assets/argon_js_merged.js", null, $GLOBALS['theme_version']);
		/* 条件加载：仅在使用到的功能开启时才请求对应 vendor，降低首屏 JS 体积 */
		/* fancybox5（灯箱，gzip 47.9KB）已改为【按需加载】：
		   首次点击 [data-fancybox]（或指针移入时预热）才注入 JS/CSS，
		   清单在上面的 $argon_lazy_assets['fancybox']。
		   原先用 defer 只是不阻塞，每次访问仍要下载一遍 —— 现在是根本不下载。
		   见 docs/fancybox与pickr懒加载方案.md */
		if (argon_get_option('argon_enable_code_highlight') == 'true'){
			wp_enqueue_script("highlight", $GLOBALS['assets_path'] . "/assets/vendor/highlight/highlight.pack.js", null, $GLOBALS['theme_version']);
			wp_enqueue_script("highlight-ln", $GLOBALS['assets_path'] . "/assets/vendor/highlight/highlightjs-line-numbers.min.js", null, $GLOBALS['theme_version']);
			wp_enqueue_style("highlight-style", $GLOBALS['assets_path'] . "/assets/vendor/highlight/styles/" . (argon_get_option('argon_code_theme') == '' ? 'vs2015' : argon_get_option('argon_code_theme')) . ".css", null, $GLOBALS['theme_version']);
		}
		if (argon_get_option('argon_show_customize_theme_color_picker') != 'false'){
			/* pickr（取色器）与 nouislider（圆角滑块）已改为【按需加载】：
			   两者都只在打开设置面板时才需要，合计 gzip 35.3KB，原先每页都下载一遍。
			   清单在上面的 $argon_lazy_assets['pickr'] / ['nouislider']，由 argontheme.js 的
			   argonEnsureSettingsAssets() 在首次打开面板（或指针移入按钮预热）时注入。
			   注意 pickr JS 是 1.8.2，而 vendor 的 monolith.min.css 仍是 1.5.0（取色变量/面板结构
			   不匹配，会导致色块变黑、面板错位），故 CSS 用主题自有的、与 JS 同版本的
			   assets/css/pickr-monolith-1.8.2.css。
			   见 docs/fancybox与pickr懒加载方案.md */
		}
	?>
	<?php wp_head(); ?>
	<?php $GLOBALS['wp_path'] = get_option('argon_wp_path') == '' ? '/' : get_option('argon_wp_path'); ?>
	<?php
	/* 按需加载的资源清单 —— 交给 argontheme.js 的 argonLoadAssets 使用。
	   只下发【本次真的会用到】的那些，条件与原先的 wp_enqueue 保持一致，
	   避免配置关掉后还占 config 体积。
	   URL 必须走 $GLOBALS['assets_path']（可能是 CDN 基址）并在 PHP 侧拼好，
	   否则开了「资源 CDN」选项的站点会 404。
	   ?ver= 与 wp_enqueue_* 生成的 URL 一致，命中同一份 CDN/浏览器缓存。 */
	$argon_lazy_assets = array();
	if (argon_get_option('argon_enable_fancybox') != 'false' && argon_get_option('argon_enable_zoomify') == 'false'){
		$argon_lazy_assets['fancybox'] = array(
			'js'  => array($GLOBALS['assets_path'] . '/assets/vendor/fancybox5/fancybox.umd.js?ver=' . $GLOBALS['theme_version']),
			'css' => array($GLOBALS['assets_path'] . '/assets/vendor/fancybox5/fancybox.css?ver=' . $GLOBALS['theme_version']),
		);
	}
	if (argon_get_option('argon_show_customize_theme_color_picker') != 'false'){
		$argon_lazy_assets['pickr'] = array(
			'js'  => array($GLOBALS['assets_path'] . '/assets/vendor/pickr/pickr.es5.min.js?ver=' . $GLOBALS['theme_version']),
			'css' => array($GLOBALS['assets_path'] . '/assets/css/pickr-monolith-1.8.2.css?ver=' . $GLOBALS['theme_version']),
		);
		$argon_lazy_assets['nouislider'] = array(
			'js'  => array($GLOBALS['assets_path'] . '/assets/vendor/nouislider/js/nouislider.min.js?ver=' . $GLOBALS['theme_version']),
		);
	}
	?>
	<script>
		document.documentElement.classList.remove("no-js");
		var argonConfig = {
			wp_path: "<?php echo $GLOBALS['wp_path']; ?>",
			ajax_nonce: "<?php echo wp_create_nonce('argon_ajax_action'); ?>",
			language: "<?php echo argon_get_locate(); ?>",
			dateFormat: "<?php echo get_option('argon_dateformat', 'YMD'); ?>",
			<?php if (argon_get_option('argon_enable_zoomify') == 'true'){ ?>
				zoomify: {
					duration: <?php echo get_option('argon_zoomify_duration', 200); ?>,
					easing: "<?php echo get_option('argon_zoomify_easing', 'cubic-bezier(0.4,0,0,1)'); ?>",
					scale: <?php echo get_option('argon_zoomify_scale', 0.9); ?>
				},
			<?php } else { ?>
				zoomify: false,
			<?php } ?>
			pangu: "<?php echo get_option('argon_enable_pangu', 'false'); ?>",
			<?php if (get_option('argon_enable_lazyload') != 'false'){ ?>
				lazyload: {
					threshold: <?php echo get_option('argon_lazyload_threshold', 800); ?>,
					effect: "<?php echo get_option('argon_lazyload_effect', 'fadeIn'); ?>"
				},
			<?php } else { ?>
				lazyload: false,
			<?php } ?>
			fold_long_comments: <?php echo get_option('argon_fold_long_comments', 'false'); ?>,
			fold_long_shuoshuo: <?php echo get_option('argon_fold_long_shuoshuo', 'false'); ?>,
			disable_pjax: <?php echo get_option('argon_pjax_disabled', 'false'); ?>,
			pjax_animation_durtion: <?php echo (get_option("argon_disable_pjax_animation") == 'true' ? '0' : '600'); ?>,
			headroom: "<?php echo get_option('argon_enable_headroom', 'false'); ?>",
			waterflow_columns: "<?php echo get_option('argon_article_list_waterflow', '1'); ?>",
			code_highlight: {
				enable: <?php echo get_option('argon_enable_code_highlight', 'false'); ?>,
				hide_linenumber: <?php echo get_option('argon_code_highlight_hide_linenumber', 'false'); ?>,
				transparent_linenumber: <?php echo get_option('argon_code_highlight_transparent_linenumber', 'false'); ?>,
				break_line: <?php echo get_option('argon_code_highlight_break_line', 'false'); ?>
			},
			/* 按需加载清单（fancybox / pickr / nouislider），见文件上方 $argon_lazy_assets */
			lazy_assets: <?php echo wp_json_encode($argon_lazy_assets); ?>
		}
	</script>
	<script>
	/* 深色模式：本段位于 <head> 且必须同步执行（否则首屏会先按浅色绘制再切深色，视觉上闪一下），
	   因此**不能**延后到 DOMContentLoaded，更不能依赖 head 里的 jQuery。
	   故全部改用原生 classList / dispatchEvent，与 jQuery 解耦，
	   使 head 内唯一必须同步加载的脚本（jQuery + Bootstrap）可以进一步 defer。
	   对外仍暴露 setDarkmode / toggleDarkmode 等同名全局函数，
	   供 defer 后的 argontheme.js（:617-673）调用，接口未变。 */
	(function(){
		var html = document.documentElement;
		var darkmodeAutoSwitch = "<?php echo (get_option("argon_darkmode_autoswitch") == '' ? 'false' : get_option("argon_darkmode_autoswitch"));?>";
		function triggerScroll(){
			try{ window.dispatchEvent(new Event("scroll")); }catch(e){}
		}
		window.setDarkmode = function(enable){
			if (enable == true){
				html.classList.add("darkmode");
			}else{
				html.classList.remove("darkmode");
			}
			triggerScroll();
		};
		window.toggleDarkmode = function(){
			if (html.classList.contains("darkmode")){
				window.setDarkmode(false);
				sessionStorage.setItem("Argon_Enable_Dark_Mode", "false");
			}else{
				window.setDarkmode(true);
				sessionStorage.setItem("Argon_Enable_Dark_Mode", "true");
			}
		};
		var setDarkmode = window.setDarkmode;
		var toggleDarkmode = window.toggleDarkmode;
		if (sessionStorage.getItem("Argon_Enable_Dark_Mode") == "true"){
			setDarkmode(true);
		}
		function toggleDarkmodeByPrefersColorScheme(media){
			if (sessionStorage.getItem('Argon_Enable_Dark_Mode') == "false" || sessionStorage.getItem('Argon_Enable_Dark_Mode') == "true"){
				return;
			}
			if (media.matches){
				setDarkmode(true);
			}else{
				setDarkmode(false);
			}
		}
		function toggleDarkmodeByTime(){
			if (sessionStorage.getItem('Argon_Enable_Dark_Mode') == "false" || sessionStorage.getItem('Argon_Enable_Dark_Mode') == "true"){
				return;
			}
			let hour = new Date().getHours();
			if (<?php echo apply_filters("argon_darkmode_time_check", "hour < 7 || hour >= 22")?>){
				setDarkmode(true);
			}else{
				setDarkmode(false);
			}
		}
		if (darkmodeAutoSwitch == 'system'){
			var darkmodeMediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
			darkmodeMediaQuery.addListener(toggleDarkmodeByPrefersColorScheme);
			toggleDarkmodeByPrefersColorScheme(darkmodeMediaQuery);
		}
		if (darkmodeAutoSwitch == 'time'){
			toggleDarkmodeByTime();
		}
		if (darkmodeAutoSwitch == 'alwayson'){
			setDarkmode(true);
		}

		window.toggleAmoledDarkMode = function(){
			html.classList.toggle("amoled-dark");
			if (html.classList.contains("amoled-dark")){
				localStorage.setItem("Argon_Enable_Amoled_Dark_Mode", "true");
			}else{
				localStorage.setItem("Argon_Enable_Amoled_Dark_Mode", "false");
			}
		};
		if (localStorage.getItem("Argon_Enable_Amoled_Dark_Mode") == "true"){
			html.classList.add("amoled-dark");
		}else if (localStorage.getItem("Argon_Enable_Amoled_Dark_Mode") == "false"){
			html.classList.remove("amoled-dark");
		}
	})();
	</script>
	<script>
		/* Safari 检测：同理改为原生 classList，避免 head 内出现任何 jQuery 依赖 */
		if (navigator.userAgent.indexOf("Safari") !== -1 && navigator.userAgent.indexOf("Chrome") === -1){
			document.documentElement.classList.add("using-safari");
		}
	</script>

	<?php if (get_option('argon_enable_smoothscroll_type') == '2') { /*平滑滚动*/?>
		<script defer src="<?php echo $GLOBALS['assets_path']; ?>/assets/vendor/smoothscroll/smoothscroll2.js"></script>
	<?php }else if (get_option('argon_enable_smoothscroll_type') == '3'){?>
		<script defer src="<?php echo $GLOBALS['assets_path']; ?>/assets/vendor/smoothscroll/smoothscroll3.min.js"></script>
	<?php }else if (get_option('argon_enable_smoothscroll_type') == '1_pulse'){?>
		<script defer src="<?php echo $GLOBALS['assets_path']; ?>/assets/vendor/smoothscroll/smoothscroll1_pulse.js"></script>
	<?php }else if (get_option('argon_enable_smoothscroll_type') != 'disabled'){?>
		<script defer src="<?php echo $GLOBALS['assets_path']; ?>/assets/vendor/smoothscroll/smoothscroll1.js"></script>
	<?php }?>
</head>

<?php echo get_option('argon_custom_html_head'); ?>

<style id="themecolor_css">
	<?php
		$themecolor_rgbstr = hex2str($themecolor);
		$RGB = hexstr2rgb($themecolor);
		$HSL = rgb2hsl($RGB['R'], $RGB['G'], $RGB['B']);
	?>
	:root{
		--themecolor: <?php echo $themecolor; ?>;
		--themecolor-R: <?php echo $RGB['R']; ?>;
		--themecolor-G: <?php echo $RGB['G']; ?>;
		--themecolor-B: <?php echo $RGB['B']; ?>;
		--themecolor-H: <?php echo $HSL['H']; ?>;
		--themecolor-S: <?php echo $HSL['S']; ?>;
		--themecolor-L: <?php echo $HSL['L']; ?>;
	}
</style>
<style id="theme_cardradius_css">
	:root{
		--card-radius: <?php echo $cardradius; ?>px;
	}
</style>

<body <?php body_class(); ?>>
<?php /*wp_body_open();*/ ?>
<?php if (get_option('argon_enable_preloader') != 'false') { ?>
<?php /* 模糊层：夹在圆环与博客内容之间（层级 圆环 → 模糊 → 内容）。
         平时 backdrop-filter:none，零开销；只在退场时由 argon-revealing 触发模糊收敛。
         放在遮罩【之前】，靠 z-index 99998 < 99999 保证在遮罩下面。 */ ?>
<div class="argon-preloader__blur" aria-hidden="true"></div>
<div id="argon-preloader" aria-hidden="true">
	<div class="argon-preloader__inner">
		<div class="argon-preloader__spinner"></div>
		<div class="argon-preloader__text"><?php _e('加载中', 'argon'); ?></div>
	</div>
</div>
<script>
(function(){
	var el = document.getElementById('argon-preloader');
	if (!el) return;
	var blurEl = document.querySelector('.argon-preloader__blur');
	var hidden = false;
	function hide(){
		if (hidden) return; hidden = true;
		/* argon-revealing 加在 <html> 上：它同时驱动三件事，所以天然同步 ——
		   ① 遮罩的圆形化开（--argon-reveal 过渡）
		   ② 模糊层的 backdrop-filter 收敛（argon-blur-out 动画）
		   ③ 转圈的模糊淡出（argon-preloader-out，由 --hidden 触发）
		   注意不能加在 body 上：那里一旦有 filter 就会创建包含块，
		   内部 position:fixed 的浮动按钮会因此错位（实测差 254px）。 */
		document.documentElement.classList.add('argon-revealing');
		el.classList.add('argon-preloader--hidden');
		/* 等化开（1.5s）与内层模糊淡出（2s）都走完再摘掉节点 */
		setTimeout(function(){
			if (el && el.parentNode) el.parentNode.removeChild(el);
			if (blurEl && blurEl.parentNode) blurEl.parentNode.removeChild(blurEl);
			document.documentElement.classList.remove('argon-revealing');
		}, 2200);
	}
	var start = Date.now();
	/* 字体就绪 —— 但必须【加超时】。
	   document.fonts.ready 在字体慢或 CDN 抖动时会一直挂着，
	   那样遮罩会把整页（包括已经就位的骨架屏）一直盖住，
	   最坏要等下面那个 10s 兜底才消失。 */
	var FONT_TIMEOUT = 1500;
	var fontsReady = (document.fonts && document.fonts.ready)
		? Promise.race([document.fonts.ready, new Promise(function(res){ setTimeout(res, FONT_TIMEOUT); })])
		: Promise.resolve();
	/* 最短可见时长：只留一点点，避免样式刚应用就闪一下。
	   关闭的主条件已经变成「骨架屏已就位（DOMContentLoaded + 样式表就绪） + 字体就绪」，
	   不再固定等 600ms —— 快网下那 600ms 会把骨架屏 / LQIP 白白压住，
	   而它们正是用来填补「遮罩退场之后、图片还没下完」那段时间的。 */
	var minVisible = 150;
	function minDelay(){ return new Promise(function(res){ setTimeout(res, Math.max(0, minVisible - (Date.now() - start))); }); }
	// 等待所有样式表加载完成（合并 JS 为解析阻塞脚本，已在 DOMContentLoaded 前执行完毕）
	function stylesheetsReady(){
		var links = document.querySelectorAll('link[rel="stylesheet"]');
		var pending = [];
		for (var i = 0; i < links.length; i++){
			(function(l){
				if (l.sheet) return;
				pending.push(new Promise(function(res){ l.addEventListener('load', res); l.addEventListener('error', res); }));
			})(links[i]);
		}
		return Promise.all(pending);
	}
	/* 关闭时机：DOMContentLoaded（骨架屏的 HTML 已解析）+ 样式表就绪（骨架屏样式已生效）
	   + 字体就绪（或超时）+ 极短的最短可见时长，随后带模糊淡出。
	   刻意【不等图片】—— 遮罩退场之后正是骨架屏 / LQIP 接手的那段时间。 */
	function ready(){ Promise.all([stylesheetsReady(), fontsReady, minDelay()]).then(hide); }
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
	window.addEventListener('load', hide);
	// 兜底：10s 仍异常则强制隐藏，避免永久遮罩
	setTimeout(hide, 10000);
})();
</script>
<?php } ?>
<div id="toolbar">
	<header class="header-global">
		<nav id="navbar-main" class="navbar navbar-main navbar-expand-lg navbar-transparent navbar-light bg-primary headroom--not-bottom headroom--not-top headroom--pinned">
			<div class="container">
				<button id="open_sidebar" class="navbar-toggler" type="button" aria-expanded="false" aria-label="Toggle sidebar">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div class="navbar-brand mr-0">
					<?php if (get_option('argon_toolbar_icon') != '') { /*顶栏ICON(如果选项中开启)*/?>
						<a class="navbar-brand navbar-icon mr-lg-5" href="<?php echo get_option('argon_toolbar_icon_link'); ?>">
							<img src="<?php echo get_option('argon_toolbar_icon'); ?>">
						</a>
					<?php }?>
					<?php
						//顶栏标题
						$toolbar_title = get_option('argon_toolbar_title') == '' ? get_bloginfo('name') : get_option('argon_toolbar_title');
						if ($toolbar_title == '--hidden--'){
							$toolbar_title = '';
						}
					?>
					<a class="navbar-brand navbar-title" href="<?php bloginfo('url'); ?>"><?php echo $toolbar_title;?></a>
				</div>
				<div class="navbar-collapse collapse" id="navbar_global">
					<div class="navbar-collapse-header">
						<div class="row" style="display: none;">
							<div class="col-6 collapse-brand"></div>
							<div class="col-6 collapse-close">
								<button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbar_global" aria-controls="navbar_global" aria-expanded="false" aria-label="Toggle navigation">
									<span></span>
									<span></span>
								</button>
							</div>
						</div>
						<div class="input-group input-group-alternative">
							<div class="input-group-prepend">
								<span class="input-group-text"><i class="fa fa-search"></i></span>
							</div>
							<input id="navbar_search_input_mobile" class="form-control" placeholder="搜索什么..." type="text" autocomplete="off">
						</div>
					</div>
					<?php
						/*顶栏菜单*/
						class toolbarMenuWalker extends Walker_Nav_Menu{
							public function start_lvl( &$output, $depth = 0, $args = array() ) {
								$indent = str_repeat("\t", $depth);
								$output .= "\n$indent<div class=\"dropdown-menu\">\n";
							}
							public function end_lvl( &$output, $depth = 0, $args = array() ) {
								$indent = str_repeat("\t", $depth);
								$output .= "\n$indent</div>\n";
							}
							public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
								if ($depth == 0){
									if ($args -> walker -> has_children == 1){
										$output .= "\n
										<li class='nav-item dropdown'>
											<a href='" . $object -> url . "' class='nav-link' data-toggle='dropdown' no-pjax onclick='return false;' title='" . $object -> description . "'>
										  		<i class='ni ni-book-bookmark d-lg-none'></i>
												<span class='nav-link-inner--text'>" . $object -> title . "</span>
										  </a>";
									}else{
										$output .= "\n
										<li class='nav-item'>
											<a href='" . $object -> url . "' class='nav-link' target='" . $object -> target . "' title='" . $object -> description . "'>
										  		<i class='ni ni-book-bookmark d-lg-none'></i>
												<span class='nav-link-inner--text'>" . $object -> title . "</span>
										  </a>";
									}
								}else if ($depth == 1){
									$output .= "<a href='" . $object -> url . "' class='dropdown-item' target='" . $object -> target . "' title='" . $object -> description . "'>" . $object -> title . "</a>";
								}
							}
							public function end_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
								if ($depth == 0){
									$output .= "\n</li>";
								}
							}
						}
						if ( has_nav_menu('toolbar_menu') ){
							echo "<ul class='navbar-nav navbar-nav-hover align-items-lg-center'>";
							wp_nav_menu( array(
								'container'  => '',
								'theme_location'  => 'toolbar_menu',
								'items_wrap'  => '%3$s',
								'depth' => 0,
								'walker' => new toolbarMenuWalker()
							) );
							echo "</ul>";
						}
					?>
					<ul class="navbar-nav align-items-lg-center ml-lg-auto">
						<li id="navbar_search_container" class="nav-item" data-toggle="modal">
							<div id="navbar_search_input_container">
								<div class="input-group input-group-alternative">
									<div class="input-group-prepend">
										<span class="input-group-text"><i class="fa fa-search"></i></span>
									</div>
									<input id="navbar_search_input" class="form-control" placeholder="<?php _e('搜索什么...', 'argon');?>" type="text" autocomplete="off">
								</div>
							</div>
						</li>
					</ul>
				</div>
				<div id="navbar_menu_mask" data-toggle="collapse" data-target="#navbar_global"></div>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar_global" aria-controls="navbar_global" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon navbar-toggler-searcg-icon"></span>
				</button>
			</div>
		</nav>
	</header>
</div>
<div class="modal fade" id="argon_search_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><?php _e('搜索', 'argon');?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<?php get_search_form(); ?>
			</div>
		</div>
	</div>
</div>
<!--Banner-->
<section id="banner" class="banner section section-lg section-shaped">
	<div class="shape <?php echo get_option('argon_banner_background_hide_shapes') == 'true' ? '' : 'shape-style-1' ?> <?php echo get_option('argon_banner_background_color_type') == '' ? 'shape-primary' : get_option('argon_banner_background_color_type'); ?>">
		<span></span>
		<span></span>
		<span></span>
		<span></span>
		<span></span>
		<span></span>
		<span></span>
		<span></span>
		<span></span>
	</div>

	<?php
		$banner_title = get_option('argon_banner_title') == '' ? get_bloginfo('name') : get_option('argon_banner_title');
		$enable_banner_title_typing_effect = get_option('argon_enable_banner_title_typing_effect') != 'true' ? "false" : get_option('argon_enable_banner_title_typing_effect');
		/* 首页（含静态首页）的 banner 站名用 <h1>，其余页面保持 <div>：
		   首页此前完全没有 h1（banner 站名是 div、#content 里也没有标题），
		   而文章页的 h1 是文章标题，banner 再出 h1 就会有两个。
		   注意 banner_size 设为 hide 时本区块【仍然渲染】（只是 <html> 多了 no-banner 类，
		   由 CSS 隐藏），所以首页在任何配置下都恰好有一个 h1。 */
		$argon_banner_tag = (is_home() || is_front_page()) ? 'h1' : 'div';
	?>
	<div id="banner_container" class="banner-container container text-center">
		<?php if ($enable_banner_title_typing_effect != "true"){?>
			<<?php echo $argon_banner_tag; ?> class="banner-title text-white"><span class="banner-title-inner"><?php echo apply_filters('argon_banner_title_html', $banner_title); ?></span>
			<?php echo get_option('argon_banner_subtitle') == '' ? '' : '<span class="banner-subtitle d-block">' . get_option('argon_banner_subtitle') . '</span>'; ?></<?php echo $argon_banner_tag; ?>>
		<?php } else {?>
			<<?php echo $argon_banner_tag; ?> class="banner-title text-white" data-interval="<?php echo get_option('argon_banner_typing_effect_interval', 100); ?>"><span data-text="<?php echo $banner_title; ?>" class="banner-title-inner">&nbsp;</span>
			<?php echo get_option('argon_banner_subtitle') == '' ? '' : '<span data-text="' . get_option('argon_banner_subtitle') . '" class="banner-subtitle d-block">&nbsp;</span>'; ?></<?php echo $argon_banner_tag; ?>>
		<?php }?>
	</div>
	<?php if (get_option('argon_banner_background_url') != '') { ?>
		<style>
			section.banner{
				background-image: url(<?php echo get_banner_background_url(); ?>) !important;
			}
		</style>
	<?php } ?>
	<?php if ($banner_size == 'fullscreen') { ?>
		<div class="cover-scroll-down">
			<i class="fa fa-angle-down" aria-hidden="true"></i>
		</div>
	<?php } ?>
</section>

<?php if (apply_filters('argon_page_background_url', get_option('argon_page_background_url')) != '') { ?>
	<style>
		<?php if (get_option('argon_page_background_banner_style', 'false') == 'transparent') { ?>
			#banner, #banner .shape {
				background: transparent !important;
			}
		<?php } ?>
		#content:before {
			content: '';
			display: block;
			position: fixed;
			left: 0;
			right: 0;
			top: 0;
			bottom: 0;
			z-index: -2;
			background: url(<?php echo apply_filters('argon_page_background_url', get_option('argon_page_background_url'));?>);
			background-position: center;
			background-size: cover;
			background-repeat: no-repeat;
			opacity: <?php echo (get_option('argon_page_background_opacity') == '' ? '1' : get_option('argon_page_background_opacity')); ?>;
			transition: opacity .5s ease;
		}
		html.darkmode #content:before{
			filter: brightness(0.65);
		}
		<?php if (apply_filters('argon_page_background_dark_url', get_option('argon_page_background_dark_url')) != '') { ?>
			#content:after {
				content: '';
				display: block;
				position: fixed;
				left: 0;
				right: 0;
				top: 0;
				bottom: 0;
				z-index: -2;
				background: url(<?php echo apply_filters('argon_page_background_dark_url', get_option('argon_page_background_dark_url'));?>);
				background-position: center;
				background-size: cover;
				background-repeat: no-repeat;
				opacity: 0;
				transition: opacity .5s ease;
			}
			html.darkmode #content:after {
				opacity: <?php echo (get_option('argon_page_background_opacity') == '' ? '1' : get_option('argon_page_background_opacity')); ?>;
			}
			html.darkmode #content:before {
				opacity: 0;
			}
		<?php } ?>
	</style>
<?php } ?>

<?php if (get_option('argon_show_toolbar_mask') == 'true') { ?>
	<style>
		#banner:after {
			content: '';
			width: 100vw;
			position: absolute;
			left: 0;
			top: 0;
			height: 120px;
			background: linear-gradient(180deg, rgba(0,0,0,0.25) 0%, rgba(0,0,0,0.15) 35%, rgba(0,0,0,0) 100%);
			display: block;
			z-index: -1;
		}
		.banner-title {
			text-shadow: 0 5px 15px rgba(0, 0, 0, .2);
		}
	</style>
<?php } ?>

<div id="float_action_buttons" class="float-action-buttons fabtns-unloaded">
	<button id="fabtn_toggle_sides" class="btn btn-icon btn-neutral fabtn shadow-sm" type="button" aria-hidden="true" tooltip-move-to-left="<?php _e('移至左侧', 'argon'); ?>" tooltip-move-to-right="<?php _e('移至右侧', 'argon'); ?>">
		<span class="btn-inner--icon fabtn-show-on-right"><i class="fa fa-caret-left"></i></span>
		<span class="btn-inner--icon fabtn-show-on-left"><i class="fa fa-caret-right"></i></span>
	</button>
	<button id="fabtn_back_to_top" class="btn btn-icon btn-neutral fabtn shadow-sm" type="button" aria-label="Back To Top" tooltip="<?php _e('回到顶部', 'argon'); ?>">
		<span class="btn-inner--icon"><i class="fa fa-angle-up"></i></span>
	</button>
	<button id="fabtn_go_to_comment" class="btn btn-icon btn-neutral fabtn shadow-sm d-none" type="button" <?php if (get_option('argon_fab_show_gotocomment_button') != 'true') echo " style='display: none;'";?> aria-label="Comment" tooltip="<?php _e('评论', 'argon'); ?>">
		<span class="btn-inner--icon"><i class="fa fa-comment-o"></i></span>
	</button>
	<button id="fabtn_toggle_darkmode" class="btn btn-icon btn-neutral fabtn shadow-sm" type="button" <?php if (get_option('argon_fab_show_darkmode_button') != 'true') echo " style='display: none;'";?> aria-label="Toggle Darkmode" tooltip-darkmode="<?php _e('夜间模式', 'argon'); ?>" tooltip-blackmode="<?php _e('暗黑模式', 'argon'); ?>" tooltip-lightmode="<?php _e('日间模式', 'argon'); ?>">
		<span class="btn-inner--icon"><i class="fa fa-moon-o"></i><i class='fa fa-lightbulb-o'></i></span>
	</button>
	<button id="fabtn_toggle_blog_settings_popup" class="btn btn-icon btn-neutral fabtn shadow-sm" type="button" <?php if (get_option('argon_fab_show_settings_button') == 'false') echo " style='display: none;'";?> aria-label="Open Blog Settings Menu" tooltip="<?php _e('设置', 'argon'); ?>">
		<span class="btn-inner--icon"><i class="fa fa-cog"></i></span>
	</button>
	<div id="fabtn_blog_settings_popup" class="card shadow-sm" style="opacity: 0;" aria-hidden="true">
		<div id="close_blog_settings"><i class="fa fa-close"></i></div>
		<div class="blog-setting-item mt-3">
			<div style="transform: translateY(-4px);"><div id="blog_setting_toggle_darkmode_and_amoledarkmode" tooltip-switch-to-darkmode="<?php _e('切换到夜间模式', 'argon'); ?>" tooltip-switch-to-blackmode="<?php _e('切换到暗黑模式', 'argon'); ?>"><span><?php _e('夜间模式', 'argon');?></span><span><?php _e('暗黑模式', 'argon');?></span></div></div>
			<div style="flex: 1;"></div>
			<label id="blog_setting_darkmode_switch" class="custom-toggle">
				<span class="custom-toggle-slider rounded-circle"></span>
			</label>
		</div>
		<div class="blog-setting-item mt-3">
			<div style="flex: 1;"><?php _e('字体', 'argon');?></div>
			<div>
				<button id="blog_setting_font_sans_serif" type="button" class="blog-setting-font btn btn-outline-primary blog-setting-selector-left">Sans Serif</button><button id="blog_setting_font_serif" type="button" class="blog-setting-font btn btn-outline-primary blog-setting-selector-right">Serif</button>
			</div>
		</div>
		<div class="blog-setting-item mt-3">
			<div style="flex: 1;"><?php _e('阴影', 'argon');?></div>
			<div>
				<button id="blog_setting_shadow_small" type="button" class="blog-setting-shadow btn btn-outline-primary blog-setting-selector-left"><?php _e('浅阴影', 'argon');?></button><button id="blog_setting_shadow_big" type="button" class="blog-setting-shadow btn btn-outline-primary blog-setting-selector-right"><?php _e('深阴影', 'argon');?></button>
			</div>
		</div>
		<div class="blog-setting-item mt-3 mb-3">
			<div style="flex: 1;"><?php _e('滤镜', 'argon');?></div>
			<div id="blog_setting_filters" class="ml-3">
				<button id="blog_setting_filter_off" type="button" class="blog-setting-filter-btn ml-0" filter-name="off"><?php _e('关闭', 'argon');?></button>
				<button id="blog_setting_filter_sunset" type="button" class="blog-setting-filter-btn" filter-name="sunset"><?php _e('日落', 'argon');?></button>
				<button id="blog_setting_filter_darkness" type="button" class="blog-setting-filter-btn" filter-name="darkness"><?php _e('暗化', 'argon');?></button>
				<button id="blog_setting_filter_grayscale" type="button" class="blog-setting-filter-btn" filter-name="grayscale"><?php _e('灰度', 'argon');?></button>
			</div>
		</div>
		<div class="blog-setting-item mb-3">
			<div id="blog_setting_card_radius_to_default" style="cursor: pointer;" tooltip="<?php _e('恢复默认', 'argon'); ?>"><?php _e('圆角', 'argon');?></div>
			<div style="flex: 1;margin-left: 20px;margin-right: 8px;transform: translateY(2px);">
				<div id="blog_setting_card_radius"></div>
			</div>
		</div>
		<?php if (argon_get_option('argon_show_customize_theme_color_picker') != 'false') {?>
			<div class="blog-setting-item mt-1 mb-3">
				<div style="flex: 1;"><?php _e('主题色', 'argon');?></div>
				<div id="theme-color-picker" class="ml-3"></div>
			</div>
		<?php }?>
	</div>
	<button id="fabtn_reading_progress" class="btn btn-icon btn-neutral fabtn shadow-sm" type="button" aria-hidden="true" tooltip="<?php _e('阅读进度', 'argon'); ?>">
		<div id="fabtn_reading_progress_bar" style="width: 0%;"></div>
		<span id="fabtn_reading_progress_details">0%</span>
	</button>
</div>

<div id="content" class="site-content">
