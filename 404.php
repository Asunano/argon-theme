<?php
/**
 * 404 模板：复用主题 header（从而获得预加载遮罩与一致的样式/资源），
 * 不调用 get_footer() 以避免在本页加载 argontheme.js / Pjax（原 404 页亦无 footer）。
 * 预加载遮罩的隐藏逻辑内置于 header.php，独立于 footer 脚本，故仍正常生效。
 */
get_header();
?>
<div class="position-relative">
	<section class="section section-lg section-shaped pb-250" style="height: 100vh !important;">
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
		<div class="container py-lg-md d-flex">
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
