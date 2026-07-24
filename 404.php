<?php
/**
 * 404 模板（独立文档，参考 solstice23 原版 argon 实现）。
 *
 * 设计要点：
 * - 不调用 get_header()/get_footer()，自成一个完整的 <html> 文档，
 *   避免站点 banner / 预加载遮罩 / #content 包裹导致的「双重英雄区」「渐变铺不满」问题。
 * - 英雄区结构与原版一致：section.section-shaped(height:100vh) > shape.shape-default(铺满渐变) + container。
 *   container 是 shape 的直接兄弟，命中 .section-shaped .shape+.container{height:100%}，
 *   渐变与高度均正常；container 用 d-flex align-items-center 使 404 文案「左-中」对齐。
 * - 深色模式：内联无 jQuery 的早判定脚本（与 header.php 一致），并复用原版的深色微调样式。
 */
?>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php _e("404 - 找不到页面", "argon"); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php if (get_option('argon_disable_googlefont') != 'true') { ?>
		<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=swap" rel="stylesheet">
	<?php } ?>
	<link href="<?php echo get_template_directory_uri(); ?>/assets/vendor/nucleo/css/nucleo.css" rel="stylesheet">
	<link href="<?php echo get_template_directory_uri(); ?>/assets/vendor/font-awesome/css/font-awesome.min.css" rel="stylesheet">
	<link type="text/css" href="<?php echo get_template_directory_uri(); ?>/assets/argon_css_merged.css" rel="stylesheet">
	<link type="text/css" href="<?php echo get_template_directory_uri(); ?>/style.css" rel="stylesheet">
	<script>
		/* 早期(无 jQuery 依赖)依据存储/选项把深浅类加到 <html>，与 header.php 保持一致 */
		(function(){
			try{
				var html = document.documentElement;
				var auto = "<?php echo (get_option('argon_darkmode_autoswitch') == '' ? 'false' : get_option('argon_darkmode_autoswitch'));?>";
				var s = sessionStorage.getItem('Argon_Enable_Dark_Mode');
				var dark = false;
				if (s === 'true'){ dark = true; }
				else if (s === 'false'){ dark = false; }
				else if (auto === 'alwayson'){ dark = true; }
				else if (auto === 'system'){ dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; }
				else if (auto === 'time'){ var h = new Date().getHours(); dark = (<?php echo apply_filters('argon_darkmode_time_check', 'hour < 7 || hour >= 22');?>); }
				if (dark){ html.classList.add('darkmode'); }
				var a = localStorage.getItem('Argon_Enable_Amoled_Dark_Mode');
				if (a === 'true'){ html.classList.add('amoled-dark'); }
				else if (a === 'false'){ html.classList.remove('amoled-dark'); }
			}catch(e){}
		})();
		document.documentElement.classList.remove("no-js");
	</script>
</head>
<body>
	<div class="position-relative">
		<section class="section section-lg section-shaped" style="height: 100vh !important;">
			<div class="shape shape-style-1 shape-default">
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
			<div class="container py-lg-md d-flex align-items-center" style="min-height: 100vh;">
				<div class="col px-0">
					<div class="row">
						<div class="col-lg-6 col-sm-12">
							<div class="display-1 text-white">404</div>
							<p class="lead text-white">Page not found.<br><?php _e("这个页面不见了", "argon"); ?></p>
							<div class="btn-wrapper">
								<a href="javascript:window.history.back(-1);" ondragstart="return false;" class="btn btn-info btn-icon mb-3 mb-sm-0">
									<span class="btn-inner--icon"><i class="fa fa-chevron-left"></i></span>
									<span class="btn-inner--text"><?php _e("返回上一页", "argon"); ?></span>
								</a>
								<a href="<?php bloginfo('url'); ?>" class="btn btn-white btn-icon mb-3 mb-sm-0">
									<span class="btn-inner--icon"><i class="fa fa-home"></i></span>
									<span class="btn-inner--text"><?php _e("回到首页", "argon"); ?></span>
								</a>
							</div>
							<?php echo apply_filters('argon_404page_extra_html', ''); ?>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>
	<style>
		body{
			overflow: hidden;
		}
		html.darkmode .section-shaped .shape {
			background: #262626;
		}
		html.darkmode .text-white {
			opacity: .75;
		}
		html.darkmode .btn-white {
			background: #424242;
			border-color: #424242;
			color: #eee;
		}
		html.darkmode .btn-info {
			background: #0a7f94;
			border-color: #0a7f94;
			color: #eee;
		}
		html.darkmode.amoled-dark .section-shaped .shape {
			background: #000;
		}
	</style>
</body>
</html>
