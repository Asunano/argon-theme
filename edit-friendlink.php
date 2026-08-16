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

	// wp_links 无 meta，可管理 token 存放于选项 argon_fl_link_tokens[link_id]
	if (!function_exists('wp_update_link')){
		require_once ABSPATH . 'wp-admin/includes/bookmark.php';
	}

	$id = intval($_REQUEST['link'] ?? -1);
	$token = $_REQUEST['token'] ?? '';
	$error = '';
	$success = '';

	$fl_tokens = get_option('argon_fl_link_tokens', array());
	$valid_token = isset($fl_tokens[$id]) ? $fl_tokens[$id]['token'] : '';

	// 校验
	if ($id == -1){
		$error = __('参数错误：缺少友链标识', 'argon');
	}
	else if (get_bookmark($id) == null){
		$error = __('友链 #', 'argon') . $id . __(' 不存在', 'argon');
	}
	else if ($token === '' || $token !== $valid_token){
		$error = __('管理链接无效或已失效', 'argon');
	}

	// 提交保存
	if ($error === '' && ($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['argon_fl_save'])){
		if ($token !== ($_POST['token'] ?? '')){
			$error = __('管理链接无效或已失效', 'argon');
		} else {
			$name     = sanitize_text_field($_POST['argon_fl_name'] ?? '');
			$url      = esc_url_raw(trim($_POST['argon_fl_url'] ?? ''));
			$image    = esc_url_raw(trim($_POST['argon_fl_image'] ?? ''));
			$desc     = sanitize_textarea_field($_POST['argon_fl_desc'] ?? '');
			$linkpage = esc_url_raw(trim($_POST['argon_fl_linkpage'] ?? ''));
			if ($name === '' || $url === '' || !wp_http_validate_url($url)){
				$error = __('请填写有效的站点名称与网站 URL', 'argon');
			} else if ($linkpage !== '' && !wp_http_validate_url($linkpage)){
				$error = __('友链页 URL 不合法', 'argon');
			} else {
				wp_insert_link(array(
					'link_id'          => $id,
					'link_name'        => $name,
					'link_url'         => $url,
					'link_description' => $desc,
					'link_image'       => $image,
				));
				$fl_tokens = get_option('argon_fl_link_tokens', array());
				if (isset($fl_tokens[$id])){
					$fl_tokens[$id]['linkpage'] = $linkpage;
					update_option('argon_fl_link_tokens', $fl_tokens);
				}
				$success = __('友链信息已更新，将在友链界面立即生效。', 'argon');
			}
		}
	}

	// 准备表单初值
	$link = ($error === '') ? get_bookmark($id) : null;
	$l_name     = $link ? $link->link_name : '';
	$l_url      = $link ? $link->link_url : '';
	$l_desc     = $link ? $link->link_description : '';
	$l_image    = $link ? $link->link_image : '';
	$l_linkpage = isset($fl_tokens[$id]['linkpage']) ? $fl_tokens[$id]['linkpage'] : '';

	$page_title = __('管理我的友链', 'argon');
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
			<div class="card main-card shadow" style="width: 640px; max-width: calc(100vw - 40px);">
				<?php if ($error !== ''): ?>
					<div class="display-3 text-black"><i class='fa fa-close' style='color:#f5365c; margin-right:12px;'></i><?php _e('无法管理', 'argon'); ?></div>
					<p class="lead text-black"><?php echo $error; ?></p>
				<?php else: ?>
					<h2 class="display-3 text-black"><i class='fa fa-link' style='color:#5e72e4; margin-right:12px;'></i><?php _e('管理我的友链', 'argon'); ?></h2>
					<?php if ($success !== ''): ?>
						<div class="alert alert-success"><?php echo $success; ?></div>
					<?php endif; ?>
					<?php if (function_exists('argon_fl_get_link_status')):
						$st = argon_fl_get_link_status($id);
						if (!empty($st)):
							$slabels = array('mutual' => __('已互链', 'argon'), 'none' => __('未检测到互链', 'argon'), 'error' => __('检查失败', 'argon'));
							$scolors = array('mutual' => '#2dce89', 'none' => '#f5a623', 'error' => '#f5365c');
							$sv = isset($st['status']) ? $st['status'] : '';
							$shtml = isset($slabels[$sv]) ? $slabels[$sv] : __('未检查', 'argon');
							$scolor = isset($scolors[$sv]) ? $scolors[$sv] : '#8898aa';
					?>
						<div class="alert alert-info" style="font-size:14px;">
							<?php _e('互链状态：', 'argon'); ?><strong style="color:<?php echo $scolor; ?>;"><?php echo $shtml; ?></strong>
							<?php if (!empty($st['checked_url'])): ?><br><small><?php _e('检查地址：', 'argon'); ?> <?php echo esc_html($st['checked_url']); ?></small><?php endif; ?>
							<br>
							<button type="button" class="btn btn-sm btn-outline-primary" id="fl_recheck_btn" data-id="<?php echo (int) $id; ?>" data-token="<?php echo esc_attr($token); ?>" data-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('argon_ajax_action')); ?>"><?php _e('重新检查', 'argon'); ?></button>
							<span id="fl_recheck_result" style="margin-left:8px;"></span>
						</div>
					<?php endif; endif; ?>
					<form method="post" action="">
						<input type="hidden" name="link" value="<?php echo $id; ?>">
						<input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
						<input type="hidden" name="argon_fl_save" value="1">
						<div class="form-group">
							<label><?php _e('站点名称', 'argon'); ?></label>
							<input type="text" class="form-control" name="argon_fl_name" value="<?php echo esc_attr($l_name); ?>" required>
						</div>
						<div class="form-group">
							<label><?php _e('网站 URL', 'argon'); ?></label>
							<input type="url" class="form-control" name="argon_fl_url" value="<?php echo esc_attr($l_url); ?>" required placeholder="https://">
						</div>
						<div class="form-group">
							<label><?php _e('站点图 URL（可选）', 'argon'); ?></label>
							<input type="url" class="form-control" name="argon_fl_image" value="<?php echo esc_attr($l_image); ?>" placeholder="https://">
						</div>
						<div class="form-group">
							<label><?php _e('描述（可选）', 'argon'); ?></label>
							<textarea class="form-control" name="argon_fl_desc" rows="3"><?php echo esc_textarea($l_desc); ?></textarea>
						</div>
						<div class="form-group">
							<label><?php _e('友链页 URL（可选，用于自动互链检查）', 'argon'); ?></label>
							<input type="url" class="form-control" name="argon_fl_linkpage" value="<?php echo esc_attr($l_linkpage); ?>" placeholder="https://">
						</div>
						<button type="submit" class="btn btn-primary"><?php _e('保存修改', 'argon'); ?></button>
						<a class="btn btn-link" href="<?php echo esc_url(home_url('/')); ?>"><?php _e('返回首页', 'argon'); ?></a>
					</form>
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
		transform: translate(-50%, calc(-50% - 60px));
	}
</style>
<script>
	(function(){
		var btn = document.getElementById('fl_recheck_btn');
		if (!btn) return;
		btn.addEventListener('click', function(){
			var out = document.getElementById('fl_recheck_result');
			btn.disabled = true;
			out.textContent = '<?php echo esc_js(__('检查中…', 'argon')); ?>';
			var fd = new FormData();
			fd.append('action', 'argon_fl_recheck');
			fd.append('link_id', btn.getAttribute('data-id'));
			fd.append('token', btn.getAttribute('data-token'));
			fd.append('argon_ajax_nonce', btn.getAttribute('data-nonce'));
			fetch(btn.getAttribute('data-ajaxurl'), { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(res){
					btn.disabled = false;
					out.textContent = (res && res.result) ? res.result : '<?php echo esc_js(__('检查失败', 'argon')); ?>';
					if (res && res.status === 'ok') setTimeout(function(){ location.reload(); }, 1200);
				})
				.catch(function(){
					btn.disabled = false;
					out.textContent = '<?php echo esc_js(__('检查失败', 'argon')); ?>';
				});
		});
	})();
</script>
