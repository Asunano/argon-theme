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
	$themecolor = get_option("argon_theme_color", "#5e72e4");
	$themecolor_origin = $themecolor;
	if (isset($_COOKIE["argon_custom_theme_color"])){
		if (checkHEX($_COOKIE["argon_custom_theme_color"]) && argon_get_option('argon_show_customize_theme_color_picker') != 'false'){
			$themecolor = $_COOKIE["argon_custom_theme_color"];
		}
	}
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
	if (isset($_COOKIE["argon_card_radius"]) && $_COOKIE["argon_card_radius"] != ""){
		$cardradius = $_COOKIE["argon_card_radius"];
	}
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
	<?php /* 字体域名资源提示：加速异步 googlefont 的 DNS/连接建立，减少 FOUT 时长（不影响渲染阻塞） */ ?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="dns-prefetch" href="https://fonts.googleapis.com">
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
	<?php if (get_option('argon_enable_social_meta', 'true') != 'false'){ ?>
	<?php
		$argon_site_icon = get_site_icon_url();
		if ($argon_site_icon != ''){
	?>
	<link rel="icon" href="<?php echo esc_url($argon_site_icon); ?>">
	<?php } ?>
	<link rel="manifest" href="<?php echo esc_url(home_url('?argon_manifest=1')); ?>">
	<meta property="og:site_name" content="<?php echo get_bloginfo('name');?>">
	<meta property="og:title" content="<?php echo wp_get_document_title();?>">
	<meta property="og:type" content="<?php echo is_singular() ? 'article' : 'website'; ?>">
	<meta property="og:url" content="<?php echo home_url(add_query_arg(array(),$wp->request));?>">
	<meta property="og:locale" content="<?php echo get_locale(); ?>">
	<?php
		$seo_description = get_seo_description();
		if ($seo_description != ''){ ?>
			<meta name="description" content="<?php echo $seo_description?>">
			<meta property="og:description" content="<?php echo $seo_description?>">
			<meta name="twitter:description" content="<?php echo $seo_description?>">
	<?php } ?>

	<?php
		$seo_keywords = get_seo_keywords();
		if ($seo_keywords != ''){ ?>
			<meta name="keywords" content="<?php echo $seo_keywords;?>">
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
		// Font Awesome 6（免费版）：all.min.css 提供 solid/regular/brands + 内置 FA4 别名；
		// v4-shims.min.css 是 FA6 官方 FA4 兼容层（fa-clock-o / fa-send 等旧类名映射），
		// 后加载覆盖合并包内的 FA4 裁剪版，保证 `fa fa-xxx` 类名全部可用
		wp_enqueue_style("font-awesome-full", $GLOBALS['assets_path'] . "/assets/vendor/font-awesome6/css/all.min.css", null, $GLOBALS['theme_version']);
		wp_enqueue_style("font-awesome-shims", $GLOBALS['assets_path'] . "/assets/vendor/font-awesome6/css/v4-shims.min.css", null, $GLOBALS['theme_version']);
		wp_enqueue_style("style", $GLOBALS['assets_path'] . "/style.css", null, $GLOBALS['theme_version']);
		if (argon_get_option('argon_disable_googlefont') != 'true') {wp_enqueue_style("googlefont", "//fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Noto+Serif+SC:300,600&display=swap");}
		wp_enqueue_script("argon_js_merged", $GLOBALS['assets_path'] . "/assets/argon_js_merged.js", null, $GLOBALS['theme_version']);
		/* 条件加载：仅在使用到的功能开启时才请求对应 vendor，降低首屏 JS 体积 */
		if (argon_get_option('argon_enable_fancybox') != 'false' && argon_get_option('argon_enable_zoomify') == 'false'){
			wp_enqueue_style("fancybox5", $GLOBALS['assets_path'] . "/assets/vendor/fancybox5/fancybox.css", null, $GLOBALS['theme_version']);
			wp_enqueue_script("fancybox5", $GLOBALS['assets_path'] . "/assets/vendor/fancybox5/fancybox.umd.js", null, $GLOBALS['theme_version']);
		}
		if (argon_get_option('argon_enable_code_highlight') == 'true'){
			wp_enqueue_script("highlight", $GLOBALS['assets_path'] . "/assets/vendor/highlight/highlight.pack.js", null, $GLOBALS['theme_version']);
			wp_enqueue_script("highlight-ln", $GLOBALS['assets_path'] . "/assets/vendor/highlight/highlightjs-line-numbers.min.js", null, $GLOBALS['theme_version']);
			wp_enqueue_style("highlight-style", $GLOBALS['assets_path'] . "/assets/vendor/highlight/styles/" . (argon_get_option('argon_code_theme') == '' ? 'vs2015' : argon_get_option('argon_code_theme')) . ".css", null, $GLOBALS['theme_version']);
		}
		if (argon_get_option('argon_show_customize_theme_color_picker') != 'false'){
			/* pickr JS 已为 1.8.2，而 vendor 的 monolith.min.css 仍是 1.5.0(取色变量/面板结构不匹配，
			   导致色块变黑、面板错位)。此处改用主题自有的、与 JS 同版本的 1.8.2 主题 CSS，避免改动 vendor 文件 */
			wp_enqueue_style("pickr-style", $GLOBALS['assets_path'] . "/assets/css/pickr-monolith-1.8.2.css", null, $GLOBALS['theme_version']);
			wp_enqueue_script("nouislider", $GLOBALS['assets_path'] . "/assets/vendor/nouislider/js/nouislider.min.js", null, $GLOBALS['theme_version']);
			wp_enqueue_script("pickr", $GLOBALS['assets_path'] . "/assets/vendor/pickr/pickr.es5.min.js", null, $GLOBALS['theme_version']);
		}
	?>
	<?php wp_head(); ?>
	<?php $GLOBALS['wp_path'] = get_option('argon_wp_path') == '' ? '/' : get_option('argon_wp_path'); ?>
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
			}
		}
	</script>
	<script>
		var darkmodeAutoSwitch = "<?php echo (get_option("argon_darkmode_autoswitch") == '' ? 'false' : get_option("argon_darkmode_autoswitch"));?>";
		function setDarkmode(enable){
			if (enable == true){
				$("html").addClass("darkmode");
			}else{
				$("html").removeClass("darkmode");
			}
			$(window).trigger("scroll");
		}
		function toggleDarkmode(){
			if ($("html").hasClass("darkmode")){
				setDarkmode(false);
				sessionStorage.setItem("Argon_Enable_Dark_Mode", "false");
			}else{
				setDarkmode(true);
				sessionStorage.setItem("Argon_Enable_Dark_Mode", "true");
			}
		}
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

		function toggleAmoledDarkMode(){
			$("html").toggleClass("amoled-dark");
			if ($("html").hasClass("amoled-dark")){
				localStorage.setItem("Argon_Enable_Amoled_Dark_Mode", "true");
			}else{
				localStorage.setItem("Argon_Enable_Amoled_Dark_Mode", "false");
			}
		}
		if (localStorage.getItem("Argon_Enable_Amoled_Dark_Mode") == "true"){
			$("html").addClass("amoled-dark");
		}else if (localStorage.getItem("Argon_Enable_Amoled_Dark_Mode") == "false"){
			$("html").removeClass("amoled-dark");
		}
	</script>
	<script>
		if (navigator.userAgent.indexOf("Safari") !== -1 && navigator.userAgent.indexOf("Chrome") === -1){
			$("html").addClass("using-safari");
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
	var hidden = false;
	function hide(){
		if (hidden) return; hidden = true;
		el.classList.add('argon-preloader--hidden');
		// 退出动画(内层模糊 2s)播完后再移除节点
		setTimeout(function(){ if (el && el.parentNode) el.parentNode.removeChild(el); }, 2100);
	}
	var start = Date.now();
	var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
	// 最短可见时长(正常 600ms)；主结束时机仍是资源(JS/CSS)加载完毕
	var minVisible = 600;
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
	// 结束时机：DOMContentLoaded(JS+CSS 已就绪) + 样式表加载 + 字体就绪 + 最短可见时长，随后带模糊淡出
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
	?>
	<div id="banner_container" class="banner-container container text-center">
		<?php if ($enable_banner_title_typing_effect != "true"){?>
			<div class="banner-title text-white"><span class="banner-title-inner"><?php echo apply_filters('argon_banner_title_html', $banner_title); ?></span>
			<?php echo get_option('argon_banner_subtitle') == '' ? '' : '<span class="banner-subtitle d-block">' . get_option('argon_banner_subtitle') . '</span>'; ?></div>
		<?php } else {?>
			<div class="banner-title text-white" data-interval="<?php echo get_option('argon_banner_typing_effect_interval', 100); ?>"><span data-text="<?php echo $banner_title; ?>" class="banner-title-inner">&nbsp;</span>
			<?php echo get_option('argon_banner_subtitle') == '' ? '' : '<span data-text="' . get_option('argon_banner_subtitle') . '" class="banner-subtitle d-block">&nbsp;</span>'; ?></div>
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
