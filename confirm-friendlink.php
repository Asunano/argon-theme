<?php
	$argon_wp_load_dir = dirname( __FILE__ );
	while ( ! file_exists( $argon_wp_load_dir . '/wp-load.php' ) ) {
		if ( $argon_wp_load_dir === dirname( $argon_wp_load_dir ) ) {
			break;
		}
		$argon_wp_load_dir = dirname( $argon_wp_load_dir );
	}
	if ( file_exists( $argon_wp_load_dir . '/wp-load.php' ) ) {
		require_once( $argon_wp_load_dir . '/wp-load.php' );
	} else {
		status_header( 500 );
		exit( 'WordPress 环境未找到' );
	}
	header('HTTP/1.1 200 OK');

	$token = isset($_REQUEST['t']) ? trim((string) $_REQUEST['t']) : '';
	$error = '';
	$submitted = false;

	// 防重放：已成功提交过的链接，二次点击提示「已提交」
	if ($token !== '' && get_transient('argon_fl_done_' . $token)){
		$submitted = true; // 复用成功页，仅文案提示已提交
		$already_done = true;
	}

	// 校验 token 并取暂存的提交数据（1 小时有效）
	if (!$submitted){
		$pending = ($token !== '') ? get_transient('argon_fl_pending_' . $token) : false;
		if ($token === ''){
			$error = __('参数错误：缺少验证标识', 'argon');
		} else if (!is_array($pending) || empty($pending)){
			$error = __('验证链接无效或已过期，请重新提交申请。', 'argon');
		}

		// 校验通过：正式创建待审申请 + 防重放（立即删除 transient）
		if ($error === '' && function_exists('argon_fl_create_pending')){
			$comment_id = argon_fl_create_pending($pending);
			delete_transient('argon_fl_pending_' . $token);
			if (is_wp_error($comment_id) || empty($comment_id)){
				$error = __('提交失败，请稍后重试或重新提交申请。', 'argon');
			} else {
				$submitted = true;
				// 短期「已提交」标记，供二次点击提示
				set_transient('argon_fl_done_' . $token, 1, 10 * MINUTE_IN_SECONDS);
				if (function_exists('argon_fl_notify')){
					argon_fl_notify('submitted', $pending);
				}
			}
		} else if ($error === ''){
			$error = __('主题功能异常，请联系站点管理员。', 'argon');
		}
	}

	$page_title = __('友链申请提交成功', 'argon');
?>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
	<link href="<?php bloginfo('template_url'); ?>/assets/vendor/nucleo/css/nucleo.css" rel="stylesheet">
	<link href="<?php bloginfo('template_url'); ?>/assets/vendor/font-awesome/css/font-awesome.min.css" rel="stylesheet">
	<link type="text/css" href="<?php bloginfo('template_url'); ?>/assets/css/argon.min.css" rel="stylesheet">
	<script src="<?php bloginfo('template_url'); ?>/assets/vendor/jquery/jquery.min.js"></script>
	<script src="<?php bloginfo('template_url'); ?>/assets/vendor/bootstrap/bootstrap.min.js"></script>
	<script src="<?php bloginfo('template_url'); ?>/assets/js/argon.min.js"></script>
	<title><?php echo $page_title; ?></title>
</head>
<body>
	<div class="position-relative">
		<section class="section section-lg section-shaped pb-250" style="min-height: 100vh !important;">
			<div class="shape shape-style-1 shape-default">
				<span></span><span></span><span></span><span></span><span></span>
				<span></span><span></span><span></span><span></span>
			</div>
			<div class="card main-card shadow" style="width: 520px; max-width: calc(100vw - 40px);">
				<?php if ($submitted): ?>
					<div class="text-center" style="padding: 18px 0 6px;">
						<svg width="84" height="84" viewBox="0 0 84 84" fill="none" aria-hidden="true">
							<circle cx="42" cy="42" r="38" stroke="#2dce89" stroke-width="4" stroke-linecap="round" class="ck-circle"/>
							<path d="M27 43 L38 54 L58 32" stroke="#2dce89" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" class="ck-check"/>
						</svg>
					</div>
					<h2 class="display-3 text-black text-center" style="font-size: 26px;"><i class='fa fa-link' style='color:#5e72e4; margin-right:8px;'></i><?php echo !empty($already_done) ? __('该申请已提交，无需重复确认', 'argon') : __('提交成功，等待管理员审核', 'argon'); ?></h2>
					<p class="lead text-black text-center" style="font-size: 15px;"><?php echo !empty($already_done) ? __('您的友链申请此前已确认并提交，请等待管理员审核。', 'argon') : __('您的友链申请已确认并提交，审核通过后会通过邮件通知您，并附上可自助修改友链信息的管理链接。', 'argon'); ?></p>
					<p class="text-black text-center"><a class="btn btn-primary" href="<?php echo esc_url(home_url('/')); ?>"><?php _e('返回首页', 'argon'); ?></a></p>
				<?php else: ?>
					<div class="display-3 text-black"><i class='fa fa-close' style='color:#f5365c; margin-right:12px;'></i><?php _e('无法确认', 'argon'); ?></div>
					<p class="lead text-black"><?php echo $error; ?></p>
					<p class="text-black"><a class="btn btn-link" href="<?php echo esc_url(home_url('/')); ?>"><?php _e('返回首页', 'argon'); ?></a></p>
				<?php endif; ?>
			</div>
		</section>
	</div>
</body>
</html>

<style>
	body{ overflow: hidden; }
	.main-card{
		margin: auto;
		padding: 40px 50px;
		position: fixed;
		left: 50vw;
		top: 50vh;
		transform: translate(-50%, -50%);
	}
	/* 提交成功动画：圆描边 + 对勾渐显（纯 CSS，不依赖主题 JS 框架） */
	.ck-circle{ stroke-dasharray: 240; stroke-dashoffset: 240; animation: ck-draw .6s ease-out forwards; }
	.ck-check{ stroke-dasharray: 60; stroke-dashoffset: 60; animation: ck-draw .45s ease-out .5s forwards; }
	@keyframes ck-draw{ to{ stroke-dashoffset: 0; } }
	@media (prefers-reduced-motion: reduce){
		.ck-circle, .ck-check{ stroke-dashoffset: 0; animation: none; }
	}
</style>
