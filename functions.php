<?php
if (version_compare( $GLOBALS['wp_version'], '4.4-alpha', '<' )) {
	echo "<div style='background: #5e72e4;color: #fff;font-size: 30px;padding: 50px 30px;position: fixed;width: 100%;left: 0;right: 0;bottom: 0;z-index: 2147483647;'>" . __("Argon 主题不支持 Wordpress 4.4 以下版本，请更新 Wordpress", 'argon') . "</div>";
}

/**
 * 带请求内静态缓存的 get_option 封装，避免同一请求中重复读取同一选项造成的多次数据库查询。
 * 仅当选项确实存在（返回值不等于默认值）时才写入缓存，避免不同默认值的调用相互污染。
 */
function argon_get_option($option, $default = false) {
	static $argon_option_cache = array();
	if (!array_key_exists($option, $argon_option_cache)) {
		$value = get_option($option, $default);
		if ($value !== $default) {
			$argon_option_cache[$option] = $value;
		}
		return $value;
	}
	return $argon_option_cache[$option];
}
function theme_slug_setup() {
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	// 启用 WP 官方区块样式：经典主题需显式声明，否则 wp-block-library-theme 不会在前台入队，
	// 画廊/封面等区块的 is-layout-grid 等布局缺少列模板而失效。配合 argon_enqueue_block_library_always() 在 Pjax 下常驻。
	add_theme_support('wp-block-styles');
	load_theme_textdomain('argon', get_template_directory() . '/languages');
}
add_action('after_setup_theme','theme_slug_setup');

// 条件加载的 vendor 脚本以 defer 加载，解除 <head> 渲染阻塞。
// argon_js_merged 不在其中(见下方注释)：它同步于 <head> 输出，作为全局基础包。
add_filter('script_loader_tag', function ($tag, $handle) {
	// 条件加载的 vendor 脚本统一以 defer 加载，解除 <head> 渲染阻塞。
	// 注意：argon_js_merged 不能 defer —— 它在全局定义 jQuery($) 与 socialShare，
	// 而 footer 的 argonjs 与正文内联脚本(如 share.php 的 socialShare(...))在解析期同步执行，
	// 早于 defer 脚本，defer 会导致 "$ is not defined" / "socialShare is not defined"。
	// 其余脚本均在 argontheme.js(pjax:complete) 中按需调用，延迟至文档解析后执行无副作用。
	if (in_array($handle, array('fancybox5', 'highlight', 'highlight-ln', 'nouislider', 'pickr'), true)) {
		return str_replace(' src=', ' defer src=', $tag);
	}
	return $tag;
}, 10, 2);

// 仅 googlefont 异步加载（media=print + onload 切回 all），其余样式保持渲染阻塞，
// 保证首屏必定已样式化、绝不出现整页无样式(空白/异常)。预加载遮罩的瞬时出现由
// 内联样式 + 遮罩自身不依赖外部 CSS 来保证；关键 CSS 渲染阻塞可彻底避免无样式闪烁。
// FA6 两个 CSS 同样异步：首屏主题图标由合并包内 FA4（同步）保证，FA6 仅覆盖/补充
// 文章正文用到的更多图标，异步延迟渲染无感知，可省去 ~130KB 首屏阻塞 CSS。
add_filter('style_loader_tag', function ($tag, $handle) {
	if (in_array($handle, array('googlefont', 'font-awesome-full', 'font-awesome-shims'), true)) {
		$deferred = str_replace("media='all'", "media='print' onload=\"this.media='all'\"", $tag);
		return $deferred . '<noscript>' . $tag . '</noscript>';
	}
	return $tag;
}, 10, 2);

$argon_version = !(wp_get_theme() -> Template) ? wp_get_theme() -> Version : wp_get_theme(wp_get_theme() -> Template) -> Version;
$GLOBALS['theme_version'] = $argon_version;
$argon_assets_path = get_option("argon_assets_path");
switch ($argon_assets_path) {
    case "jsdelivr":
	    $GLOBALS['assets_path'] = "https://cdn.jsdelivr.net/gh/Asunano/argon-theme@" . $argon_version;
        break;
    case "sourcegcdn":
	    $GLOBALS['assets_path'] = "https://gh.sourcegcdn.com/Asunano/argon-theme/v" . $argon_version;
        break;
	case "jsdelivr_gcore":
	    $GLOBALS['assets_path'] = "https://gcore.jsdelivr.net/gh/Asunano/argon-theme@" . $argon_version;
        break;
	case "jsdelivr_fastly":
	    $GLOBALS['assets_path'] = "https://fastly.jsdelivr.net/gh/Asunano/argon-theme@" . $argon_version;
        break;
	case "jsdelivr_cf":
	    $GLOBALS['assets_path'] = "https://testingcf.jsdelivr.net/gh/Asunano/argon-theme@" . $argon_version;
        break;
	case "custom":
		$GLOBALS['assets_path'] = preg_replace('/\/$/', '', get_option("argon_custom_assets_path"));
		$GLOBALS['assets_path'] = preg_replace('/%theme_version%/', $argon_version, $GLOBALS['assets_path']);
		break;
    default:
	    $GLOBALS['assets_path'] = get_bloginfo('template_url');
}

//翻译 Hook
function argon_locate_filter($locate){
	if (substr($locate, 0, 2) == 'zh'){
		if ($locate == 'zh_TW'){
			return $locate;
		}
		return 'zh_CN';
	}
	if (substr($locate, 0, 2) == 'en'){
		return 'en_US';
	}
	if (substr($locate, 0, 2) == 'ru'){
		return 'ru_RU';
	}
	return 'en_US';
}
function argon_get_locate(){
	if (function_exists("determine_locale")){
		return argon_locate_filter(determine_locale());
	}
	$determined_locale = get_locale();
	if (is_admin()){
		$determined_locale = get_user_locale();
	}
	return $determined_locale;
}
function theme_locale_hook($locate, $domain){
	if ($domain == 'argon'){
		return argon_locate_filter($locate);
	}
	return $locate;
}
add_filter('theme_locale', 'theme_locale_hook', 10, 2);

//更新主题版本后的兼容
$argon_last_version = get_option("argon_last_version");
if ($argon_last_version == ""){
	$argon_last_version = "0.0";
}
if (version_compare($argon_last_version, $GLOBALS['theme_version'], '<' )){
	if (version_compare($argon_last_version, '0.940', '<')){
		if (get_option('argon_mathjax_v2_enable') == 'true' && get_option('argon_mathjax_enable') != 'true'){
			update_option("argon_math_render", 'mathjax2');
		}
		if (get_option('argon_mathjax_enable') == 'true'){
			update_option("argon_math_render", 'mathjax3');
		}
	}
	if (version_compare($argon_last_version, '0.970', '<')){
		if (get_option('argon_show_author') == 'true'){
			update_option("argon_article_meta", 'time|views|comments|categories|author');
		}
	}
	if (version_compare($argon_last_version, '1.1.0', '<')){
		if (get_option('argon_enable_zoomify') != 'false'){
			update_option("argon_enable_fancybox", 'true');
			update_option("argon_enable_zoomify", 'false');
		}
	}
	if (version_compare($argon_last_version, '1.3.4', '<')){
		switch (get_option('argon_search_post_filter', 'post,page')){
			case 'post,page':
				update_option("argon_enable_search_filters", 'true');
				update_option("argon_search_filters_type", '*post,*page,shuoshuo');
				break;
			case 'post,page,shuoshuo':
				update_option("argon_enable_search_filters", 'true');
				update_option("argon_search_filters_type", '*post,*page,*shuoshuo');
				break;
			case 'post,page,hide_shuoshuo':
				update_option("argon_enable_search_filters", 'true');
				update_option("argon_search_filters_type", '*post,*page');
				break;
			case 'off':
			default:
				update_option("argon_enable_search_filters", 'false');
				break;
		}		
	}
	update_option("argon_last_version", $GLOBALS['theme_version']);
}


//检测更新
require_once(get_template_directory() . '/theme-update-checker/plugin-update-checker.php');
$argon_update_source = get_option('argon_update_source');
switch ($argon_update_source) {
	case "stop":
		break;
	case "ghproxy":
		/* 镜像源：在原地址前加 https://v4.gh-proxy.org/ 转发 */
		$argonThemeUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
			'https://v4.gh-proxy.org/https://raw.githubusercontent.com/Asunano/argon-theme/master/version.json',
			get_template_directory() . '/functions.php',
			'argon'
		);
		break;
	case "github":
    default:
		/* 本分支（Asunano/argon-theme）自有更新源：仓库根目录的 version.json（master 分支），由 release 工作流在发版时自动同步 */
		$argonThemeUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
			'https://raw.githubusercontent.com/Asunano/argon-theme/master/version.json',
			get_template_directory() . '/functions.php',
			'argon'
		);
}

//初次使用时发送安装量统计信息 (数据仅用于统计安装量)
function post_analytics_info(){
	if(function_exists('file_get_contents')){
		$contexts = stream_context_create(
			array(
				'https' => array(
					'method'=>"GET",
					'header'=>"User-Agent: ArgonTheme\r\n"
				)
			)
		);
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
		$host = preg_replace('/[^a-zA-Z0-9.\-]/', '', $host); // 仅允许域名合法字符，防 Host header 注入
		$result = file_get_contents('https://api.solstice23.top/argon_analytics/index.php?domain=' . urlencode($host) . '&version='. urlencode($GLOBALS['theme_version']), false, $contexts);
		update_option('argon_has_inited', 'true');
		return $result;
	}else{
		update_option('argon_has_inited', 'true');
	}
}
if (get_option('argon_has_inited') != 'true'){
	post_analytics_info();
}
//时区修正
if (get_option('argon_enable_timezone_fix') == 'true'){
	date_default_timezone_set('UTC');
}
//注册小工具
function argon_widgets_init() {
	register_sidebar(
		array(
			'name'          => __('左侧栏小工具', 'argon'),
			'id'            => 'leftbar-tools',
			'description'   => __( '左侧栏小工具 (如果设置会在侧栏增加一个 Tab)', 'argon'),
			'before_widget' => '<div id="%1$s" class="widget %2$s card bg-white border-0">',
			'after_widget'  => '</div>',
			'before_title'  => '<h6 class="font-weight-bold text-black">',
			'after_title'   => '</h6>',
		)
	);
	register_sidebar(
		array(
			'name'          => __('右侧栏小工具', 'argon'),
			'id'            => 'rightbar-tools',
			'description'   => __( '右侧栏小工具 (在 "Argon 主题选项" 中选择 "三栏布局" 才会显示)', 'argon'),
			'before_widget' => '<div id="%1$s" class="widget %2$s card shadow-sm bg-white border-0">',
			'after_widget'  => '</div>',
			'before_title'  => '<h6 class="font-weight-bold text-black">',
			'after_title'   => '</h6>',
		)
	);
	register_sidebar(
		array(
			'name'          => __('站点概览额外内容', 'argon'),
			'id'            => 'leftbar-siteinfo-extra-tools',
			'description'   => __( '站点概览额外内容', 'argon'),
			'before_widget' => '<div id="%1$s" class="widget %2$s card bg-white border-0">',
			'after_widget'  => '</div>',
			'before_title'  => '<h6 class="font-weight-bold text-black">',
			'after_title'   => '</h6>',
		)
	);
}
add_action('widgets_init', 'argon_widgets_init');
//注册新后台主题配色方案
function argon_add_admin_color(){
	wp_admin_css_color(
		'argon',
		'Argon',
		get_bloginfo('template_directory') . "/admin.css",
		array("#5e72e4", "#324cdc", "#e8ebfb"),
		array('base' => '#525f7f', 'focus' => '#5e72e4', 'current' => '#fff')
	);
}
add_action('admin_init', 'argon_add_admin_color');
function argon_admin_themecolor_css(){
	$themecolor = get_option("argon_theme_color", "#5e72e4");
	if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themecolor)) {
		$themecolor = "#5e72e4";
	}
	$RGB = hexstr2rgb($themecolor);
	$HSL = rgb2hsl($RGB['R'], $RGB['G'], $RGB['B']);
	echo "
		<style id='themecolor_css'>
			:root{
				--themecolor: " . esc_attr($themecolor) . " ;
				--themecolor-R: " . esc_attr($RGB['R']) . " ;
				--themecolor-G: " . esc_attr($RGB['G']) . " ;
				--themecolor-B: " . esc_attr($RGB['B']) . " ;
				--themecolor-H: " . esc_attr($HSL['H']) . " ;
				--themecolor-S: " . esc_attr($HSL['S']) . " ;
				--themecolor-L: " . esc_attr($HSL['L']) . " ;
			}
		</style>
	";
	if (get_option("argon_enable_immersion_color", "false") == "true"){
		echo "<script> document.documentElement.classList.add('immersion-color'); </script>";
	}
}
add_filter('admin_head', 'argon_admin_themecolor_css');
function array_remove(&$arr, $item){
	$pos = array_search($item, $arr);
	if ($pos !== false){
		array_splice($arr, $pos, 1);
	}
}
//数字格式化
function format_number_in_kilos($number) {
	if ($number < 1000){
		return $number;
	}
	if (1000 <= $number && $number < 1000000){
		if (1000 <= $number && $number < 10000){
			return round($number / 1000, 1) . "K";
		}else{
			return round($number / 1000, 0) . "K";
		}
	}
	if (1000000 <= $number && $number <= 10000000){
		return round($number / 1000000, 1) . "M";
	}else{
		return round($number / 1000000, 0) . "M";
	}
}
//表情包
require_once(get_template_directory() . '/emotions.php');
//文章特色图片
function argon_get_first_image_of_article(){
	global $post;
	if (post_password_required()){
		return false;
	}
	$post_content_full = apply_filters('the_content', preg_replace( '<!--more(.*?)-->', '', $post -> post_content));
	preg_match('/<img(.*?)(src|data-original)=[\"\']((http:|https:)?\/\/(.*?))[\"\'](.*?)\/?>/', $post_content_full, $match);
	if (isset($match[3])){
		return $match[3];
	}
	return false;
}
function argon_has_post_thumbnail($postID = 0){
	if ($postID == 0){
		global $post;
		$postID = $post -> ID;
	}
	if (has_post_thumbnail()){
		return true;
	}
	$argon_first_image_as_thumbnail = get_post_meta($postID, 'argon_first_image_as_thumbnail', true);
	if ($argon_first_image_as_thumbnail == ""){
		$argon_first_image_as_thumbnail = "default";
	}
	if ($argon_first_image_as_thumbnail == "true" || ($argon_first_image_as_thumbnail == "default" && get_option("argon_first_image_as_thumbnail_by_default", "false") == "true")){
		if (argon_get_first_image_of_article() != false){
			return true;
		}
	}
	return false;
}
function argon_get_post_thumbnail($postID = 0){
	if ($postID == 0){
		global $post;
		$postID = $post -> ID;
	}
	if (has_post_thumbnail()){
		return apply_filters("argon_post_thumbnail", wp_get_attachment_image_src(get_post_thumbnail_id($postID), "full")[0]);
	}
	return apply_filters("argon_post_thumbnail", argon_get_first_image_of_article());
}
//文末附加内容
function get_additional_content_after_post(){
	global $post;
	$postID = $post -> ID;
	$res = get_post_meta($post -> ID, 'argon_after_post', true);
	if ($res == "--none--"){
		return "";
	}
	if ($res == ""){
		$res = get_option("argon_additional_content_after_post");
	}
	$res = str_replace("\n", "<br>", $res);
	$res = str_replace("%url%", get_permalink($postID), $res);
	$res = str_replace("%link%", '<a href="' . get_permalink($postID) . '" target="_blank">' . get_permalink($postID) . '</a>', $res);
	$res = str_replace("%title%", get_the_title(), $res);
	$res = str_replace("%author%", get_the_author(), $res);
	return wp_kses_post($res);
}
//输出分页页码
function get_argon_formatted_paginate_links($maxPageNumbers, $extraClasses = ''){
	$args = array(
		'prev_text' => '',
		'next_text' => '',
		'before_page_number' => '',
		'after_page_number' => '',
		'show_all' => True,
		'type' => 'array'
	);
	$links = paginate_links($args);
	if (empty($links)){
		return "";
	}
	//直接解析 paginate_links 返回的链接数组，避免对整段 HTML 做脆弱的正则匹配
	$pages = array(); // 页码 => array('url' => , 'current' => bool)
	foreach ($links as $link){
		if (preg_match('/class="[^"]*(prev|next)[^"]*"/', $link)){
			continue; // 跳过上一页/下一页
		}
		if (preg_match('/<span[^>]*class="[^"]*current[^"]*"[^>]*>(.*?)<\/span>/', $link, $m)){
			$num = (int) $m[1];
			$pages[$num] = array('url' => '', 'current' => true);
		}elseif (preg_match('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/', $link, $m)){
			$num = (int) $m[2];
			$pages[$num] = array('url' => $m[1], 'current' => false);
		}
	}
	$total = count($pages);
	if ($total == 0){
		return "";
	}
	$current = 0;
	foreach ($pages as $num => $p){
		if ($p['current']){
			$current = $num;
			break;
		}
	}
	if ($current == 0){
		return "";
	}

	//计算页码起始
	$from = max($current - ($maxPageNumbers - 1) / 2 , 1);
	$to = min($current + $maxPageNumbers - ( $current - $from + 1 ) , $total);
	if ($to - $from + 1 < $maxPageNumbers){
		$to = min($current + ($maxPageNumbers - 1) / 2 , $total);
		$from = max($current - ( $maxPageNumbers - ( $to - $current + 1 ) ) , 1);
	}
	//生成新页码
	$html = "";
	if ($from > 1 && isset($pages[1])){
		$html .= '<li class="page-item"><a aria-label="First Page" class="page-link" href="' . esc_url($pages[1]['url']) . '"><i class="fa fa-angle-double-left" aria-hidden="true"></i></a></li>';
	}
	if ($current > 1 && isset($pages[$current - 1])){
		$html .= '<li class="page-item"><a aria-label="Previous Page" class="page-link" href="' . esc_url($pages[$current - 1]['url']) . '"><i class="fa fa-angle-left" aria-hidden="true"></i></a></li>';
	}
	for ($i = $from; $i <= $to; $i++){
		if ($current == $i){
			$html .= '<li class="page-item active"><span class="page-link" style="cursor: default;">' . $i . '</span></li>';
		}else{
			$html .= '<li class="page-item"><a class="page-link" href="' . esc_url($pages[$i]['url']) . '">' . $i . '</a></li>';
		}
	}
	if ($current < $total && isset($pages[$current + 1])){
		$html .= '<li class="page-item"><a aria-label="Next Page" class="page-link" href="' . esc_url($pages[$current + 1]['url']) . '"><i class="fa fa-angle-right" aria-hidden="true"></i></a></li>';
	}
	if ($to < $total && isset($pages[$total])){
		$html .= '<li class="page-item"><a aria-label="Last Page" class="page-link" href="' . esc_url($pages[$total]['url']) . '"><i class="fa fa-angle-double-right" aria-hidden="true"></i></a></li>';
	}
	return '<nav><ul class="pagination' . $extraClasses . '">' . $html . '</ul></nav>';
}
function get_argon_formatted_paginate_links_for_all_platforms(){
	return get_argon_formatted_paginate_links(7) . get_argon_formatted_paginate_links(5, " pagination-mobile");
}
//访问者 Token & Session
function get_random_token(){
	return md5(uniqid(microtime(true), true));
}
function set_user_token_cookie(){
	if (!isset($_COOKIE["argon_user_token"]) || strlen($_COOKIE["argon_user_token"]) != 32){
		$newToken = get_random_token();
		$cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
		setcookie("argon_user_token", $newToken, time() + 10 * 365 * 24 * 60 * 60, "/", $cookie_domain, is_ssl(), true);
		$_COOKIE["argon_user_token"] = $newToken;
	}
}
// 访问者 Token 初始化（验证码已改为无状态，不再需要 PHP Session / 会话文件锁）
function argon_visitor_init(){
	set_user_token_cookie();
}
add_action('wp', 'argon_visitor_init');
//页面 Description Meta
function get_seo_description(){
	global $post;
	if (is_single() || is_page()){
		if (get_the_excerpt() != ""){
			return preg_replace('/ \[&hellip;]$/', '&hellip;', get_the_excerpt());
		}
		if (!post_password_required()){
			return htmlspecialchars(mb_substr(str_replace("\n", '', strip_tags($post -> post_content)), 0, 50)) . "...";
		}else{
			return __("这是一个加密页面，需要密码来查看", 'argon');
		}
	}else{
		$desc = get_option('argon_seo_description');
		if ($desc == ''){
			$desc = get_bloginfo('description');
		}
		return $desc;
	}
}
//结构化数据 (JSON-LD)
function argon_output_structured_data(){
	if (argon_get_option('argon_enable_structured_data') == 'false'){
		return;
	}
	if (!is_singular('post')){
		return;
	}
	global $post;
	$schema = array(
		'@context' => 'https://schema.org',
		'@type' => 'Article',
		'@id' => get_permalink($post -> ID) . '#article',
		'headline' => get_the_title($post -> ID),
		'datePublished' => get_the_date('c', $post -> ID),
		'dateModified' => get_the_modified_date('c', $post -> ID),
		'author' => array(
			'@type' => 'Person',
			'name' => get_the_author_meta('display_name', $post -> post_author),
			'url' => get_author_posts_url($post -> post_author),
		),
		'publisher' => array(
			'@type' => 'Organization',
			'name' => get_bloginfo('name'),
		),
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id' => get_permalink($post -> ID),
		),
	);
	$thumbnail_id = get_post_thumbnail_id($post -> ID);
	if ($thumbnail_id){
		$thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
		if ($thumbnail_url){
			$schema['image'] = array(
				'@type' => 'ImageObject',
				'url' => $thumbnail_url,
				'width' => 1200,
				'height' => 800,
			);
			$schema['publisher']['logo'] = array(
				'@type' => 'ImageObject',
				'url' => $thumbnail_url,
			);
		}
	}
	$excerpt = get_the_excerpt($post -> ID);
	if ($excerpt){
		$schema['description'] = wp_strip_all_tags($excerpt);
	}
	echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
add_action('wp_head', 'argon_output_structured_data');
//页面 Keywords
function get_seo_keywords(){
	if (is_single()){
		global $post;
		$tags = get_the_tags('', ',', '', $post -> ID);
		if ($tags != null){
			$res = "";
			foreach ($tags as $tag){
				if ($res != ""){
					$res .= ",";
				}
				$res .= $tag -> name;
			}
			return $res;
		}
	}
	if (is_category()){
		return single_cat_title('', false);
	}
	if (is_tag()){
		return single_tag_title('', false);
	}
	if (is_author()){
		return get_the_author();
	}
	if (is_post_type_archive()){
		return post_type_archive_title('', false);
	}
	if (is_tax()){
		return single_term_title('', false);
	}
	return get_option('argon_seo_keywords');
}
//页面分享预览图
function get_og_image(){
	global $post;
	$postID = $post -> ID;
	$argon_first_image_as_thumbnail = get_post_meta($postID, 'argon_first_image_as_thumbnail', true);
	if (has_post_thumbnail() || $argon_first_image_as_thumbnail == 'true'){
		return argon_get_post_thumbnail($postID);
	}
	return '';
}
// 判断头像 URL 是否来自已知失效的默认头像服务（如旧版 qiniu 头像，已 404）
function argon_is_invalid_avatar($url){
	return $url == '' || strpos($url, 'dn-qiniu-avatar.qbox.me') !== false || strpos($url, 'qbox.me/avatar') !== false;
}
//社交分享图（SEO/OG/Twitter 共用）：优先文章特色图/首图，其次站点 OG 封面图，
//再次作者/管理员头像（过滤失效头像源），再次站点图标，最终回退到主题默认封面图，
//确保 og:image 始终指向有效图片，避免社交平台抓取无图
function argon_get_social_image(){
	$cover = get_option('argon_og_cover_image', '');
	$site_icon = get_site_icon_url(300);
	if (is_singular() && !is_front_page()){
		$img = get_og_image();
		if ($img != ''){
			return $img;
		}
		if ($cover != ''){
			return $cover;
		}
		global $post;
		$avatar = get_avatar_url($post -> post_author, array('size' => 300));
		if (!argon_is_invalid_avatar($avatar)){
			return $avatar;
		}
		if ($site_icon != ''){
			return $site_icon;
		}
	}
	if ($cover != ''){
		return $cover;
	}
	$admins = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID'));
	if (!empty($admins)){
		$avatar = get_avatar_url($admins[0] -> ID, array('size' => 300));
		if (!argon_is_invalid_avatar($avatar)){
			return $avatar;
		}
	}
	if ($site_icon != ''){
		return $site_icon;
	}
	return get_template_directory_uri() . '/screenshot.png';
}
// 取站内图片尺寸（仅本地路径，避免远程 getimagesize 的耗时与失败），
// 用于补充 og:image:width / og:image:height，提升社交平台大图卡片渲染可靠性
function argon_get_image_size($url){
	if (!function_exists('getimagesize')){
		return false;
	}
	$size = false;
	if (strpos($url, home_url()) === 0){
		$path = str_replace(home_url('/'), trailingslashit(ABSPATH), $url);
		if (@file_exists($path)){
			$size = @getimagesize($path);
		}
	}elseif (strpos($url, get_template_directory_uri()) === 0){
		$path = str_replace(get_template_directory_uri(), get_template_directory(), $url);
		if (@file_exists($path)){
			$size = @getimagesize($path);
		}
	}
	return $size ? $size : false;
}

//动态输出 Web App Manifest（复用站点名称、Description Meta 与主题色、站点图标）
function argon_manifest_query_vars($vars){
	$vars[] = 'argon_manifest';
	return $vars;
}
add_filter('query_vars', 'argon_manifest_query_vars');

function argon_output_manifest(){
	if (get_query_var('argon_manifest') !== '1'){
		return;
	}
	header('Content-Type: application/manifest+json');
	$name = get_bloginfo('name');
	$description = get_seo_description();
	if ($description == ''){
		$description = get_bloginfo('description');
	}
	$themecolor = get_option('argon_theme_color', '#5e72e4');
	$icon = get_site_icon_url();
	$manifest = array(
		'name' => $name,
		'short_name' => $name,
		'description' => $description,
		'start_url' => home_url('/'),
		'display' => 'standalone',
		'background_color' => '#ffffff',
		'theme_color' => $themecolor,
	);
	if ($icon != ''){
		$manifest['icons'] = array(
			array(
				'src' => $icon,
				'sizes' => '512x512',
				'type' => 'image/png',
				'purpose' => 'any'
			)
		);
	}
	echo json_encode($manifest);
	exit;
}
add_action('template_redirect', 'argon_output_manifest');

//Enhanced 视觉类功能：通过 body class 控制 CSS 开关（文章图片悬浮放大 / 滚动模糊）
function argon_enhanced_body_class($classes){
	if (get_option('argon_enable_image_hover', 'true') != 'false'){
		$classes[] = 'argon-image-hover';
	}
	if (get_option('argon_enable_scroll_blur', 'true') != 'false'){
		$classes[] = 'argon-scroll-blur';
	}
	return $classes;
}
add_filter('body_class', 'argon_enhanced_body_class');

//Enhanced 滚动模糊：将模糊强度以 CSS 变量注入 <head>，供设置项 argon_scroll_blur_radius 调节
function argon_scroll_blur_css_var(){
	$radius = intval(get_option('argon_scroll_blur_radius', 8));
	if ($radius < 0){ $radius = 0; }
	if ($radius > 40){ $radius = 40; }
	echo '<style>:root{--argon-scroll-blur-radius:' . $radius . 'px;}</style>' . "\n";
}
add_action('wp_head', 'argon_scroll_blur_css_var');
//页面浏览量
function get_post_views($post_id){
	$count_key = 'views';
	$count = get_post_meta($post_id, $count_key, true);
	if ($count==''){
		delete_post_meta($post_id, $count_key);
		add_post_meta($post_id, $count_key, '0');
		$count = '0';
	}
	return number_format_i18n($count);
}
// 简单防爬：识别常见爬虫/机器人 UA（及空 UA），不计入浏览量，避免爬虫虚高
function argon_is_bot_request(){
	if (!isset($_SERVER['HTTP_USER_AGENT']) || trim((string) $_SERVER['HTTP_USER_AGENT']) === ''){
		return true;
	}
	$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
	$bots = array(
		'bot', 'crawler', 'spider', 'slurp', 'mediapartners-google', 'bingpreview',
		'facebookexternalhit', 'twitterbot', 'rogerbot', 'linkedinbot', 'embedly',
		'quora link preview', 'pinterest', 'whatsapp', 'telegrambot', 'discordbot',
		'slackbot', 'slack-imgproxy', 'yandex', 'baiduspider', 'sogou', 'exabot',
		'applebot', 'petalbot', 'bytespider', 'semrushbot', 'ahrefsbot', 'mj12bot',
		'dotbot', 'blexbot', 'scrapy', 'python-requests', 'go-http-client',
		'java/', 'curl', 'wget', 'okhttp', 'postmanruntime', 'phantomjs',
		'headlesschrome', 'archive.org_bot', 'ia_archiver', 'feed', 'rss'
	);
	foreach ($bots as $b){
		if (strpos($ua, $b) !== false){
			return true;
		}
	}
	return false;
}
function set_post_views(){
	// 简单防爬：已知爬虫/机器人 UA 与空 UA 不计入浏览量（降低爬虫虚高）
	if (argon_is_bot_request()){
		return;
	}
	if (!is_single() && !is_page()) {
		return;
	}
	global $post;
	if (!isset($post) || !isset($post -> ID)) {
		return;
	}
	$post_id = $post -> ID;
	if (post_password_required($post_id)){
		return;
	}
	if (isset($_GET['preview'])){
		if ($_GET['preview'] == 'true'){
			if (current_user_can('publish_posts')){
				return;
			}
		}
	}
	$noPostView = 'false';
	if (isset($_POST['no_post_view'])){
		$noPostView = $_POST['no_post_view'];
	}
	if ($noPostView == 'true'){
		return;
	}
	$post_id = $post -> ID;
	if (is_single() || is_page()) {
		// 节流：同一访客 60s 内重复刷新同一文章只计数一次，降低数据库写放大
		$viewed = isset($_COOKIE['argon_viewed_posts']) ? array_map('intval', explode(',', $_COOKIE['argon_viewed_posts'])) : array();
		if (in_array($post_id, $viewed)){
			return;
		}
		$viewed[] = $post_id;
		setcookie('argon_viewed_posts', implode(',', array_slice($viewed, -20)), time() + 60, '/');
		// 先累加进 Object Cache，再由 shutdown 钩子统一落库；
		// 配合持久化对象缓存（Redis/Memcached）可将 DB 写频率降至每请求至多一次
		$count_key = 'views';
		$cache_key = 'argon_views_' . $post_id;
		$count = wp_cache_get($cache_key, 'argon');
		if ($count === false){
			$count = (int) get_post_meta($post_id, $count_key, true);
		}
		$count++;
		wp_cache_set($cache_key, $count, 'argon', 300);
		$GLOBALS['argon_dirty_views'][$post_id] = $count;
	}
}
add_action('get_header', 'set_post_views');
//请求结束前批量落库，减少数据库写操作
function argon_flush_post_views(){
	if (empty($GLOBALS['argon_dirty_views'])){
		return;
	}
	foreach ($GLOBALS['argon_dirty_views'] as $post_id => $count){
		update_post_meta($post_id, 'views', $count);
	}
	$GLOBALS['argon_dirty_views'] = array();
}
add_action('shutdown', 'argon_flush_post_views');
//字数和预计阅读时间
function get_article_words($str){
	preg_match_all('/<pre(.*?)>[\S\s]*?<code(.*?)>([\S\s]*?)<\/code>[\S\s]*?<\/pre>/im', $str, $codeSegments, PREG_PATTERN_ORDER);
	$codeSegments = $codeSegments[3];
	$codeTotal = 0;
	foreach ($codeSegments as $codeSegment){
		$codeLines = preg_split('/\r\n|\n|\r/', $codeSegment);
		foreach ($codeLines as $line){
			if (strlen(trim($line)) > 0){
				$codeTotal++;
			}
		}
	}

	$str = preg_replace(
		'/<code(.*?)>[\S\s]*?<\/code>/im',
		'',
		$str
	);
	$str = preg_replace(
		'/<pre(.*?)>[\S\s]*?<\/pre>/im',
		'',
		$str
	);
	$str = preg_replace(
		'/<style(.*?)>[\S\s]*?<\/style>/im',
		'',
		$str
	);
	$str = preg_replace(
		'/<script(.*?)>[\S\s]*?<\/script>/im',
		'',
		$str
	);
	$str =  preg_replace('/<[^>]+?>/', ' ', $str);
	$str = html_entity_decode(strip_tags($str));
	preg_match_all('/[\x{4e00}-\x{9fa5}]/u' , $str , $cnRes);
	$cnTotal = count($cnRes[0]);
	$enRes = preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $str);
	preg_match_all('/[a-zA-Z0-9_\x{0392}-\x{03c9}\x{0400}-\x{04FF}]+|[\x{4E00}-\x{9FFF}\x{3400}-\x{4dbf}\x{f900}-\x{faff}\x{3040}-\x{309f}\x{ac00}-\x{d7af}\x{0400}-\x{04FF}]+|[\x{00E4}\x{00C4}\x{00E5}\x{00C5}\x{00F6}\x{00D6}]+|\w+/u' , $str , $enRes);
	$enTotal = count($enRes[0]);
	return array(
		'cn' => $cnTotal,
		'en' => $enTotal,
		'code' => $codeTotal,
	);
}
function get_article_words_total($str){
	$res = get_article_words($str);
	return $res['cn'] + $res['en'] + $res['code'];
}
function get_reading_time($len){
	$speedcn = get_option('argon_reading_speed', 300);
	$speeden = get_option('argon_reading_speed_en', 160);
	$speedcode = get_option('argon_reading_speed_code', 20);
	$reading_time = $len['cn'] / $speedcn + $len['en'] / $speeden + $len['code'] / $speedcode;
	if ($reading_time < 0.3){
		return __("几秒读完", 'argon');
	}
	if ($reading_time < 1){
		return __("1 分钟内", 'argon');
	}
	if ($reading_time < 60){
		return ceil($reading_time) . " " . __("分钟", 'argon');
	}
	return round($reading_time / 60 , 1) . " " . __("小时", 'argon');
}
//当前文章是否可以生成目录
function have_catalog(){
	if (!is_single() && !is_page()){
		return false;
	}
	if (post_password_required()){
		return false;
	}
	if (is_page() && is_page_template('timeline.php')){
		return true;
	}
	$content = get_post(get_the_ID()) -> post_content;
	if (preg_match('/<h[1-6](.*?)>/',$content)){
		return true;
	}else{
		return false;
	}
}
//获取文章 Meta
function get_article_meta($type){
	if ($type == 'sticky'){
		return '<div class="post-meta-detail post-meta-detail-stickey">
					<i class="fa fa-thumb-tack" aria-hidden="true"></i>
					' . _x('置顶', 'pinned', 'argon') . '
				</div>';
	}
	if ($type == 'needpassword'){
		return '<div class="post-meta-detail post-meta-detail-needpassword">
					<i class="fa fa-lock" aria-hidden="true"></i>
					' . __('需要密码', 'argon') . '
				</div>';
	}
	if ($type == 'time'){
		return '<div class="post-meta-detail post-meta-detail-time">
					<i class="fa fa-clock-o" aria-hidden="true"></i>
					<time title="' . __('发布于', 'argon') . ' ' . get_the_time('Y-n-d G:i:s') . ' | ' . __('编辑于', 'argon') . ' ' . get_the_modified_time('Y-n-d G:i:s') . '">' .
						get_the_time('Y-n-d G:i') . '
					</time>
				</div>';
	}
	if ($type == 'edittime'){
		return '<div class="post-meta-detail post-meta-detail-edittime">
					<i class="fa fa-clock-o" aria-hidden="true"></i>
					<time title="' . __('发布于', 'argon') . ' ' . get_the_time('Y-n-d G:i:s') . ' | ' . __('编辑于', 'argon') . ' ' . get_the_modified_time('Y-n-d G:i:s') . '">' .
						get_the_modified_time('Y-n-d G:i') . '
					</time>
				</div>';
	}
	if ($type == 'views'){
		if (function_exists('pvc_get_post_views')){
			$views = pvc_get_post_views(get_the_ID());
		}else{
			$views = get_post_views(get_the_ID());
		}
		return '<div class="post-meta-detail post-meta-detail-views">
					<i class="fa fa-eye" aria-hidden="true"></i> ' .
					$views .
				'</div>';
	}
	if ($type == 'comments'){
		return '<div class="post-meta-detail post-meta-detail-comments">
					<i class="fa fa-comments-o" aria-hidden="true"></i> ' .
					get_post(get_the_ID()) -> comment_count .
				'</div>';
	}
	if ($type == 'categories'){
		$res = '<div class="post-meta-detail post-meta-detail-categories">
				<i class="fa fa-bookmark-o" aria-hidden="true"></i> ';
		$categories = get_the_category();
		foreach ($categories as $index => $category){
			$res .= '<a href="' . get_category_link($category -> term_id) . '" target="_blank" class="post-meta-detail-catagory-link">' . $category -> cat_name . '</a>';
			if ($index != count($categories) - 1){
				$res .= '<span class="post-meta-detail-catagory-space">,</span>';
			}
		}
		$res .= '</div>';
		return $res;
	}
	if ($type == 'author'){
		$res = '<div class="post-meta-detail post-meta-detail-author">
					<i class="fa fa-user-circle-o" aria-hidden="true"></i> ';
					global $authordata;
		$res .= '<a href="' . get_author_posts_url($authordata -> ID, $authordata -> user_nicename) . '" target="_blank">' . get_the_author() . '</a>
				</div>';
		return $res;
	}
	if ($type == 'like'){
		if (get_option('argon_enable_post_like', 'true') != 'true' || get_post_type() != 'post'){
			return '';
		}
		$threshold = intval(get_option('argon_hot_like_threshold', 1000));
		ob_start();
		argon_render_post_like(get_the_ID(), true);
		$html = ob_get_clean();
		if (function_exists('pvc_get_post_views')){
			$count = intval(pvc_get_post_views(get_the_ID()));
		}else{
			$count = intval(get_post_meta(get_the_ID(), 'views', true));
		}
		if ($threshold > 0 && $count >= $threshold){
			$hot_color = get_option('argon_hot_like_color', '#ff5e7e');
			if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hot_color)){
				$hot_color = '#ff5e7e';
			}
			$rgb = sscanf($hot_color, '#%02x%02x%02x');
			if (is_array($rgb) && count($rgb) == 3){
				list($hr, $hg, $hb) = $rgb;
			}else{
				$hr = 255; $hg = 94; $hb = 126;
			}
			$style = '--hot:' . $hot_color
				. ';--hot-soft:rgba(' . $hr . ',' . $hg . ',' . $hb . ',0.08)'
				. ';--hot-mid:rgba(' . $hr . ',' . $hg . ',' . $hb . ',0.16)'
				. ';--hot-line:rgba(' . $hr . ',' . $hg . ',' . $hb . ',0.30)'
				. ';--hot-strong:rgba(' . $hr . ',' . $hg . ',' . $hb . ',0.45)'
				. ';--hot-glow:rgba(' . $hr . ',' . $hg . ',' . $hb . ',0.45)';
			$hot_flame = '<svg class="hot-flame" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67z"/></svg>';
			$html .= ' <span class="post-upvote-hot" style="' . esc_attr($style) . '" title="' . esc_attr(sprintf(__('热门文章（浏览量 ≥ %d）', 'argon'), $threshold)) . '">' . $hot_flame . __('热门', 'argon') . '</span>';
		}
		return $html;
	}
}
//获取文章字数统计和预计阅读时间
function get_article_reading_time_meta($post_content_full){
	$post_content_full = apply_filters("argon_html_before_wordcount", $post_content_full);
	$words = get_article_words($post_content_full);
	$total = $words['cn'] + $words['en'] + $words['code'];
	$res = '</br><div class="post-meta-detail post-meta-detail-words">
		<i class="fa fa-file-word-o" aria-hidden="true"></i>';
	if ($words['code'] > 0){
		$res .= '<span title="' . sprintf(__( '包含 %d 行代码', 'argon'), $words['code']) . '">';
	}else{
		$res .= '<span>';
	}
	$res .= ' ' . $total . " " . __("字", 'argon');
	$res .= '</span>
		</div>
		<div class="post-meta-devide">|</div>
		<div class="post-meta-detail post-meta-detail-words">
			<i class="fa fa-hourglass-end" aria-hidden="true"></i>
			' . get_reading_time($words) . '
		</div>
	';
	return $res;
}
//当前文章是否隐藏 阅读时间 Meta
function is_readingtime_meta_hidden(){
	if (strpos(get_the_content() , "[hide_reading_time][/hide_reading_time]") !== False){
		return true;
	}
	global $post;
	if (get_post_meta($post -> ID, 'argon_hide_readingtime', true) == 'true'){
		return true;
	}
	return false;
}
//当前文章是否隐藏 发布时间和分类 (简洁 Meta)
function is_meta_simple(){
	global $post;
	if (get_post_meta($post -> ID, 'argon_meta_simple', true) == 'true'){
		return true;
	}
	return false;
}
//根据文章 id 获取标题
function get_post_title_by_id($id){
	return get_post($id) -> post_title;
}
//解析 UA 和相应图标
require_once(get_template_directory() . '/useragent-parser.php');
$argon_comment_ua = get_option("argon_comment_ua");
$argon_comment_show_ua = Array();
if (strpos($argon_comment_ua, 'platform') !== false){
	$argon_comment_show_ua['platform'] = true;
}
if (strpos($argon_comment_ua, 'browser') !== false){
	$argon_comment_show_ua['browser'] = true;
}
if (strpos($argon_comment_ua, 'version') !== false){
	$argon_comment_show_ua['version'] = true;
}
function parse_ua_and_icon($userAgent){
	global $argon_comment_ua;
	global $argon_comment_show_ua;
	if ($argon_comment_ua == "" || $argon_comment_ua == "hidden"){
		return "";
	}
	$parsed = argon_parse_user_agent($userAgent);
	$out = "<div class='comment-useragent'>";
	if (isset($argon_comment_show_ua['platform']) && $argon_comment_show_ua['platform'] == true){
		if (isset($GLOBALS['UA_ICON'][$parsed['platform']])){
			$out .= $GLOBALS['UA_ICON'][$parsed['platform']] . " ";
		}else{
			$out .= $GLOBALS['UA_ICON']['Unknown'] . " ";
		}
		$out .= $parsed['platform'];
	}
	if (isset($argon_comment_show_ua['browser']) && $argon_comment_show_ua['browser'] == true){
		if (isset($GLOBALS['UA_ICON'][$parsed['browser']])){
			$out .= " " . $GLOBALS['UA_ICON'][$parsed['browser']];
		}else{
			$out .= " " . $GLOBALS['UA_ICON']['Unknown'];
		}
		$out .= " " . $parsed['browser'];
		if (isset($argon_comment_show_ua['version']) && $argon_comment_show_ua['version'] == true){
			$out .= " " . $parsed['version'];
		}
	}
	$out .= "</div>";
	return apply_filters("argon_comment_ua_icon", $out);
}

/* 获取真实客户端 IP（适配 CDN / 反向代理，可在后台选择来源） */
function argon_get_real_client_ip(){
	$source = get_option('argon_comment_ip_source', 'default');
	$custom = trim(get_option('argon_comment_ip_custom_header', ''));
	$ip = '';
	switch ($source){
		case 'cloudflare':
			$ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? trim($_SERVER['HTTP_CF_CONNECTING_IP']) : '';
			break;
		case 'eo':
			$ip = isset($_SERVER['HTTP_EO_CONNECTING_IP']) ? trim($_SERVER['HTTP_EO_CONNECTING_IP']) : '';
			break;
		case 'custom':
			if ($custom != ''){
				$key = 'HTTP_' . strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $custom));
				$ip = isset($_SERVER[$key]) ? trim($_SERVER[$key]) : '';
			}
			break;
		case 'default':
		default:
			$forward_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP');
			foreach ($forward_keys as $key){
				if (empty($_SERVER[$key])){
					continue;
				}
				$candidates = explode(',', $_SERVER[$key]);
				foreach ($candidates as $candidate){
					$candidate = trim($candidate);
					if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)){
						return $candidate;
					}
				}
			}
			break;
	}
	$ip = trim($ip);
	if ($ip != '' && strpos($ip, ',') !== false){
		$ip = trim(explode(',', $ip)[0]);
	}
	if ($ip != '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)){
		return $ip;
	}
	if (!empty($_SERVER['REMOTE_ADDR'])){
		return $_SERVER['REMOTE_ADDR'];
	}
	return '';
}
/* 评论保存时写入真实客户端 IP（覆盖 WordPress 默认写入的代理 IP） */
function argon_preprocess_comment_ip($commentdata){
	$ip = argon_get_real_client_ip();
	if (!empty($ip)){
		$commentdata['comment_author_IP'] = $ip;
	}
	return $commentdata;
}
add_filter('preprocess_comment', 'argon_preprocess_comment_ip');

/* 渲染评论者 IP 与归属地。地区在前、原始 IP 在后；归属地需开启 argon_comment_show_geolocation。
   服务端仅在缓存命中时直接渲染；缓存未命中时输出占位 span（带 data-ip），
   由前端脚本 argonInitCommentGeo() 触发服务端 AJAX 兜底查询（按 IP 缓存，规避限流）。 */
function argon_render_comment_ip($comment){
	$show_ip = get_option('argon_comment_show_ip', 'false') == 'true';
	$show_geo = get_option('argon_comment_show_geolocation', 'false') == 'true';
	if (!$show_ip && !$show_geo){
		return;
	}
	$ip = isset($comment -> comment_author_IP) ? $comment -> comment_author_IP : '';
	if (empty($ip)){
		return;
	}
	$geo_icon = '<svg viewBox="0 0 1024 1024" style="transform: scale(1.05) translateY(-1px);" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="argonGeoGlobe" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#3aa0ff"/><stop offset="100%" stop-color="#36c98e"/></linearGradient></defs><g transform="scale(42.6667)"><circle cx="12" cy="12" r="10" fill="url(#argonGeoGlobe)"/><g fill="none" stroke="#ffffff" stroke-width="1" stroke-opacity="0.85"><ellipse cx="12" cy="12" rx="4.5" ry="10"/><ellipse cx="12" cy="12" rx="9" ry="10"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="3.2" y1="7.5" x2="20.8" y2="7.5"/><line x1="3.2" y1="16.5" x2="20.8" y2="16.5"/></g></g></svg>';
	$ip_icon  = '<svg viewBox="0 0 1024 1024" style="transform: scale(1.05) translateY(-1px);" xmlns="http://www.w3.org/2000/svg"><g transform="scale(42.6667)"><path fill="#f5574e" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.8" fill="#ffffff"/></g></svg>';
	if ($show_geo){
		$cached = argon_get_ip_geolocation_cache($ip);
		if ($cached !== false && (!empty($cached['country']) || !empty($cached['country_code']))){
			echo '<span class="comment-geo" title="' . esc_attr__('IP 归属地', 'argon') . '">' . $geo_icon . ' ' . esc_html(argon_format_geo($cached)) . '</span>';
		}else{
			echo '<span class="comment-geo" data-ip="' . esc_attr($ip) . '" title="' . esc_attr__('IP 归属地', 'argon') . '">' . $geo_icon . '</span>';
		}
	}
	if ($show_ip){
		echo '<span class="comment-ip" title="' . esc_attr__('IP 地址', 'argon') . '">' . $ip_icon . ' ' . esc_html($ip) . '</span>';
	}
}

/* 格式化归属地文本：中国显示「中国 · 中文省名」，其他国家保留英文（country · region） */
function argon_format_geo($geo){
	if (empty($geo) || (empty($geo['country']) && empty($geo['country_code']))){
		return '';
	}
	if (!empty($geo['country_code']) && $geo['country_code'] == 'CN'){
		$country = '中国';
		if (!empty($geo['region_code']) && function_exists('argon_cn_province_code_zh')){
			$region = argon_cn_province_code_zh($geo['region_code']);
		}else{
			$region = !empty($geo['region']) ? argon_cn_province_zh($geo['region']) : '';
		}
	}else{
		$country = !empty($geo['country']) ? $geo['country'] : $geo['country_code'];
		$region = !empty($geo['region']) ? $geo['region'] : '';
	}
	$text = $country;
	if ($region != ''){
		$text .= ' · ' . $region;
	}
	return $text;
}

/* 仅读取归属地缓存（transient），不触发 API 请求；未命中返回 false */
function argon_get_ip_geolocation_cache($ip){
	$cached = get_transient('argon_geo_' . md5(trim($ip)));
	return $cached === false ? false : $cached;
}

/* 服务端归属地查询（兜底用）：先读缓存，未命中则依次尝试 ip.sb、ipwho.is、ip-api.com，
   各源返回字段结构不同，按 host 归一化为统一结构（country/country_code/region/region_code）。
   成功缓存 30 天；失败/私有 IP 短负缓存（10 分钟），便于限流恢复后重试。 */
function argon_get_ip_geolocation($ip){
	$ip = trim($ip);
	$cache_key = 'argon_geo_' . md5($ip);
	$cached = get_transient($cache_key);
	if ($cached !== false){
		return $cached;
	}
	$result = array('failed' => true);
	if (!filter_var($ip, FILTER_VALIDATE_IP)){
		set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
		return $result;
	}
	$sources = array(
		'https://api.ip.sb/geoip/' . urlencode($ip),
		'https://ipwho.is/' . urlencode($ip),
		'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,message,country,countryCode,region,regionName',
	);
	foreach ($sources as $url){
		$response = wp_remote_get($url, array(
			'timeout' => 5,
			'headers' => array('User-Agent' => 'Argon-Theme'),
		));
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200){
			continue;
		}
		$body = json_decode(wp_remote_retrieve_body($response), true);
		if (!is_array($body)){
			continue;
		}
		$host = parse_url($url, PHP_URL_HOST);
		$parsed = false;
		if ($host == 'ip-api.com'){
			// ip-api.com：region=代码(CA)，regionName=全称；status 必须为 success
			if (empty($body['status']) || $body['status'] !== 'success'){
				continue;
			}
			$parsed = array(
				'country'      => isset($body['country']) ? $body['country'] : '',
				'country_code' => isset($body['countryCode']) ? $body['countryCode'] : '',
				'region'       => isset($body['regionName']) ? $body['regionName'] : '',
				'region_code'  => isset($body['region']) ? $body['region'] : '',
			);
		}else{
			$parsed = array(
				'country'      => isset($body['country']) ? $body['country'] : '',
				'country_code' => isset($body['country_code']) ? $body['country_code'] : '',
				'region'       => isset($body['region']) ? $body['region'] : '',
				'region_code'  => isset($body['region_code']) ? $body['region_code'] : '',
			);
		}
		if (empty($parsed['country']) && empty($parsed['country_code'])){
			continue;
		}
		$result = $parsed;
		break;
	}
	if (!empty($result['country']) || !empty($result['country_code'])){
		set_transient($cache_key, $result, 30 * DAY_IN_SECONDS);
	}else{
		set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
	}
	return $result;
}

/* 前端兜底 AJAX：客户端脚本 argonInitCommentGeo() 在直连失败/缓存未命中时调用，
   由服务端执行 geoip 查询（已按 IP 缓存）并返回格式化中文文本。 */
function argon_ajax_comment_geo(){
	argon_verify_ajax_nonce();
	if (empty($_POST['ip']) || !filter_var($_POST['ip'], FILTER_VALIDATE_IP)){
		wp_send_json(array('failed' => true));
	}
	$geo = argon_get_ip_geolocation($_POST['ip']);
	if (empty($geo['country']) && empty($geo['country_code'])){
		wp_send_json(array('failed' => true));
	}
	wp_send_json(array('display' => argon_format_geo($geo)));
}
add_action('wp_ajax_nopriv_argon_comment_geo', 'argon_ajax_comment_geo');
add_action('wp_ajax_argon_comment_geo', 'argon_ajax_comment_geo');
//发送邮件
function send_mail($to, $subject, $content){
	wp_mail($to, $subject, $content, array('Content-Type: text/html; charset=UTF-8'));
}
function check_email_address($email){
	return (bool) preg_match( "/^\w+((-\w+)|(\.\w+))*@[A-Za-z0-9]+(([.\-])[A-Za-z0-9]+)*\.[A-Za-z0-9]+$/", $email );
}
//检验评论 Token 和用户 Token 是否一致
function check_comment_token($id){
	if (strlen($_COOKIE['argon_user_token']) != 32){
		return false;
	}
	if ($_COOKIE['argon_user_token'] != get_comment_meta($id, "user_token", true)){
		return false;
	}
	return true;
}
//检验评论发送者 ID 和当前登录用户 ID 是否一致
function check_login_user_same($userid){
	if ($userid == 0){
		return false;
	}
	if ($userid != (wp_get_current_user() -> ID)){
		return false;
	}
	return true;
}
function get_comment_user_id_by_id($comment_ID){
	$comment = get_comment($comment_ID);
	return $comment -> user_id;
}
function check_comment_userid($id){
	if (!check_login_user_same(get_comment_user_id_by_id($id))){
		return false;
	}
	return true;
}
//悄悄话
function is_comment_private_mode($id){
	if (strlen(get_comment_meta($id, "private_mode", true)) != 32){
		return false;
	}
	return true;
}
function user_can_view_comment($id){
	if (!is_comment_private_mode($id)){
		return true;
	}
	if (current_user_can("manage_options")){
		return true;
	}
	if ($_COOKIE['argon_user_token'] == get_comment_meta($id, "private_mode", true)){
		return true;
	}
	return false;
}
//过滤 RSS 中悄悄话
function remove_rss_private_comment_title_and_author($str){
	global $comment;
	if (isset($comment -> comment_ID) && is_comment_private_mode($comment -> comment_ID)){
		return "***";
	}
	return $str;
}
add_filter('the_title_rss' , 'remove_rss_private_comment_title_and_author');
add_filter('comment_author_rss' , 'remove_rss_private_comment_title_and_author');
function remove_rss_private_comment_content($str){
	global $comment;
	if (is_comment_private_mode($comment -> comment_ID)){
		$comment -> comment_content = __('该评论为悄悄话', 'argon');
		return $comment -> comment_content;
	}
	return $str;
}
add_filter('comment_text_rss' , 'remove_rss_private_comment_content');
//评论回复信息
function get_comment_parent_info($comment){
	if (!$GLOBALS['argon_comment_options']['show_comment_parent_info']){
		return "";
	}
	if ($comment -> comment_parent == 0){
		return "";
	}
	$parent_comment = get_comment($comment -> comment_parent);
	return '<div class="comment-parent-info" data-parent-id=' . $parent_comment -> comment_ID . '><i class="fa fa-reply" aria-hidden="true"></i> ' . get_comment_author($parent_comment -> comment_ID) . '</div>';
}
//是否可以查看评论编辑记录
function can_visit_comment_edit_history($id){
	$who_can_visit_comment_edit_history = get_option("argon_who_can_visit_comment_edit_history");
	if ($who_can_visit_comment_edit_history == ""){
		$who_can_visit_comment_edit_history = "admin";
	}
	switch ($who_can_visit_comment_edit_history) {
		case 'everyone':
			return true;

		case 'commentsender':
			if (check_comment_token($id) || check_comment_userid($id)){
				return true;
			}
			return false;

		default:
			if (current_user_can("moderate_comments")){
				return true;
			}
			return false;
	}
}
//获取评论编辑记录
// AJAX CSRF (Nonce) 校验：评论/点赞等写操作必须通过此验证，防止跨站请求伪造
function argon_verify_ajax_nonce(){
	if (!isset($_POST['argon_ajax_nonce']) || !wp_verify_nonce($_POST['argon_ajax_nonce'], 'argon_ajax_action')){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('安全验证失败，请刷新页面后重试', 'argon')
		)));
	}
}

function get_comment_edit_history(){
	argon_verify_ajax_nonce();
	$id = $_POST['id'];
	if (!can_visit_comment_edit_history($id)){
		exit(json_encode(array(
			'id' => $_POST['id'],
			'history' => ""
		)));
	}
	$editHistory = json_decode(get_comment_meta($id, "comment_edit_history", true));
	$editHistory = array_reverse($editHistory);
	$res = "";
	$position = count($editHistory) + 1;
	date_default_timezone_set(get_option('timezone_string'));
	foreach ($editHistory as $edition){
		$position -= 1;
		$res .= "<div class='comment-edit-history-item'>
					<div class='comment-edit-history-title'>
						<div class='comment-edit-history-id'>
							#" . $position . "
						</div>
						" . ($edition -> isfirst ? "<span class='badge badge-primary badge-admin'>" . __("最初版本", 'argon') . "</span>" : "") . "
					</div>
					<div class='comment-edit-history-time'>" . date('Y-m-d H:i:s', $edition -> time) . "</div>
					<div class='comment-edit-history-content'>" . wp_kses_post(str_replace("\n", "</br>", $edition -> content)) . "</div>
				</div>";
	}
	exit(json_encode(array(
		'id' => $_POST['id'],
		'history' => $res
	)));
}
add_action('wp_ajax_get_comment_edit_history', 'get_comment_edit_history');
add_action('wp_ajax_nopriv_get_comment_edit_history', 'get_comment_edit_history');
//实时搜索建议 AJAX
function argon_ajax_live_search(){
	$q = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash($_REQUEST['q'])) : '';
	if ($q === ''){
		wp_send_json(array());
	}
	$post_types = array('post', 'page');
	$filter = get_option('argon_search_post_filter', 'post,page');
	if ($filter && $filter != 'off'){
		$post_types = explode(',', $filter);
	}
	$allowed = get_post_types(array('public' => true));
	$post_types = array_values(array_intersect($post_types, $allowed));
	if (empty($post_types)){
		$post_types = array('post');
	}
	$query = new WP_Query(array(
		's' => $q,
		'posts_per_page' => 8,
		'post_type' => $post_types,
		'post_status' => 'publish',
	));
	$results = array();
	if ($query -> have_posts()){
		while ($query -> have_posts()){
			$query -> the_post();
			$thumbnail = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') : '';
			$excerpt = wp_strip_all_tags(get_the_excerpt());
			if ($excerpt === get_the_title()){
				$excerpt = '';
			}
			$results[] = array(
				'title' => get_the_title(),
				'url' => get_permalink(),
				'thumbnail' => $thumbnail,
				'type' => get_post_type(),
				'excerpt' => $excerpt ? wp_trim_words($excerpt, 14, '…') : '',
			);
		}
	}
	wp_reset_postdata();
	wp_send_json($results);
}
add_action('wp_ajax_argon_live_search', 'argon_ajax_live_search');
add_action('wp_ajax_nopriv_argon_live_search', 'argon_ajax_live_search');
//是否可以置顶/取消置顶
function is_comment_pinable($id){
	if (get_comment($id) -> comment_approved != "1"){
		return false;
	}
	if (get_comment($id) -> comment_parent != 0){
		return false;
	}
	if (is_comment_private_mode($id)){
		return false;
	}
	return true;
}
//评论内容格式化
function argon_get_comment_text($comment_ID = 0, $args = array()) {
	$comment = get_comment($comment_ID);
	$comment_text = get_comment_text($comment, $args);
	$enableMarkdown = get_comment_meta(get_comment_ID(), "use_markdown", true);
	/*if ($enableMarkdown == false){
		return $comment_text;
	}*/
	//图片
	$comment_text = preg_replace(
		'/<a data-src="(.*?)" title="(.*?)" class="comment-image"(.*?)>([\w\W]*)<\/a>/',
		'<img src="$1" alt="$2" />',
		$comment_text
	);
	$comment_text = preg_replace(
		'/<img src="(.*?)" alt="(.*?)" \/>/',
		'<a href="$1" title="$2" class="comment-image" data-fancybox="comment-images" rel="nofollow">
			<i class="fa fa-image" aria-hidden="true"></i>
			' . __('查看图片', 'argon') . '
			<img src="" alt="$2" class="comment-image-preview">
			<i class="comment-image-preview-mask"></i>
		</a>',
		$comment_text
	);
	//表情
	if (get_option("argon_comment_emotion_keyboard", "true") != "false"){
		global $emotionListDefault;
		$emotionList = apply_filters("argon_emotion_list", $emotionListDefault);
		$search = array();
		$replace = array();
		foreach ($emotionList as $groupIndex => $group){
			foreach ($group['list'] as $index => $emotion){
				if ($emotion['type'] != 'sticker'){
					continue;
				}
				if (!isset($emotion['code']) || mb_strlen($emotion['code']) == 0){
					continue;
				}
				if (!isset($emotion['src']) || mb_strlen($emotion['src']) == 0){
					continue;
				}
				$search[] = ':' . $emotion['code'] . ':';
				$replace[] = "<img class='comment-sticker lazyload' src='data:image/svg+xml;base64,PHN2ZyBjbGFzcz0iZW1vdGlvbi1sb2FkaW5nIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGZpbGw9Im5vbmUiIHZpZXdCb3g9Ii04IC04IDQwIDQwIiBzdHJva2U9IiM4ODgiIG9wYWNpdHk9Ii41IiB3aWR0aD0iNjAiIGhlaWdodD0iNjAiPgogIDxwYXRoIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgc3Ryb2tlLXdpZHRoPSIxLjUiIGQ9Ik0xNC44MjggMTQuODI4YTQgNCAwIDAxLTUuNjU2IDBNOSAxMGguMDFNMTUgMTBoLjAxTTIxIDEyYTkgOSAwIDExLTE4IDAgOSA5IDAgMDExOCAweiIvPgo8L3N2Zz4=' data-original='" . $emotion['src'] . "'/><noscript><img class='comment-sticker' src='" . $emotion['src'] . "'/></noscript>";
			}
		}
		if (!empty($search)){
			$comment_text = str_replace($search, $replace, $comment_text); // 一次性替换，避免对每个表情遍历整段评论文本
		}
	}
	return apply_filters( 'comment_text', $comment_text, $comment, $args );
}
//评论点赞
function get_comment_upvotes($id) {
	$comment = get_comment($id);
	if ($comment == null){
		return 0;
	}
	$upvotes = get_comment_meta($comment -> comment_ID, "upvotes", true);
	if ($upvotes == null) {
		$upvotes = 0;
	}
	return $upvotes;
}
function set_comment_upvotes($id){
	$comment = get_comment($id);
	if ($comment == null){
		return 0;
	}
	$upvotes = get_comment_meta($comment -> comment_ID, "upvotes", true);
	if ($upvotes == null) {
		$upvotes = 0;
	}
	$upvotes++;
	update_comment_meta($comment -> comment_ID, "upvotes", $upvotes);
	return $upvotes;
}
function argon_get_upvoted_comment_ids(){
	// 已赞评论 ID 列表：登录用户走 user meta（服务端去重），匿名用户走 Cookie（尽力而为）
	if (is_user_logged_in()){
		$list = get_user_meta(get_current_user_id(), 'argon_upvoted_comments', true);
		return is_array($list) ? $list : array();
	}
	$upvotedList = isset( $_COOKIE['argon_comment_upvoted'] ) ? $_COOKIE['argon_comment_upvoted'] : '';
	return array_filter(array_map('intval', explode(',', $upvotedList)), function($v){ return $v > 0; });
}
function argon_mark_comment_upvoted($id){
	if (is_user_logged_in()){
		$list = argon_get_upvoted_comment_ids();
		if (!in_array($id, $list, true)){
			$list[] = (int) $id;
			update_user_meta(get_current_user_id(), 'argon_upvoted_comments', $list);
		}
	}else{
		$upvotedList = isset( $_COOKIE['argon_comment_upvoted'] ) ? $_COOKIE['argon_comment_upvoted'] : '';
		$ids = array_filter(array_map('intval', explode(',', $upvotedList)));
		if (!in_array((int) $id, $ids, true)) {
			$ids[] = (int) $id;
		}
		// 去重 + 保留最近 200 个，防止 Cookie 累积超限导致请求头过大 (HTTP 400)
		$ids = array_unique($ids);
		$ids = array_slice($ids, -200);
		setcookie('argon_comment_upvoted', implode(',', $ids) . ',', time() + 3153600000, '/');
	}
}
function is_comment_upvoted($id){
	return in_array((int) $id, argon_get_upvoted_comment_ids(), true);
}
function upvote_comment(){
	argon_verify_ajax_nonce();
	if (get_option("argon_enable_comment_upvote", "false") != "true"){
		return;
	}
	header('Content-Type:application/json; charset=utf-8');
	$ID = isset($_POST["comment_id"]) ? intval($_POST["comment_id"]) : 0;
	$comment = get_comment($ID);
	if ($comment == null){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('评论不存在', 'argon'),
			'total_upvote' => 0
		)));
	}
	if (is_comment_upvoted($ID)){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('该评论已被赞过', 'argon'),
			'total_upvote' => get_comment_upvotes($ID)
		)));
	}
	set_comment_upvotes($ID);
	argon_mark_comment_upvoted($ID);
	exit(json_encode(array(
		'ID' => $ID,
		'status' => 'success',
		'msg' => __('点赞成功', 'argon'),
		'total_upvote' => format_number_in_kilos(get_comment_upvotes($ID))
	)));
}
add_action('wp_ajax_upvote_comment' , 'upvote_comment');
add_action('wp_ajax_nopriv_upvote_comment' , 'upvote_comment');
//评论样式格式化
$GLOBALS['argon_comment_options']['enable_upvote'] = (get_option("argon_enable_comment_upvote", "false") == "true");
$GLOBALS['argon_comment_options']['enable_pinning'] = (get_option("argon_enable_comment_pinning", "false") == "true");
$GLOBALS['argon_comment_options']['current_user_can_moderate_comments'] = current_user_can('moderate_comments');
$GLOBALS['argon_comment_options']['show_comment_parent_info'] = (get_option("argon_show_comment_parent_info", "true") == "true");
function argon_comment_format($comment, $args, $depth){
	global $comment_enable_upvote, $comment_enable_pinning;
	$GLOBALS['comment'] = $comment;
	if (!($comment -> placeholder) && user_can_view_comment(get_comment_ID())){
	?>
	<li class="comment-item" id="comment-<?php comment_ID(); ?>" data-id="<?php comment_ID(); ?>" data-use-markdown="<?php echo get_comment_meta(get_comment_ID(), "use_markdown", true);?>">
		<div class="comment-item-left-wrapper">
			<div class="comment-item-avatar">
				<?php if(function_exists('get_avatar') && get_option('show_avatars')){
					echo get_avatar($comment, 40);
				}?>
			</div>
			<?php if ($GLOBALS['argon_comment_options']['enable_upvote']){ ?>
				<button class="comment-upvote btn btn-icon btn-outline-primary btn-sm <?php echo (is_comment_upvoted(get_comment_ID()) ? 'upvoted' : ''); ?>" type="button" data-id="<?php comment_ID(); ?>">
					<span class="btn-inner--icon"><i class="fa fa-caret-up"></i></span>
					<span class="btn-inner--text">
						<span class="comment-upvote-num"><?php echo format_number_in_kilos(get_comment_upvotes(get_comment_ID())); ?></span>
					</span>
				</button>
			<?php } ?>
		</div>
		<div class="comment-item-inner" id="comment-inner-<?php comment_ID();?>">
			<div class="comment-item-title">
				<div class="comment-name">
					<div class="comment-author"><?php echo get_comment_author_link();?></div>
					<?php if (user_can($comment -> user_id , "update_core")){
						echo '<span class="badge badge-primary badge-admin">' . __('博主', 'argon') . '</span>';}
					?>
					<?php echo get_comment_parent_info($comment); ?>
					<?php if ($GLOBALS['argon_comment_options']['enable_pinning'] && get_comment_meta(get_comment_ID(), "pinned", true) == "true"){
						echo '<span class="badge badge-danger badge-pinned"><i class="fa fa-thumb-tack" aria-hidden="true"></i> ' . _x('置顶', 'pinned', 'argon') . '</span>';
					}?>
					<?php if (is_comment_private_mode(get_comment_ID()) && user_can_view_comment(get_comment_ID())){
						echo '<span class="badge badge-success badge-private-comment">' . __('悄悄话', 'argon') . '</span>';}
					?>
					<?php if ($comment -> comment_approved == 0){
						echo '<span class="badge badge-warning badge-unapproved">' . __('待审核', 'argon') . '</span>';}
					?>
					<?php
						echo parse_ua_and_icon($comment -> comment_agent);
						argon_render_comment_ip($comment);
					?>
				</div>
				<div class="comment-info">
					<?php if (get_comment_meta(get_comment_ID(), "edited", true) == "true") { ?>
						<div class="comment-edited<?php if (can_visit_comment_edit_history(get_comment_ID())){echo ' comment-edithistory-accessible';}?>">
							<i class="fa fa-pencil" aria-hidden="true"></i><?php _e('已编辑', 'argon')?>
						</div>
					<?php } ?>
					<div class="comment-time">
						<span class="human-time" data-time="<?php echo get_comment_time('U', true);?>"><?php echo human_time_diff(get_comment_time('U') , current_time('timestamp')) . __("前", "argon");?></span>
						<div class="comment-time-details"><?php echo get_comment_time('Y-n-d G:i:s');?></div>
					</div>
				</div>
			</div>
			<div class="comment-item-text">
				<?php echo argon_get_comment_text();?>
			</div>
			<div class="comment-item-source" style="display: none;" aria-hidden="true"><?php echo htmlspecialchars(get_comment_meta(get_comment_ID(), "comment_content_source", true));?></div>

			<div class="comment-operations">
				<?php if ($GLOBALS['argon_comment_options']['enable_pinning'] && $GLOBALS['argon_comment_options']['current_user_can_moderate_comments'] && is_comment_pinable(get_comment_ID())) {
					if (get_comment_meta(get_comment_ID(), "pinned", true) == "true") { ?>
						<button class="comment-unpin btn btn-sm btn-outline-primary" data-id="<?php comment_ID(); ?>" type="button" style="margin-right: 2px;"><?php _e('取消置顶', 'argon')?></button>
					<?php } else { ?>
						<button class="comment-pin btn btn-sm btn-outline-primary" data-id="<?php comment_ID(); ?>" type="button" style="margin-right: 2px;"><?php _ex('置顶', 'to pin', 'argon')?></button>
				<?php }
					} ?>
				<?php if ((check_comment_token(get_comment_ID()) || check_login_user_same($comment -> user_id)) && (get_option("argon_comment_allow_editing") != "false")) { ?>
					<button class="comment-edit btn btn-sm btn-outline-primary" data-id="<?php comment_ID(); ?>" type="button" style="margin-right: 2px;"><?php _e('编辑', 'argon')?></button>
				<?php } ?>
				<button class="comment-reply btn btn-sm btn-outline-primary" data-id="<?php comment_ID(); ?>" type="button"><?php _e('回复', 'argon')?></button>
			</div>
		</div>
	</li>
	<li class="comment-divider"></li>
	<li>
<?php }}
//评论样式格式化 (说说预览界面)
function argon_comment_shuoshuo_preview_format($comment, $args, $depth){
	$GLOBALS['comment'] = $comment;?>
	<li class="comment-item" id="comment-<?php comment_ID(); ?>">
		<div class="comment-item-inner " id="comment-inner-<?php comment_ID();?>">
			<span class="shuoshuo-comment-item-title">
				<?php echo get_comment_author_link();?>
				<?php if( user_can($comment -> user_id , "update_core") ){
					echo '<span class="badge badge-primary badge-admin">' . __('博主', 'argon') . '</span>';}
				?>
				<?php if( $comment -> comment_approved == 0 ){
					echo '<span class="badge badge-warning badge-unapproved">' . __('待审核', 'argon') . '</span>';}
				?>
				:
			</span>
			<span class="shuoshuo-comment-item-text">
				<?php echo strip_tags(get_comment_text());?>
			</span>
		</div>
	</li>
	<li>
<?php }
function comment_author_link_filter($html){
	return str_replace('href=', 'target="_blank" href=', $html);
}
add_filter('get_comment_author_link', 'comment_author_link_filter');
//评论验证码生成 & 验证
function get_comment_captcha_seed($refresh = false){
	// 无状态实现：验证码 seed 已随表单提交（comment_captcha_seed），无需 PHP Session。
	// 仅用请求内 static 缓存，保证同一次渲染中多次调用得到同一 seed（题目与隐藏字段一致），
	// 同时彻底避免 PHP 文件型 Session 的会话文件锁 / I/O 开销。
	static $cachedSeed = null;
	if ($cachedSeed !== null && !$refresh){
		return $cachedSeed;
	}
	if (function_exists('random_int')){
		$captchaSeed = random_int(0, 500000000);
	}elseif (function_exists('openssl_random_pseudo_bytes')){
		$captchaSeed = abs(hexdec(bin2hex(openssl_random_pseudo_bytes(4)))) % 500000001; // 加密级后备，避免可预测的 mt_rand
	}else{
		$captchaSeed = mt_rand(0, 500000000); // 极旧环境最后兜底
	}
	$cachedSeed = $captchaSeed;
	return $captchaSeed;
}
class captcha_calculation{ //数字验证码
	var $captchaSeed;
	function __construct($seed) {
		$this -> captchaSeed = $seed;
	}
	function getChallenge(){
		mt_srand($this -> captchaSeed + 10007);
		$oper = mt_rand(1 , 4);
		$num1 = 0;
		$num2 = 0;
		switch ($oper){
			case 1:
				$num1 = mt_rand(1 , 20);
				$num2 = mt_rand(0 , 20 - $num1);
				return $num1 . " + " . $num2 . " = ";
				break;
			case 2:
				$num1 = mt_rand(10 , 20);
				$num2 = mt_rand(1 , $num1);
				return $num1 . " - " . $num2 . " = ";
				break;
			case 3:
				$num1 = mt_rand(3 , 9);
				$num2 = mt_rand(3 , 9);
				return $num1 . " * " . $num2 . " = ";
				break;
			case 4:
				$num2 = mt_rand(2 , 9);
				$num1 = $num2 * mt_rand(2 , 9);
				return $num1 . " / " . $num2 . " = ";
				break;
			default:
				break;
		}
	}
	function getAnswer(){
		mt_srand($this -> captchaSeed + 10007);
		$oper = mt_rand(1 , 4);
		$num1 = 0;
		$num2 = 0;
		switch ($oper){
			case 1:
				$num1 = mt_rand(1 , 20);
				$num2 = mt_rand(0 , 20 - $num1);
				return $num1 + $num2;
				break;
			case 2:
				$num1 = mt_rand(10 , 20);
				$num2 = mt_rand(1 , $num1);
				return $num1 - $num2;
				break;
			case 3:
				$num1 = mt_rand(3 , 9);
				$num2 = mt_rand(3 , 9);
				return $num1 * $num2;
				break;
			case 4:
				$num2 = mt_rand(2 , 9);
				$num1 = $num2 * mt_rand(2 , 9);
				return $num1 / $num2;
				break;
			default:
				break;
		}
		return "";
	}
	function check($answer){
		if ($answer == self::getAnswer()){
			return true;
		}
		return false;
	}
}
function wrong_captcha(){
	exit(json_encode(array(
		'status' => 'failed',
		'msg' => __('验证码错误', 'argon'),
		'isAdmin' => current_user_can('manage_options')
	)));
	//wp_die('验证码错误，评论失败');
}
function get_comment_captcha(){
	$captcha = new captcha_calculation(get_comment_captcha_seed());
	return $captcha -> getChallenge();
}
function get_comment_captcha_answer(){
	$captcha = new captcha_calculation(get_comment_captcha_seed());
	return $captcha -> getAnswer();
}
function check_comment_captcha($comment){
	if (get_option('argon_comment_need_captcha') == 'false'){
		return $comment;
	}
	$answer = $_POST['comment_captcha'];
	if(current_user_can('manage_options')){
		return $comment;
	}
	// 优先使用前端随评论提交的验证码 seed：它与表单渲染题目时使用的 seed 一致，
	// 可避免会话(seed)在“渲染表单”和“提交评论”两次请求间未保持时，正确答案被误判为错误。
	// 该验证码本身为客户端可计算的数学题（seed 已随页面下发），使用提交 seed 不会削弱其安全性。
	if (isset($_POST['comment_captcha_seed']) && $_POST['comment_captcha_seed'] !== ''){
		$seed = $_POST['comment_captcha_seed'];
	}else{
		$seed = get_comment_captcha_seed();
	}
	$captcha = new captcha_calculation($seed);
	if (!($captcha -> check($answer))){
		wrong_captcha();
	}
	return $comment;
}
add_filter('preprocess_comment' , 'check_comment_captcha');

function ajax_get_captcha(){
	if (get_option('argon_get_captcha_by_ajax', 'false') != 'true') {
		return;
	}
	exit(json_encode(array(
		'captcha' => get_comment_captcha()
	)));
}
add_action('wp_ajax_get_captcha', 'ajax_get_captcha');
add_action('wp_ajax_nopriv_get_captcha', 'ajax_get_captcha');
//Ajax 发送评论
function ajax_post_comment(){
	argon_verify_ajax_nonce();
	$max_length = 65535;
	if (isset($_POST['comment']) && mb_strlen($_POST['comment']) > $max_length) {
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('评论内容过长', 'argon'),
			'isAdmin' => current_user_can('manage_options')
		)));
	}
	$parentID = isset($_POST['comment_parent']) ? intval($_POST['comment_parent']) : 0;
	if (is_comment_private_mode($parentID)){
		if (!user_can_view_comment($parentID)){
			//如果父级评论是悄悄话模式且当前 Token 与父级不相同则返回
			exit(json_encode(array(
				'status' => 'failed',
				'msg' =>  __('不能回复其他人的悄悄话评论', 'argon'),
				'isAdmin' => current_user_can('manage_options')
			)));
		}
	}
	if (get_option('argon_comment_enable_qq_avatar') == 'true'){
		if (check_qqnumber($_POST['email'])){
			$_POST['qq'] = $_POST['email'];
			$_POST['email'] .= "@qq.com";
		}else{
			$_POST['qq'] = "";
		}
	}
	$comment = wp_handle_comment_submission(wp_unslash($_POST));
	if (is_wp_error($comment)){
		$msg = $comment -> get_error_data();
		if (!empty($msg)){
			$msg = $comment -> get_error_message();
		}
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => $msg,
			'isAdmin' => current_user_can('manage_options')
		)));
	}
	$user = wp_get_current_user();
	do_action('set_comment_cookies', $comment, $user);
	if (isset($_POST['qq'])){
		if (!empty($_POST['qq']) && get_option('argon_comment_enable_qq_avatar') == 'true'){
			$_comment = $comment;
			$_comment -> comment_author_email = $_POST['qq'] . "@avatarqq.com";
			do_action('set_comment_cookies', $_comment, $user);
		}
	}
	$html = wp_list_comments(
		array(
			'type'      => 'comment',
			'callback'  => 'argon_comment_format',
			'echo'      => false
		),
		array($comment)
	);
	$newCaptchaSeed = get_comment_captcha_seed(true);
	$newCaptcha = get_comment_captcha($newCaptchaSeed);
	if (current_user_can('manage_options')){
		$newCaptchaAnswer = get_comment_captcha_answer($newCaptchaSeed);
	}else{
		$newCaptchaAnswer = "";
	}
	exit(json_encode(array(
		'status' => 'success',
		'html' => $html,
		'id' => $comment -> comment_ID,
		'parentID' => $comment -> comment_parent,
		'commentOrder' => (argon_get_option("comment_order") == "" ? "desc" : argon_get_option("comment_order")),
		'newCaptchaSeed' => $newCaptchaSeed,
		'newCaptcha' => $newCaptcha,
		'newCaptchaAnswer' => $newCaptchaAnswer,
		'isAdmin' => current_user_can('manage_options'),
		'isLogin' => is_user_logged_in()
	)));
}
add_action('wp_ajax_ajax_post_comment', 'ajax_post_comment');
add_action('wp_ajax_nopriv_ajax_post_comment', 'ajax_post_comment');
//评论 Markdown 解析
require_once(get_template_directory() . '/parsedown.php');
require_once(get_template_directory() . '/argon-geo-china-provinces.php');
function comment_markdown_parse($comment_content){
	//HTML 过滤
	global $allowedtags;
	//$comment_content = wp_kses($comment_content, $allowedtags);
	//允许评论中额外的 HTML Tag
	$allowedtags['pre'] = array('class' => array());
	$allowedtags['i'] = array('class' => array(), 'aria-hidden' => array());
	$allowedtags['img'] = array('src' => array(), 'alt' => array(), 'class' => array());
	$allowedtags['ol'] = array();
	$allowedtags['ul'] = array();
	$allowedtags['li'] = array();
	$allowedtags['a']['class'] = array();
	$allowedtags['a']['data-src'] = array();
	$allowedtags['a']['target'] = array();
	$allowedtags['h1'] = $allowedtags['h2'] = $allowedtags['h3'] = $allowedtags['h4'] = $allowedtags['h5'] = $allowedtags['h6'] = array();

	//解析 Markdown
	$parsedown = new _Parsedown();
	$res = $parsedown -> text($comment_content);
	/*$res = preg_replace(
		'/<code>([\s\S]*?)<\/code>/',
		'<pre>$1</pre>',
		$res
	);*/

	$res = preg_replace(
		'/<a (.*?)>(.*?)<\/a>/',
		'<a $1 target="_blank" rel="noopener nofollow">$2</a>',
		$res
	);
	//防御兜底：对 Markdown 解析结果再做一次白名单净化，避免存储型 XSS
	$res = wp_kses($res, $allowedtags);
	return $res;
}
//评论发送处理
function post_comment_preprocessing($comment){
	//保存评论未经 Markdown 解析的源码
	$_POST['comment_content_source'] = $comment['comment_content'];
	//Markdown
	if ($_POST['use_markdown'] == 'true' && argon_get_option("argon_comment_allow_markdown") != "false"){
		$comment['comment_content'] = comment_markdown_parse($comment['comment_content']);
	}
	return $comment;
}
add_filter('preprocess_comment' , 'post_comment_preprocessing');
//发送评论通知邮件
function comment_mail_notify($comment){
	if (get_option("argon_comment_allow_mailnotice") != "true"){
		return;
	}
	if ($comment == null){
		return;
	}
	$id = $comment -> comment_ID;
	$commentPostID = $comment -> comment_post_ID;
	$commentAuthor = $comment -> comment_author;
	$parentID = $comment -> comment_parent;
	if ($parentID == 0){
		return;
	}
	$parentComment = get_comment($parentID);
	$parentEmail =  $parentComment -> comment_author_email;
	$parentName = $parentComment -> comment_author;
	$emailTo = "$parentName <$parentEmail>";
	if (get_comment_meta($parentID, "enable_mailnotice", true) == "true"){
		if (check_email_address($parentEmail)){
			$title = __("您在", 'argon') . " 「" . wp_trim_words(get_post_title_by_id($commentPostID), 20) . "」 " . __("的评论有了新的回复", 'argon');
			$fullTitle = __("您在", 'argon') . " 「" . get_post_title_by_id($commentPostID) . "」 " . __("的评论有了新的回复", 'argon');
			$content = htmlspecialchars(get_comment_meta($id, "comment_content_source", true));
			$link = get_permalink($commentPostID) . "#comment-" . $id;
			$unsubscribeLink = site_url("unsubscribe-comment-mailnotice?comment=" . $parentID . "&token=" . get_comment_meta($parentID, "mailnotice_unsubscribe_key", true));
			$html = '
					<!DOCTYPE html>
					<html>
						<head>
							<meta http-equiv="Content-Type" content="text/html charset=UTF-8" />
						</head>
						<body>
							<div style="background: #fff;box-shadow: 0 15px 35px rgba(50,50,93,.1), 0 5px 15px rgba(0,0,0,.07);border-radius: 6px;margin: 15px auto 50px auto;padding: 35px 30px;max-width: min(calc(100% - 100px), 1200px);">
								<div style="font-size:30px;text-align:center;margin-bottom:15px;">' . htmlspecialchars($fullTitle)  .'</div>
								<div style="background: rgba(0, 0, 0, .15);height: 1px;width: 300px;margin: auto;margin-bottom: 35px;"></div>
								<div style="font-size: 18px;border-left: 4px solid rgba(0, 0, 0, .15);width: max-content;width: -moz-max-content;margin: auto;padding: 20px 30px;background: rgba(0,0,0,.08);border-radius: 6px;box-shadow: 0 2px 4px rgba(0,0,0,.075)!important;min-width: 60%;max-width: 90%;margin-bottom: 40px;">
									<div style="margin-bottom: 10px;"><strong><span style="color: #5e72e4;">@' . htmlspecialchars($commentAuthor) . '</span> ' . __('回复了你', "argon") . ':</strong></div>
									' . str_replace("\n", '<div></div>', $content) . ' 
								</div>
								<table width="100%" style="border-collapse:collapse;border:none;empty-cells:show;max-width:100%;box-sizing:border-box" cellspacing="0" cellpadding="0">
									<tbody style="box-sizing:border-box">
										<tr style="box-sizing:border-box" align="center">
											<td style="min-width:5px;box-sizing:border-box">
												<table style="border-collapse:collapse;border:none;empty-cells:show;max-width:100%;box-sizing:border-box" cellspacing="0" cellpadding="0">
													<tbody style="box-sizing:border-box">
														<tr style="box-sizing:border-box">
															<td style="box-sizing:border-box">
																<a href="' . $link . '" style="display: block; line-height: 1; color: #fff;background-color: #5e72e4;border-color: #5e72e4;box-shadow: 0 4px 6px rgba(50,50,93,.11), 0 1px 3px rgba(0,0,0,.08);padding: 15px 25px;font-size: 18px;border-radius: 4px;text-decoration: none; margin: 10px;">' . __('前往查看', "argon") . '</a>
															</td>
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
									</tbody>
								</table>
								<table width="100%" style="border-collapse:collapse;border:none;empty-cells:show;max-width:100%;box-sizing:border-box" cellspacing="0" cellpadding="0">
									<tbody style="box-sizing:border-box">
										<tr style="box-sizing:border-box" align="center">
											<td style="min-width:5px;box-sizing:border-box">
												<table style="border-collapse:collapse;border:none;empty-cells:show;max-width:100%;box-sizing:border-box" cellspacing="0" cellpadding="0">
													<tbody style="box-sizing:border-box">
														<tr style="box-sizing:border-box">
															<td style="box-sizing:border-box">
																<a href="' . $unsubscribeLink . '" style="display: block; line-height: 1;color: #5e72e4;font-size: 16px;text-decoration: none; margin: 10px;">' . __('退订该评论的邮件提醒', "argon") . '</a>
															</td>
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</body>
					</html>';
			$html = apply_filters("argon_comment_mail_notification_content", $html); 
			send_mail($emailTo, $title, $html);
		}
	}
}
//评论发送完成添加 Meta
function post_comment_updatemetas($id){
	$parentID = isset($_POST['comment_parent']) ? intval($_POST['comment_parent']) : 0;
	$comment = get_comment($id);
	$commentPostID = $comment -> comment_post_ID;
	$commentAuthor = $comment -> comment_author;
	$mailnoticeUnsubscribeKey = get_random_token();
	//评论 Markdown 源码
	update_comment_meta($id, "comment_content_source", $_POST['comment_content_source']);
	//评论者 Token
	set_user_token_cookie();
	update_comment_meta($id, "user_token", $_COOKIE["argon_user_token"]);
	//保存初次编辑记录
	$editHistory = array(array(
		'content' => htmlspecialchars(stripslashes($_POST['comment_content_source'])),
		'time' => time(),
		'isfirst' => true
	));
	update_comment_meta($id, "comment_edit_history", addslashes(json_encode($editHistory, JSON_UNESCAPED_UNICODE)));
	//是否启用 Markdown
	if ($_POST['use_markdown'] == 'true' && argon_get_option("argon_comment_allow_markdown") != "false"){
		update_comment_meta($id, "use_markdown", "true");
	}else{
		update_comment_meta($id, "use_markdown", "false");
	}
	//是否启用悄悄话模式
	if ($_POST['private_mode'] == 'true' && get_option("argon_comment_allow_privatemode") == "true"){
		update_comment_meta($id, "private_mode", $_COOKIE["argon_user_token"]);
	}else{
		update_comment_meta($id, "private_mode", "false");
	}
	if (is_comment_private_mode($parentID)){
		//如果父级评论是悄悄话模式则将当前评论可查看者的 Token 跟随父级评论者的 Token
		update_comment_meta($id, "private_mode", get_comment_meta($parentID, "private_mode", true));
	}
	if ($parentID!= 0 && !is_comment_private_mode($parentID)){
		//如果父级评论不是悄悄话模式则当前评论也不是悄悄话模式
		update_comment_meta($id, "private_mode", "false");
	}
	//是否启用邮件通知
	if ($_POST['enable_mailnotice'] == 'true' && get_option("argon_comment_allow_mailnotice") == "true"){
		update_comment_meta($id, "enable_mailnotice", "true");
		update_comment_meta($id, "mailnotice_unsubscribe_key", $mailnoticeUnsubscribeKey);
	}else{
		update_comment_meta($id, "enable_mailnotice", "false");
	}
	//向父级评论发送邮件
	if ($comment -> comment_approved == 1){
		comment_mail_notify($comment);
	}
	//保存 QQ 号
	if (get_option('argon_comment_enable_qq_avatar') == 'true'){
		if (!empty($_POST['qq'])){
			update_comment_meta($id, "qq_number", $_POST['qq']);
		}
	}
}
add_action('comment_post' , 'post_comment_updatemetas');
add_action('comment_unapproved_to_approved', 'comment_mail_notify');
add_rewrite_rule('^unsubscribe-comment-mailnotice/?(.*)$', '/wp-content/themes/argon/unsubscribe-comment-mailnotice.php$1', 'top');
//编辑评论
function user_edit_comment(){
	argon_verify_ajax_nonce();
	header('Content-Type:application/json; charset=utf-8');
	if (get_option("argon_comment_allow_editing") == "false"){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('博主关闭了编辑评论功能', 'argon')
		)));
	}
	$id = $_POST["id"];
	$content = $_POST["comment"];
	$contentSource = $content;
	$max_length = 65535;
	if (mb_strlen($content) > $max_length) {
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('评论内容过长', 'argon')
		)));
	}
	if (!check_comment_token($id) && !check_login_user_same(get_comment_user_id_by_id($id))){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('您不是这条评论的作者或 Token 已过期', 'argon')
		)));
	}
	if ($_POST["comment"] == ""){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('新的评论为空', 'argon')
		)));
	}
	if (get_comment_meta($id, "use_markdown", true) == "true"){
		$content = comment_markdown_parse($content);
	}
	$res = wp_update_comment(array(
		'comment_ID' => $id,
		'comment_content' => $content
	));
	if ($res == 1){
		update_comment_meta($id, "comment_content_source", $contentSource);
		update_comment_meta($id, "edited", "true");
		//保存编辑历史
		$editHistory = json_decode(get_comment_meta($id, "comment_edit_history", true));
		if (is_null($editHistory)){
			$editHistory = array();
		}
		array_push($editHistory, array(
			'content' => htmlspecialchars(stripslashes($contentSource)),
			'time' => time(),
			'isfirst' => false
		));
		update_comment_meta($id, "comment_edit_history", addslashes(json_encode($editHistory, JSON_UNESCAPED_UNICODE)));
		exit(json_encode(array(
			'status' => 'success',
			'msg' => __('编辑评论成功', 'argon'),
			'new_comment' => apply_filters('comment_text', argon_get_comment_text($id), $id),
			'new_comment_source' => htmlspecialchars(stripslashes($contentSource)),
			'can_visit_edit_history' => can_visit_comment_edit_history($id)
		)));
	}else{
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('编辑评论失败，可能原因: 与原评论相同', 'argon'),
		)));
	}
}
add_action('wp_ajax_user_edit_comment', 'user_edit_comment');
add_action('wp_ajax_nopriv_user_edit_comment', 'user_edit_comment');
//置顶评论
function pin_comment(){
	argon_verify_ajax_nonce();
	header('Content-Type:application/json; charset=utf-8');
	if (get_option("argon_enable_comment_pinning") == "false"){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('博主关闭了评论置顶功能', 'argon')
		)));
	}
	if (!current_user_can("moderate_comments")){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('您没有权限进行此操作', 'argon')
		)));
	}
	$id = $_POST["id"];
	$newPinnedStat = $_POST["pinned"] == "true";
	$origPinnedStat = get_comment_meta($id, "pinned", true) == "true";
	if ($newPinnedStat == $origPinnedStat){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => $newPinnedStat ? __('评论已经是置顶状态', 'argon') : __('评论已经是取消置顶状态', 'argon')
		)));
	}
	if (get_comment($id) -> comment_parent != 0){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('不能置顶子评论', 'argon')
		)));
	}
	if (is_comment_private_mode($id)){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('不能置顶悄悄话', 'argon')
		)));
	}
	update_comment_meta($id, "pinned", $newPinnedStat ? "true" : "false");
	exit(json_encode(array(
		'status' => 'success',
		'msg' => $newPinnedStat ? __('置顶评论成功', 'argon') : __('取消置顶成功', 'argon'),
	)));
}
add_action('wp_ajax_pin_comment', 'pin_comment');
add_action('wp_ajax_nopriv_pin_comment', 'pin_comment');
//输出评论分页页码
function get_argon_formatted_comment_paginate_links($maxPageNumbers, $extraClasses = ''){
	$args = array(
		'prev_text' => '',
		'next_text' => '',
		'before_page_number' => '',
		'after_page_number' => '',
		'show_all' => True,
		'echo' => False
	);
	$res = paginate_comments_links($args);
	//单引号转双引号 & 去除上一页和下一页按钮
	$res = preg_replace(
		'/\'/',
		'"',
		$res
	);
	$res = preg_replace(
		'/<a class="prev page-numbers" href="(.*?)">(.*?)<\/a>/',
		'',
		$res
	);
	$res = preg_replace(
		'/<a class="next page-numbers" href="(.*?)">(.*?)<\/a>/',
		'',
		$res
	);
	//寻找所有页码标签
	preg_match_all('/<(.*?)>(.*?)<\/(.*?)>/' , $res , $pages);
	$total = count($pages[0]);
	$current = 0;
	$urls = array();
	for ($i = 0; $i < $total; $i++){
		if (preg_match('/<span(.*?)>(.*?)<\/span>/' , $pages[0][$i])){
			$current = $i + 1;
		}else{
			preg_match('/<a(.*?)href="(.*?)">(.*?)<\/a>/' , $pages[0][$i] , $tmp);
			$urls[$i + 1] = $tmp[2];
		}
	}

	if ($total == 0){
		return "";
	}

	//计算页码起始
	$from = max($current - ($maxPageNumbers - 1) / 2 , 1);
	$to = min($current + $maxPageNumbers - ( $current - $from + 1 ) , $total);
	if ($to - $from + 1 < $maxPageNumbers){
		$to = min($current + ($maxPageNumbers - 1) / 2 , $total);
		$from = max($current - ( $maxPageNumbers - ( $to - $current + 1 ) ) , 1);
	}
	//生成新页码
	$html = "";
	if ($from > 1){
		$html .= '<li class="page-item"><div aria-label="First Page" class="page-link" href="' . $urls[1] . '"><i class="fa fa-angle-double-left" aria-hidden="true"></i></div></li>';
	}
	if ($current > 1){
		$html .= '<li class="page-item"><div aria-label="Previous Page" class="page-link" href="' . $urls[$current - 1] . '"><i class="fa fa-angle-left" aria-hidden="true"></i></div></li>';
	}
	for ($i = $from; $i <= $to; $i++){
		if ($current == $i){
			$html .= '<li class="page-item active"><span class="page-link" style="cursor: default;">' . $i . '</span></li>';
		}else{
			$html .= '<li class="page-item"><div class="page-link" href="' . $urls[$i] . '">' . $i . '</div></li>';
		}
	}
	if ($current < $total){
		$html .= '<li class="page-item"><div aria-label="Next Page" class="page-link" href="' . $urls[$current + 1] . '"><i class="fa fa-angle-right" aria-hidden="true"></i></div></li>';
	}
	if ($to < $total){
		$html .= '<li class="page-item"><div aria-label="Last Page" class="page-link" href="' . $urls[$total] . '"><i class="fa fa-angle-double-right" aria-hidden="true"></i></div></li>';
	}
	return '<nav id="comments_navigation" class="comments-navigation"><ul class="pagination' . $extraClasses . '">' . $html . '</ul></nav>';
}
function get_argon_formatted_comment_paginate_links_for_all_platforms(){
	return get_argon_formatted_comment_paginate_links(7) . get_argon_formatted_comment_paginate_links(5, " pagination-mobile");
}
function get_argon_comment_paginate_links_prev_url(){
	$args = array(
		'prev_text' => '',
		'next_text' => '',
		'before_page_number' => '',
		'after_page_number' => '',
		'show_all' => True,
		'echo' => False
	);
	$str = paginate_comments_links($args);
	//单引号转双引号
	$str = preg_replace(
		'/\'/',
		'"',
		$str
	);
	//获取上一页地址
	$url = "";
	preg_match(
		'/<a class="prev page-numbers" href="(.*?)">(.*?)<\/a>/',
		$str,
		$url
	);
	if (!isset($url[1])){
		return NULL;
	}
	
	if (isset($_GET['fill_first_page']) || strpos(parse_url($_SERVER['REQUEST_URI'])['path'], 'comment-page-') === false){
		$parsed_url = parse_url($url[1]);
		if (!isset($parsed_url['query'])){
			$parsed_url['query'] = 'fill_first_page=true';
		}else
			if (strpos($parsed_url['query'], 'fill_first_page=true') === false){
			$parsed_url['query'] .= '&fill_first_page=true';
		}
		return $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'] . '?' . $parsed_url['query'];
	}
	return $url[1];
}
//评论重排序（置顶优先）
$GLOBALS['comment_order'] = argon_get_option('comment_order');
function argon_comment_cmp($a, $b){
	$a_pinned = get_comment_meta($a -> comment_ID, 'pinned', true);
	$b_pinned = get_comment_meta($b -> comment_ID, 'pinned', true);
	if ($a_pinned != "true"){
		$a_pinned = "false";
	}
	if ($b_pinned != "true"){
		$b_pinned = "false";
	}
	if ($a_pinned == $b_pinned){
		// 同置顶状态：按时间排序（desc 新在前）
		if ($GLOBALS['comment_order'] == 'desc'){
			return $b -> comment_date_gmt <=> $a -> comment_date_gmt;
		}
		return $a -> comment_date_gmt <=> $b -> comment_date_gmt;
	}else{
		// 置顶评论始终排在前面
		return ($a_pinned == "true") ? -1 : 1;
	}
}
function argon_get_comments(){
	global $wp_query;
	/*$cpage = get_query_var('cpage') ?? 1;
	$maxiumPages = $wp_query -> max_num_pages;*/
	$args = array(
		'post__in'		 => array(get_the_ID()),
		'type'           => 'comment',
		'order'          => 'DESC',
		'orderby'        => 'comment_date_gmt',
		'status'         => 'approve'
	);
	if (is_user_logged_in()){
		$args['include_unapproved'] = array(get_current_user_id());
	} else {
		$unapproved_email = wp_get_unapproved_comment_author_email();
		if ($unapproved_email) {
			$args['include_unapproved'] = array($unapproved_email);
		}
	}

	$comment_query = new WP_Comment_Query;
	$comments = $comment_query -> query($args);
	
	if (get_option("argon_enable_comment_pinning", "false") == "true"){
		usort($comments, "argon_comment_cmp");
	}else{
		// 与前端新评论插入方向保持一致：仅当 comment_order 明确为 'asc'（旧在上）时才反转；
		// 未设置或 'desc' 时保持 DESC（新在上），对应 argontheme.js 的 prepend 行为
		if (argon_get_option('comment_order') == 'asc'){
			$comments = array_reverse($comments);
		}
	}
	
	//向评论数组中填充 placeholder comments 以填满第一页
	if (get_option("argon_comment_pagination_type", "feed") == "page"){
		return $comments;
	}
	if (!isset($_GET['fill_first_page']) && strpos(parse_url($_SERVER['REQUEST_URI'])['path'], 'comment-page-') !== false){
		return null;
	}
	$comments_per_page = get_option('comments_per_page');
	$comments_count = 0; 
	foreach ($comments as $comment){
		if ($comment -> comment_parent == 0){
			$comments_count++;
		}
	}
	$comments_pages = ceil($comments_count / $comments_per_page);
	if ($comments_pages > 1){
		$placeholders_count = $comments_pages * $comments_per_page - $comments_count;
		while ($placeholders_count--){
			array_unshift($comments, new WP_Comment((object) array(
				"placeholder" => true
			)));
		}
	}
	return $comments;
}
//QQ Avatar 获取
function get_avatar_by_qqnumber($avatar){
	global $comment;
	if (!isset($comment) || !isset($comment -> comment_ID)){
		return $avatar;
	}
	$qqnumber = get_comment_meta($comment -> comment_ID, 'qq_number', true);
	if (!empty($qqnumber)){
		preg_match_all('/width=\'(.*?)\'/', $avatar, $preg_res);
		$size = $preg_res[1][0];
		return "<img src='https://q1.qlogo.cn/g?b=qq&s=640&nk=" . $qqnumber ."' class='avatar avatar-" . $size . " photo' width='" . $size . "' height='" . $size . "'>";
	}
	return $avatar;
}
add_filter('get_avatar', 'get_avatar_by_qqnumber');
//判断 QQ 号合法性
if (!function_exists('check_qqnumber')){
	function check_qqnumber($qqnumber){
		if (preg_match("/^[1-9][0-9]{4,10}$/", $qqnumber)){
			return true;
		} else {
			return false;
		}
	}
}
//获取顶部 Banner 背景图（用户指定或必应日图）
function get_banner_background_url(){
	$url = get_option("argon_banner_background_url");
	if ($url == "--bing--"){
		$lastUpdated = get_option("argon_bing_banner_background_last_updated_time");
		if ($lastUpdated == ""){
			$lastUpdated = 0;
		}
		$now = time();
		if ($now - $lastUpdated < 3600){
			return get_option("argon_bing_banner_background_last_updated_url");
		}else{
			$data = json_decode(@file_get_contents('https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1') , true);
			$url = "//bing.com" . $data['images'][0]['url'];
			update_option("argon_bing_banner_background_last_updated_time" , $now);
			update_option("argon_bing_banner_background_last_updated_url" , $url);
			return $url;
		}
	}else{
		return $url;
	}
}
//Lazyload 对 <img> 标签预处理以加载 Lazyload
function argon_lazyload($content){
	$lazyload_loading_style = get_option('argon_lazyload_loading_style');
	if ($lazyload_loading_style == ''){
		$lazyload_loading_style = 'none';
	}
	$lazyload_loading_style = "lazyload-style-" . $lazyload_loading_style;

	if(!is_feed() && !is_robots() && !is_home()){
		$use_dom = class_exists('DOMDocument') && $content != '';
		if ($use_dom){
			$dom = new DOMDocument();
			$prev = libxml_use_internal_errors(true);
			// 以 HTML 片段方式解析，避免回溯爆炸（ReDoS），并保留 UTF-8
			@$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			libxml_clear_errors();
			libxml_use_internal_errors($prev);
			$imgs = $dom->getElementsByTagName('img');
			foreach ($imgs as $img){
				$full_url = $img->getAttribute('data-full-url');
				if ($full_url != ''){
					$img->setAttribute('data-original', $full_url);
				}else{
					$src = $img->getAttribute('src');
					if ($src != ''){
						$img->setAttribute('data-original', $src);
					}
				}
				$img->removeAttribute('srcset'); // 移除 srcset，避免浏览器提前加载
				$existing_class = $img->getAttribute('class');
				$img->setAttribute('class', trim('lazyload ' . $lazyload_loading_style . ' ' . $existing_class));
				$img->setAttribute('src', 'data:image/svg+xml;base64,PCEtLUFyZ29uTG9hZGluZy0tPgo8c3ZnIHdpZHRoPSIxIiBoZWlnaHQ9IjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgc3Ryb2tlPSIjZmZmZmZmMDAiPjxnPjwvZz4KPC9zdmc+');
			}
			$content = $dom->saveHTML();
			if ($content == ''){
				$use_dom = false; // 解析失败则回退正则
			}
		}
		if (!$use_dom){
			$content = preg_replace('/<img(.*?)src=[\'"](.*?)[\'"](.*?)((\/>)|(<\/img>))/i',"<img class=\"lazyload " . $lazyload_loading_style . "\" src=\"data:image/svg+xml;base64,PCEtLUFyZ29uTG0aG9hZGluZy8tPgo8c3ZnIHdpZHRoPSIxIiBoZWlnaHQ9IjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgc3Ryb2tlPSIjZmZmZmZmMDAiPjxnPjwvZz4KPC9zdmc+\" \$1data-original=\"\$2\" src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAANSURBVBhXYzh8+PB/AAffA0nNPuCLAAAAAElFTkSuQmCC\"\$3$4" , $content);
			$content = preg_replace('/<img(.*?)data-full-url=[\'"]([^\'"]+)[\'"](.*)>/i',"<img$1data-full-url=\"$2\" data-original=\"$2\"$3>" , $content);
			$content = preg_replace('/<img(.*?)srcset=[\'"](.*?)[\'"](.*?)>/i',"<img$1$3>" , $content);
		}
	}
	return $content;
}
function argon_fancybox($content){
	if(!is_feed() && !is_robots() && !is_home()){
		if (argon_get_option('argon_enable_lazyload') != 'false'){
			$content = preg_replace('/<img(.*?)data-original=[\'"](.*?)[\'"](.*?)((\/>)|>|(<\/img>))/i',"<a class='argon-lightbox-img' data-fancybox='post-images' href='$2'>$0</a>" , $content);
		}else{
			$content = preg_replace('/<img(.*?)src=[\'"](.*?)[\'"](.*?)((\/>)|>|(<\/img>))/i',"<a class='argon-lightbox-img' data-fancybox='post-images' href='$2'>$0</a>" , $content);
		}
	}
	return $content;
}
function the_content_filter($content){
	if (argon_get_option('argon_enable_lazyload') != 'false'){
		$content = argon_lazyload($content);
	}
	if (get_option('argon_enable_fancybox') != 'false' && get_option('argon_enable_zoomify') == 'false'){
		$content = argon_fancybox($content);
	}
	global $post;
	$custom_css = get_post_meta($post -> ID, 'argon_custom_css', true);
	if (!empty($custom_css)){
		$content .= "<style>" . $custom_css . "</style>";
	}

	return $content;
}
add_filter('the_content' , 'the_content_filter',20);
//使用 CDN 加速 gravatar
function gravatar_cdn($url){
	$cdn = argon_get_option('argon_gravatar_cdn', 'gravatar.pho.ink/avatar/');
	$cdn = str_replace("http://", "", $cdn);
	$cdn = str_replace("https://", "", $cdn);
	if (substr($cdn, -1) != '/'){
		$cdn .= "/";
	}
	$url = preg_replace("/\/\/(.*?).gravatar.com\/avatar\//", "//" . $cdn, $url);
	return $url;
}
if (argon_get_option('argon_gravatar_cdn' , '') != ''){
	add_filter('get_avatar_url', 'gravatar_cdn');
}
function text_gravatar($url){
	$url = preg_replace("/[?&]d[^&]+/i", "" , $url);
	$url .= '&d=404';
	return $url;
}
if (get_option('argon_text_gravatar', 'false') == 'true' && !is_admin()){
	add_filter('get_avatar_url', 'text_gravatar');
}
//说说点赞
function get_shuoshuo_upvotes($ID){
	$count_key = 'upvotes';
	$count = get_post_meta($ID, $count_key, true);
	if ($count==''){
		delete_post_meta($ID, $count_key);
		add_post_meta($ID, $count_key, '0');
		$count = '0';
	}
	return number_format_i18n($count);
}
function set_shuoshuo_upvotes($ID){
	if (get_post_type($ID) != 'shuoshuo'){
		return;
	}
	$count_key = 'upvotes';
	$count = get_post_meta($ID, $count_key, true);
	if ($count==''){
		delete_post_meta($ID, $count_key);
		add_post_meta($ID, $count_key, '1');
	} else {
		update_post_meta($ID, $count_key, $count + 1);
	}
}
function upvote_shuoshuo(){
	argon_verify_ajax_nonce();
	header('Content-Type:application/json; charset=utf-8');
	$ID = $_POST["shuoshuo_id"];
	$upvotedList = isset( $_COOKIE['argon_shuoshuo_upvoted'] ) ? $_COOKIE['argon_shuoshuo_upvoted'] : '';
	$ids = array_filter(array_map('intval', explode(',', $upvotedList)));
	if (in_array((int) $ID, $ids, true)){
		exit(json_encode(array(
			'status' => 'failed',
			'msg' => __('该说说已被赞过', 'argon'),
			'total_upvote' => get_shuoshuo_upvotes($ID)
		)));
	}
	set_shuoshuo_upvotes($ID);
	$ids[] = (int) $ID;
	// 去重 + 保留最近 200 个，防止 Cookie 累积超限导致请求头过大 (HTTP 400)
	$ids = array_unique($ids);
	$ids = array_slice($ids, -200);
	setcookie('argon_shuoshuo_upvoted', implode(',', $ids) . ',', time() + 3153600000 , '/');
	exit(json_encode(array(
		'ID' => $ID,
		'status' => 'success',
		'msg' => __('点赞成功', 'argon'),
		'total_upvote' => get_shuoshuo_upvotes($ID)
	)));
}
add_action('wp_ajax_upvote_shuoshuo' , 'upvote_shuoshuo');
add_action('wp_ajax_nopriv_upvote_shuoshuo' , 'upvote_shuoshuo');
//检测页面底部版权是否被修改
function alert_footer_copyright_changed(){ ?>
	<div class='notice notice-warning is-dismissible'>
		<p><?php _e("警告：你可能修改了 Argon 主题页脚的版权声明，Argon 主题要求你至少保留主题的 Github 链接或主题的发布文章链接。", 'argon');?></p>
	</div>
<?php }
function check_footer_copyright(){
	$cached = get_transient('argon_footer_copyright_check');
	if ($cached !== false){
		if ($cached === 'changed'){
			add_action('admin_notices', 'alert_footer_copyright_changed');
		}
		return;
	}
	$footer = file_get_contents(get_theme_root() . "/" . wp_get_theme() -> template . "/footer.php");
	if ((strpos($footer, "github.com/Asunano/argon-theme") === false) && (strpos($footer, "solstice23.top") === false)){
		set_transient('argon_footer_copyright_check', 'changed', DAY_IN_SECONDS);
		add_action('admin_notices', 'alert_footer_copyright_changed');
	}else{
		set_transient('argon_footer_copyright_check', 'ok', DAY_IN_SECONDS);
	}
}
check_footer_copyright();
//颜色计算
function rgb2hsl($R,$G,$B){
	$r = $R / 255;
	$g = $G / 255;
	$b = $B / 255;

	$var_Min = min($r, $g, $b);
	$var_Max = max($r, $g, $b);
	$del_Max = $var_Max - $var_Min;

	$L = ($var_Max + $var_Min) / 2;

	if ($del_Max == 0){
		$H = 0;
		$S = 0;
	}else{
		if ($L < 0.5){
			$S = $del_Max / ($var_Max + $var_Min);
		}else{
			$S = $del_Max / (2 - $var_Max - $var_Min);
		}

		$del_R = ((($var_Max - $r) / 6) + ($del_Max / 2)) / $del_Max;
		$del_G = ((($var_Max - $g) / 6) + ($del_Max / 2)) / $del_Max;
		$del_B = ((($var_Max - $b) / 6) + ($del_Max / 2)) / $del_Max;

		if ($r == $var_Max){
			$H = $del_B - $del_G;
		}
		else if ($g == $var_Max){
			$H = (1 / 3) + $del_R - $del_B;
		}
		else if ($b == $var_Max){
			$H = (2 / 3) + $del_G - $del_R;
		}
		if ($H < 0) $H += 1;
		if ($H > 1) $H -= 1;
	}
	return array(
		'h' => $H,//0~1
		's' => $S,
		'l' => $L,
		'H' => round($H * 360),//0~360
		'S' => round($S * 100),//0~100
		'L' => round($L * 100),//0~100
	);
}
function Hue_2_RGB($v1,$v2,$vH){
	if ($vH < 0) $vH += 1;
	if ($vH > 1) $vH -= 1;
	if ((6 * $vH) < 1) return ($v1 + ($v2 - $v1) * 6 * $vH);
	if ((2 * $vH) < 1) return $v2;
	if ((3 * $vH) < 2) return ($v1 + ($v2 - $v1) * ((2 / 3) - $vH) * 6);
	return $v1;
}
function hsl2rgb($h,$s,$l){
	if ($s == 0){
		$r = $l;
		$g = $l;
		$b = $l;
	}
	else{
		if ($l < 0.5){
			$var_2 = $l * (1 + $s);
		}
		else{
			$var_2 = ($l + $s) - ($s * $l);
		}
		$var_1 = 2 * $l - $var_2;
		$r = Hue_2_RGB($var_1, $var_2, $h + (1 / 3));
		$g = Hue_2_RGB($var_1, $var_2, $h);
		$b = Hue_2_RGB($var_1, $var_2, $h - (1 / 3));
	}
	return array(
		'R' => round($r * 255),//0~255
		'G' => round($g * 255),
		'B' => round($b * 255),
		'r' => $r,//0~1
		'g' => $g,
		'b' => $b
	);
}
function rgb2hex($r,$g,$b){
	$hex = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
	$rh = "";
	$gh = "";
	$bh = "";
	while (strlen($rh) < 2){
		$rh = $hex[$r%16] . $rh;
		$r = floor($r / 16);
	}
	while (strlen($gh) < 2){
		$gh = $hex[$g%16] . $gh;
		$g = floor($g / 16);
	}
	while (strlen($bh) < 2){
		$bh = $hex[$b%16] . $bh;
		$b = floor($b / 16);
	}
	return "#".$rh.$gh.$bh;
}
function hexstr2rgb($hex){
	//$hex: #XXXXXX
	return array(
		'R' => hexdec(substr($hex,1,2)),//0~255
		'G' => hexdec(substr($hex,3,2)),
		'B' => hexdec(substr($hex,5,2)),
		'r' => hexdec(substr($hex,1,2)) / 255,//0~1
		'g' => hexdec(substr($hex,3,2)) / 255,
		'b' => hexdec(substr($hex,5,2)) / 255
	);
}
function rgb2str($rgb){
	return $rgb['R']. "," .$rgb['G']. "," .$rgb['B'];
}
function hex2str($hex){
	return rgb2str(hexstr2rgb($hex));
}
function rgb2gray($R,$G,$B){
	return round($R * 0.299 + $G * 0.587 + $B * 0.114);
}
function hex2gray($hex){
	$rgb_array = hexstr2rgb($hex);
	return rgb2gray($rgb_array['R'], $rgb_array['G'], $rgb_array['B']);
}
function checkHEX($hex){
	if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
		return false;
	}
	return true;
}
//编辑文章界面新增 Meta 编辑模块
function argon_meta_box_1(){
	wp_nonce_field("argon_meta_box_nonce_action", "argon_meta_box_nonce");
	global $post;
	?>
		<h4><?php _e("显示字数和预计阅读时间", 'argon');?></h4>
		<?php $argon_meta_hide_readingtime = get_post_meta($post->ID, "argon_hide_readingtime", true);?>
		<select name="argon_meta_hide_readingtime" id="argon_meta_hide_readingtime">
			<option value="false" <?php if ($argon_meta_hide_readingtime=='false'){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
			<option value="true" <?php if ($argon_meta_hide_readingtime=='true'){echo 'selected';} ?>><?php _e("不显示", 'argon');?></option>
		</select>
		<p style="margin-top: 15px;"><?php _e("是否显示字数和预计阅读时间 Meta 信息", 'argon');?></p>
		<h4><?php _e("Meta 中隐藏发布时间和分类", 'argon');?></h4>
		<?php $argon_meta_simple = get_post_meta($post->ID, "argon_meta_simple", true);?>
		<select name="argon_meta_simple" id="argon_meta_simple">
			<option value="false" <?php if ($argon_meta_simple=='false'){echo 'selected';} ?>><?php _e("不隐藏", 'argon');?></option>
			<option value="true" <?php if ($argon_meta_simple=='true'){echo 'selected';} ?>><?php _e("隐藏", 'argon');?></option>
		</select>
		<p style="margin-top: 15px;"><?php _e("适合特定的页面，例如友链页面。开启后文章 Meta 的第一行只显示阅读数和评论数。", 'argon');?></p>
		<h4><?php _e("使用文章中第一张图作为头图", 'argon');?></h4>
		<?php $argon_first_image_as_thumbnail = get_post_meta($post->ID, "argon_first_image_as_thumbnail", true);?>
		<select name="argon_first_image_as_thumbnail" id="argon_first_image_as_thumbnail">
			<option value="default" <?php if ($argon_first_image_as_thumbnail=='default'){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
			<option value="true" <?php if ($argon_first_image_as_thumbnail=='true'){echo 'selected';} ?>><?php _e("使用", 'argon');?></option>
			<option value="false" <?php if ($argon_first_image_as_thumbnail=='false'){echo 'selected';} ?>><?php _e("不使用", 'argon');?></option>
		</select>
		<h4><?php _e("显示文章过时信息", 'argon');?></h4>
		<?php $argon_show_post_outdated_info = get_post_meta($post->ID, "argon_show_post_outdated_info", true);?>
		<div style="display: flex;">
			<select name="argon_show_post_outdated_info" id="argon_show_post_outdated_info">
				<option value="default" <?php if ($argon_show_post_outdated_info=='default'){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
				<option value="always" <?php if ($argon_show_post_outdated_info=='always'){echo 'selected';} ?>><?php _e("一直显示", 'argon');?></option>
				<option value="never" <?php if ($argon_show_post_outdated_info=='never'){echo 'selected';} ?>><?php _e("永不显示", 'argon');?></option>
			</select>
			<button id="apply_show_post_outdated_info" type="button" class="components-button is-primary" style="height: 22px; display: none;"><?php _e("应用", 'argon');?></button>
		</div>
		<p style="margin-top: 15px;"><?php _e("单独控制该文章的过时信息显示。", 'argon');?></p>
		<h4><?php _e("文末附加内容", 'argon');?></h4>
		<?php $argon_after_post = get_post_meta($post->ID, "argon_after_post", true);?>
		<textarea name="argon_after_post" id="argon_after_post" rows="3" cols="30" style="width:100%;"><?php if (!empty($argon_after_post)){echo $argon_after_post;} ?></textarea>
		<p style="margin-top: 15px;"><?php _e("给该文章设置单独的文末附加内容，留空则跟随全局，设为 <code>--none--</code> 则不显示。", 'argon');?></p>
		<h4><?php _e("自定义 CSS", 'argon');?></h4>
		<?php $argon_custom_css = get_post_meta($post->ID, "argon_custom_css", true);?>
		<textarea name="argon_custom_css" id="argon_custom_css" rows="5" cols="30" style="width:100%;"><?php if (!empty($argon_custom_css)){echo $argon_custom_css;} ?></textarea>
		<p style="margin-top: 15px;"><?php _e("给该文章添加单独的 CSS", 'argon');?></p>

		<script>$ = window.jQuery;</script>
		<script>
			function showAlert(type, message){
				if (!wp.data){
					alert(message);
					return;
				}
				wp.data.dispatch('core/notices').createNotice(
					type,
					message,
					{ type: "snackbar", isDismissible: true, }
				);
			}
			$("select[name=argon_show_post_outdated_info").change(function(){
				$("#apply_show_post_outdated_info").css("display", "");
			});
			$("#apply_show_post_outdated_info").click(function(){
				$("#apply_show_post_outdated_info").addClass("is-busy").attr("disabled", "disabled").css("opacity", "0.5");
				$("#argon_show_post_outdated_info").attr("disabled", "disabled");
				var data = {
					action: 'update_post_meta_ajax',
					argon_meta_box_nonce: $("#argon_meta_box_nonce").val(),
					post_id: <?php echo $post->ID; ?>,
					meta_key: 'argon_show_post_outdated_info',
					meta_value: $("select[name=argon_show_post_outdated_info]").val()
				};
				$.ajax({
					url: ajaxurl,
					type: 'post',
					data: data,
					success: function(response) {
						$("#apply_show_post_outdated_info").removeClass("is-busy").removeAttr("disabled").css("opacity", "1");
						$("#argon_show_post_outdated_info").removeAttr("disabled");
						if (response.status == "failed"){
							showAlert("failed", "<?php _e("应用失败", 'argon');?>");
							return;
						}
						$("#apply_show_post_outdated_info").css("display", "none");
						showAlert("success", "<?php _e("应用成功", 'argon');?>");
					},
					error: function(response) {
						$("#apply_show_post_outdated_info").removeClass("is-busy").removeAttr("disabled").css("opacity", "1");
						$("#argon_show_post_outdated_info").removeAttr("disabled");
						showAlert("failed", "<?php _e("应用失败", 'argon');?>");
					}
				});
			});
		</script>
	<?php
}
function argon_add_meta_boxes(){
	add_meta_box('argon_meta_box_1', __("文章设置", 'argon'), 'argon_meta_box_1', array('post', 'page'), 'side', 'low');
}
add_action('admin_menu', 'argon_add_meta_boxes');
function argon_save_meta_data($post_id){
	if (!isset($_POST['argon_meta_box_nonce'])){
		return $post_id;
	}
	$nonce = $_POST['argon_meta_box_nonce'];
	if (!wp_verify_nonce($nonce, 'argon_meta_box_nonce_action')){
		return $post_id;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
		return $post_id;
	}
	if ($_POST['post_type'] == 'post'){
		if (!current_user_can('edit_post', $post_id)){
			return $post_id;
		}
	}
	if ($_POST['post_type'] == 'page'){
		if (!current_user_can('edit_page', $post_id)){
			return $post_id;
		}
	}
	update_post_meta($post_id, 'argon_hide_readingtime', sanitize_text_field($_POST['argon_meta_hide_readingtime']));
	update_post_meta($post_id, 'argon_meta_simple', sanitize_text_field($_POST['argon_meta_simple']));
	update_post_meta($post_id, 'argon_first_image_as_thumbnail', sanitize_text_field($_POST['argon_first_image_as_thumbnail']));
	update_post_meta($post_id, 'argon_show_post_outdated_info', sanitize_text_field($_POST['argon_show_post_outdated_info']));
	update_post_meta($post_id, 'argon_after_post', sanitize_text_field($_POST['argon_after_post']));
	update_post_meta($post_id, 'argon_custom_css', wp_strip_all_tags($_POST['argon_custom_css']));
}
add_action('save_post', 'argon_save_meta_data');
function update_post_meta_ajax(){
	if (!isset($_POST['argon_meta_box_nonce'])){
		return;
	}
	$nonce = $_POST['argon_meta_box_nonce'];
	if (!wp_verify_nonce($nonce, 'argon_meta_box_nonce_action')){
		return;
	}
	header('Content-Type:application/json; charset=utf-8');
	if (!isset($_POST["post_id"]) || !isset($_POST["meta_key"]) || !isset($_POST["meta_value"])){
		status_header(400);
		exit(json_encode(array(
			'status' => 'failed',
			'message' => 'invalid_request'
		)));
	}
	$post_id = intval($_POST["post_id"]);
	$meta_key = $_POST["meta_key"];
	$meta_value = $_POST["meta_value"];
	$allowed_meta_keys = array(
		'argon_hide_readingtime',
		'argon_meta_simple',
		'argon_first_image_as_thumbnail',
		'argon_show_post_outdated_info',
		'argon_after_post',
		'argon_custom_css'
	);

	if (!current_user_can('edit_post', $post_id) || !in_array($meta_key, $allowed_meta_keys, true)){
		status_header(403);
		exit(json_encode(array(
			'status' => 'failed',
			'message' => 'forbidden'
		)));
	}

	if (get_post_meta($post_id, $meta_key, true) == $meta_value){
		exit(json_encode(array(
			'status' => 'success'
		)));
	}

	$result = update_post_meta($post_id, $meta_key, $meta_value);

	if ($result){
		exit(json_encode(array(
			'status' => 'success'
		)));
	}else{
		exit(json_encode(array(
			'status' => 'failed'
		)));
	}
}
add_action('wp_ajax_update_post_meta_ajax' , 'update_post_meta_ajax');
//首页显示说说
function argon_home_add_post_type_shuoshuo($query){
	if (is_home() && $query -> is_main_query()){
		$query -> set('post_type', array('post', 'shuoshuo'));
	}
	return $query;
}
if (get_option("argon_home_show_shuoshuo") == "true"){
	add_action('pre_get_posts', 'argon_home_add_post_type_shuoshuo');
}
//首页隐藏特定分类文章
function argon_home_hide_categories($query){
	if (is_home() && $query -> is_main_query()){
		$excludeCategories = explode(",", get_option("argon_hide_categories"));
		$excludeCategories = array_map(function($cat) { return -$cat; }, $excludeCategories);
		$query -> set('category__not_in', $excludeCategories);
		$query -> set('tag__not_in', $excludeCategories);
	}
	return $query;
}
if (get_option("argon_hide_categories") != ""){
	add_action('pre_get_posts', 'argon_home_hide_categories');
}
//文章过时信息显示
function argon_get_post_outdated_info(){
	global $post;
	$post_show_outdated_info_status = strval(get_post_meta($post -> ID, 'argon_show_post_outdated_info', true));
	if (get_option("argon_outdated_info_tip_type") == "toast"){
		$before = '<div id="post_outdate_toast" style="display:none;" data-text="';
		$after = '"></div>';
	}else{
		$before = "<div class='post-outdated-info'><i class='fa fa-info-circle' aria-hidden='true'></i>";
		$after = "</div>";
	}
	$content = get_option('argon_outdated_info_tip_content') == '' ? '本文最后更新于 %date_delta% 天前，其中的信息可能已经有所发展或是发生改变。' : get_option('argon_outdated_info_tip_content');
	$delta = get_option('argon_outdated_info_days') == '' ? (-1) : get_option('argon_outdated_info_days');
	if ($delta == -1){
		$delta = 2147483647;
	}
	$post_date_delta = floor((current_time('timestamp') - get_the_time("U")) / (60 * 60 * 24));
	$modify_date_delta = floor((current_time('timestamp') - get_the_modified_time("U")) / (60 * 60 * 24));
	if (get_option("argon_outdated_info_time_type") == "createdtime"){
		$date_delta = $post_date_delta;
	}else{
		$date_delta = $modify_date_delta;
	}
	if (($date_delta <= $delta && $post_show_outdated_info_status != 'always') || $post_show_outdated_info_status == 'never'){
		return "";
	}
	$content = str_replace("%date_delta%", $date_delta, $content);
	$content = str_replace("%modify_date_delta%", $modify_date_delta, $content);
	$content = str_replace("%post_date_delta%", $post_date_delta, $content);
	if (get_option("argon_outdated_info_tip_type") == "toast"){
		// 用于 HTML 属性上下文；JS 端会再用 escapeHtml 转义后注入 toast，避免 data 属性来回解码导致脚本注入
		return $before . esc_attr($content) . $after;
	}else{
		return $before . esc_html($content) . $after;
	}
}
//Gutenberg 编辑器区块
function argon_init_gutenberg_blocks() {
	wp_register_script(
		'argon-gutenberg-block-js',
		$GLOBALS['assets_path'].'/gutenberg/dist/blocks.build.js',
		array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor'),
		null,
		true
	);
	wp_register_style(
		'argon-gutenberg-block-backend-css',
		$GLOBALS['assets_path'].'/gutenberg/dist/blocks.editor.build.css',
		array('wp-edit-blocks'),
		filemtime(get_template_directory() . '/gutenberg/dist/blocks.editor.build.css')
	);
	$argon_blocks = array(
		'argon/alert',
		'argon/admonition',
		'argon/collapse',
		'argon/github',
		'argon/timeline',
		'argon/progressbar',
		'argon/todolist',
		'argon/tabpanel',
	);
	foreach ( $argon_blocks as $argon_block ) {
		register_block_type(
			$argon_block,
			array(
				//'style'         => 'argon-gutenberg-block-frontend-css',
				'editor_script' => 'argon-gutenberg-block-js',
				'editor_style'  => 'argon-gutenberg-block-backend-css',
			)
		);
	}
}
add_action('init', 'argon_init_gutenberg_blocks');
function argon_add_gutenberg_category($block_categories, $editor_context) {
	if (!empty($editor_context->post)){
		array_push(
			$block_categories,
			array(
				'slug'  => 'argon',
				'title' => 'Argon',
				'icon'  => null,
			)
		);
	}
	return $block_categories;
}
add_filter('block_categories_all', 'argon_add_gutenberg_category', 10, 2);
function argon_admin_i18n_info(){
	echo "<script>var argon_language = '" . esc_js(argon_get_locate()) . "';</script>";
}
add_filter('admin_head', 'argon_admin_i18n_info');
//主题文章短代码解析
function shortcode_content_preprocess($attr, $content = ""){
	if ( isset( $attr['nested'] ) ? $attr['nested'] : 'true' != 'false' ){
		return do_shortcode($content);
	}else{
		return $content;
	}	
}
add_shortcode('br','shortcode_br');
function shortcode_br($attr,$content=""){
	return "</br>";
}
add_shortcode('label','shortcode_label');
function shortcode_label($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<span class='badge";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'indigo';
	switch ($color){
		case 'green':
			$out .= " badge-success";
			break;
		case 'red':
			$out .= " badge-danger";
			break;
		case 'orange':
			$out .= " badge-warning";
			break;
		case 'blue':
			$out .= " badge-info";
			break;
		case 'indigo':
		default:
			$out .= " badge-primary";
			break;
	}
	$shape = isset( $attr['shape'] ) ? $attr['shape'] : 'square';
	if ($shape=="round"){
		$out .= " badge-pill";
	}
	$out .= "'>" . $content . "</span>";
	return $out;
}
add_shortcode('progressbar','shortcode_progressbar');
function shortcode_progressbar($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<div class='progress-wrapper'><div class='progress-info'>";
	if ($content != ""){
		$out .= "<div class='progress-label'><span>" . $content . "</span></div>";
	}
	$progress = isset( $attr['progress'] ) ? $attr['progress'] : 100;
	$out .= "<div class='progress-percentage'><span>" . $progress . "%</span></div>";
	$out .= "</div><div class='progress'><div class='progress-bar";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'indigo';
	switch ($color){
		case 'indigo':
			$out .= " bg-primary";
			break;
		case 'green':
			$out .= " bg-success";
			break;
		case 'red':
			$out .= " bg-danger";
			break;
		case 'orange':
			$out .= " bg-warning";
			break;
		case 'blue':
			$out .= " bg-info";
			break;
		default:
			$out .= " bg-primary";
			break;
	}
	$out .= "' style='width: " . $progress . "%;'></div></div></div>";
	return $out;
}
add_shortcode('checkbox','shortcode_checkbox');
function shortcode_checkbox($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$checked = isset( $attr['checked'] ) ? $attr['checked'] : 'false';
	$inline = isset($attr['inline']) ? $attr['inline'] : 'false';
	$out = "<div class='shortcode-todo custom-control custom-checkbox";
	if ($inline == 'true'){
		$out .= " inline";
	}
	$out .= "'>
				<input class='custom-control-input' type='checkbox'" . ($checked == 'true' ? ' checked' : '') . ">
				<label class='custom-control-label'>
					<span>" . $content . "</span>
				</label>
			</div>";
	return $out;
}
add_shortcode('alert','shortcode_alert');
function shortcode_alert($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<div class='alert";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'indigo';
	switch ($color){
		case 'indigo':
			$out .= " alert-primary";
			break;
		case 'green':
			$out .= " alert-success";
			break;
		case 'red':
			$out .= " alert-danger";
			break;
		case 'orange':
			$out .= " alert-warning";
			break;
		case 'blue':
			$out .= " alert-info";
			break;
		case 'black':
			$out .= " alert-default";
			break;
		default:
			$out .= " alert-primary";
			break;
	}
	$out .= "'>";
	if (isset($attr['icon'])){
		$out .= "<span class='alert-inner--icon'><i class='fa fa-" . $attr['icon'] . "'></i></span>";
	}
	$out .= "<span class='alert-inner--text'>";
	if (isset($attr['title'])){
		$out .= "<strong>" . $attr['title'] . "</strong> ";
	}
	$out .= $content . "</span></div>";
	return $out;
}
add_shortcode('admonition','shortcode_admonition');
function shortcode_admonition($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<div class='admonition shadow-sm";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'indigo';
	switch ($color){
		case 'indigo':
			$out .= " admonition-primary";
			break;
		case 'green':
			$out .= " admonition-success";
			break;
		case 'red':
			$out .= " admonition-danger";
			break;
		case 'orange':
			$out .= " admonition-warning";
			break;
		case 'blue':
			$out .= " admonition-info";
			break;
		case 'black':
			$out .= " admonition-default";
			break;
		case 'grey':
			$out .= " admonition-grey";
			break;
		default:
			$out .= " admonition-primary";
			break;
	}
	$out .= "'>";
	if (isset($attr['title'])){
		$out .= "<div class='admonition-title'>";
		if (isset($attr['icon'])){
			$out .= "<i class='fa fa-" . $attr['icon'] . "'></i> ";
		}
		$out .= $attr['title'] . "</div>";
	}
	if ($content != ''){
		$out .= "<div class='admonition-body'>" . $content . "</div>";
	}
	$out .= "</div>";
	return $out;
}
add_shortcode('collapse','shortcode_collapse_block');
add_shortcode('fold','shortcode_collapse_block');
function shortcode_collapse_block($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$collapsed = isset( $attr['collapsed'] ) ? $attr['collapsed'] : 'true';
	$show_border_left = isset( $attr['showleftborder'] ) ? $attr['showleftborder'] : 'false';
	$out = "<div " ;
	$out .= " class='collapse-block shadow-sm";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'none';
	$title = isset( $attr['title'] ) ? $attr['title'] : '';
	switch ($color){
		case 'indigo':
			$out .= " collapse-block-primary";
			break;
		case 'green':
			$out .= " collapse-block-success";
			break;
		case 'red':
			$out .= " collapse-block-danger";
			break;
		case 'orange':
			$out .= " collapse-block-warning";
			break;
		case 'blue':
			$out .= " collapse-block-info";
			break;
		case 'black':
			$out .= " collapse-block-default";
			break;
		case 'grey':
			$out .= " collapse-block-grey";
			break;
		case 'none':
		default:
			$out .= " collapse-block-transparent";
			break;
	}
	if ($collapsed == 'true'){
		$out .= " collapsed";
	}
	if ($show_border_left != 'true'){
		$out .= " hide-border-left";
	}
	$out .= "'>";

	$out .= "<div class='collapse-block-title'>";
	if (isset($attr['icon'])){
		$out .= "<i class='fa fa-" . $attr['icon'] . "'></i> ";
	}
	$out .= "<span class='collapse-block-title-inner'>" . $title . "</span><i class='collapse-icon fa fa-angle-down'></i></div>";

	$out .= "<div class='collapse-block-body'";
	if ($collapsed != 'false'){
		$out .= " style='display:none;'";
	}
	$out .= ">" . $content . "</div>";
	$out .= "</div>";
	return $out;
}
add_shortcode('friendlinks','shortcode_friend_link');
function shortcode_friend_link($attr,$content=""){
	$sort = isset( $attr['sort'] ) ? $attr['sort'] : 'name';
	$order = isset( $attr['order'] ) ? $attr['order'] : 'ASC';
	$friendlinks = get_bookmarks( array(
		'orderby' => $sort ,
		'order'   => $order
	));
	$style = isset( $attr['style'] ) ? $attr['style'] : '1';
	switch ($style) {
		case '1':
			$class = "friend-links-style1";
			break;
		case '1-square':
			$class = "friend-links-style1 friend-links-style1-square";
			break;
		case '2':
			$class = "friend-links-style2";
			break;
		case '2-big':
			$class = "friend-links-style2 friend-links-style2-big";
			break;
		default:
			$class = "friend-links-style1";
			break;
	}
	$out = "<div class='friend-links " . $class . "'><div class='row'>";
	foreach ($friendlinks as $friendlink){
		$out .= "
			<div class='link mb-2 col-lg-6 col-md-6'>
				<div class='card shadow-sm friend-link-container" . ($friendlink -> link_image == "" ? " no-avatar" : "") . "'>";
		if ($friendlink -> link_image != ''){
			$out .= "
					<img src='" . $friendlink -> link_image . "' class='friend-link-avatar bg-gradient-secondary'>";
		}
		$out .= "	<div class='friend-link-content'>
						<div class='friend-link-title title text-primary'>
							<a target='_blank' href='" . esc_url($friendlink -> link_url) . "'>" . esc_html($friendlink -> link_name) . "</a>
						</div>
						<div class='friend-link-description'>" . esc_html($friendlink -> link_description) . "</div>";
		$out .= "		<div class='friend-link-links'>";
		foreach (explode("\n", $friendlink -> link_notes) as $line){
			$item = explode("|", trim($line));
			if(stripos($item[0], "fa-") !== 0){
				continue;
			}
			$out .= "<a href='" . esc_url($item[1]) . "' target='_blank'><i class='fa " . sanitize_html_class($item[0]) . "'></i></a>";
		}
		$out .= "<a href='" . esc_url($friendlink -> link_url) . "' target='_blank' style='float:right; margin-right: 10px;'><i class='fa fa-angle-right' style='font-weight: bold;'></i></a>";
		$out .= "
						</div>
					</div>
				</div>
			</div>";
	}
	$out .= "</div></div>";
	// 申请友链按钮（启用时）
		if (get_option('argon_fl_enable', 'false') == 'true'){
			$out .= argon_fl_apply_button_html($style);
		}
	return $out;
}
add_shortcode('sfriendlinks','shortcode_friend_link_simple');
function shortcode_friend_link_simple($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$content = trim(strip_tags($content));
	$entries = explode("\n" , $content);

	$shuffle = isset( $attr['shuffle'] ) ? $attr['shuffle'] : 'false';
	if ($shuffle == "true"){
		mt_srand();
		$group_start = 0;
		foreach ($entries as $index => $value){
			$now = explode("|" , $value);
			if ($now[0] == 'category'){
				echo ($index-1).",".$group_start." | ";
				for ($i = $index - 1; $i >= $group_start; $i--){
					echo $i."#";
					$tar = mt_rand($group_start , $i);
					$tmp = $entries[$tar];
					$entries[$tar] = $entries[$i];
					$entries[$i] = $tmp;
				}
				$group_start = $index + 1;
			}
		}
		for ($i = count($entries) - 1; $i >= $group_start; $i--){
			$tar = mt_rand($group_start , $i);
			$tmp = $entries[$tar];
			$entries[$tar] = $entries[$i];
			$entries[$i] = $tmp;
		}
	}

	$row_tag_open = False;
	$out = "<div class='friend-links-simple'>";
	foreach($entries as $index => $value){
		$now = explode("|" , $value);
		if ($now[0] == 'category'){
			if ($row_tag_open == True){
				$row_tag_open = False;
				$out .= "</div>";
			}
			$out .= "<div class='friend-category-title text-black'>" . esc_html($now[1]) . "</div>";
		}
		if ($now[0] == 'link'){
			if ($row_tag_open == False){
				$row_tag_open = True;
				$out .= "<div class='row'>";
			}
			$out .= "
			<div class='link mb-2 col-lg-4 col-md-6'>
				<div class='card shadow-sm'>
					<div class='d-flex'>
						<div class='friend-link-avatar'>
							<a target='_blank' href='" . esc_url(str_replace("'", '', $now[1])) . "'>";
			if (!ctype_space($now[4]) && $now[4] != '' && isset($now[4])){
				$out .= "<img src='" . esc_url(str_replace("'", '', $now[4])) . "' class='icon bg-gradient-secondary rounded-circle text-white' style='pointer-events: none;'>
						</img>";
			}else{
				$out .= "<div class='icon icon-shape bg-gradient-primary rounded-circle text-white'>" . esc_html(mb_substr($now[2], 0, 1)) . "
						</div>";
			}

			$out .= "		</a>
						</div>
						<div class='pl-3'>
							<div class='friend-link-title title text-primary'><a target='_blank' href='" . esc_url(str_replace("'", '', $now[1])) . "'>" . esc_html($now[2]) . "</a>
						</div>";
			if (!ctype_space($now[3]) && $now[3] != ''  && isset($now[3])){
				$out .= "<p class='friend-link-description'>" . esc_html($now[3]) . "</p>";
			}else{
				/*$out .= "<p class='friend-link-description'>&nbsp;</p>";*/
			}
			$out .= "		<a target='_blank' href='" . esc_url(str_replace("'", '', $now[1])) . "' class='text-primary opacity-8'>前往</a>
						</div>
					</div>
				</div>
			</div>";
		}
	}
	if ($row_tag_open == True){
		$row_tag_open = False;
		$out .= "</div>";
	}
	$out .= "</div>";
	return $out;
}
add_shortcode('timeline','shortcode_timeline');
function shortcode_timeline($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$content = trim(strip_tags($content));
	$entries = explode("\n" , $content);
	$out = "<div class='argon-timeline'>";
	foreach($entries as $index => $value){
		$now = explode("|" , $value);
		$now[0] = str_replace("/" , "</br>" , esc_html($now[0]));
		$out .= "<div class='argon-timeline-node'>
					<div class='argon-timeline-time'>" . $now[0] . "</div>
					<div class='argon-timeline-card card bg-gradient-secondary shadow-sm'>";
		if ($now[1] != ''){
			$out .= "	<div class='argon-timeline-title'>" . esc_html($now[1]) . "</div>";
		}
		$out .= "		<div class='argon-timeline-content'>";
		foreach($now as $index => $value){
			if ($index < 2){
				continue;
			}
			if ($index > 2){
				$out .= "</br>";
			}
			$out .= esc_html($value);
		}
		$out .= "		</div>
					</div>
				</div>";
	}
	$out .= "</div>";
	return $out;
}
add_shortcode('hidden','shortcode_hidden');
add_shortcode('spoiler','shortcode_hidden');
function shortcode_hidden($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<span class='argon-hidden-text";
	$tip = isset( $attr['tip'] ) ? $attr['tip'] : '';
	$type = isset( $attr['type'] ) ? $attr['type'] : 'blur';
	if ($type == "background"){
		$out .= " argon-hidden-text-background";
	}else{
		$out .= " argon-hidden-text-blur";
	}
	$out .= "'";
	if ($tip != ''){
		$out .= " title='" . $tip ."'";
	}
	$out .= ">" . $content . "</span>";
	return $out;
}
add_shortcode('github','shortcode_github');
function shortcode_github($attr,$content=""){
	$github_info_card_id = mt_rand(1000000000 , 9999999999);
	$author = isset( $attr['author'] ) ? $attr['author'] : '';
	$project = isset( $attr['project'] ) ? $attr['project'] : '';
	$getdata = isset( $attr['getdata'] ) ? $attr['getdata'] : 'frontend';
	$size = isset( $attr['size'] ) ? $attr['size'] : 'full';

	$description = "";
	$stars = "";
	$forks = "";

	if ($getdata == "backend"){
		set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext) {
			if (error_reporting() === 0) {
				return false;
			}
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});
		try{
			$contexts = stream_context_create(
				array(
					'http' => array(
						'method'=>"GET",
						'header'=>"User-Agent: ArgonTheme\r\n"
					)
				)
			);
			$json = file_get_contents("https://api.github.com/repos/" . $author . "/" . $project, false, $contexts);
			if (empty($json)){
				throw new Exception("");
			}
			$json = json_decode($json);
			$description = esc_html($json -> description);
			if (!empty($json -> homepage)){
				$description .= " <a href='" . esc_url($json -> homepage) . "' target='_blank' rel='noopener nofollow' no-pjax>" . esc_html($json -> homepage) . "</a>";
			}
			$stars = $json -> stargazers_count;
			$forks = $json -> forks_count;
		}catch (Exception $e){
			$getdata = "frontend";
		}
		restore_error_handler();
	}

	$out = "<div class='github-info-card github-info-card-" . $size . " card shadow-sm' data-author='" . $author . "' data-project='" . $project . "' githubinfo-card-id='" . $github_info_card_id . "' data-getdata='" . $getdata . "' data-description='" . $description . "' data-stars='" . $stars . "' data-forks='" . $forks . "'>";
	$out .= "<div class='github-info-card-header'><a href='https://github.com/' ref='nofollow' target='_blank' title='Github' no-pjax><span><i class='fa fa-github'></i>";
	if ($size != "mini"){
		$out .= " GitHub";
	}
	$out .= "</span></a></div>";
	$out .= "<div class='github-info-card-body'>
			<div class='github-info-card-name-a'>
				<a href='https://github.com/" . $author . "/" . $project . "' target='_blank' no-pjax>
					<span class='github-info-card-name'>" . $author . "/" . $project . "</span>
				</a>
				</div>
			<div class='github-info-card-description'></div>
		</div>";
	$out .= "<div class='github-info-card-bottom'>
				<span class='github-info-card-meta github-info-card-meta-stars'>
					<i class='fa fa-star'></i> <span class='github-info-card-stars'></span>
				</span>
				<span class='github-info-card-meta github-info-card-meta-forks'>
					<i class='fa fa-code-fork'></i> <span class='github-info-card-forks'></span>
				</span>
			</div>";
	$out .= "</div>";
	return $out;
}
add_shortcode('video','shortcode_video');
function shortcode_video($attr,$content=""){
	$url = isset( $attr['mp4'] ) ? $attr['mp4'] : '';
	$url = isset( $attr['url'] ) ? $attr['url'] : $url;
	$width = isset( $attr['width'] ) ? $attr['width'] : '';
	$height = isset( $attr['height'] ) ? $attr['height'] : '';
	$autoplay = isset( $attr['autoplay'] ) ? $attr['autoplay'] : 'false';
	$out = "<video";
	if ($width != ''){
		$out .= " width='" . intval($width) . "'";
	}
	if ($height != ''){
		$out .= " height='" . intval($height) . "'";
	}
	if ($autoplay == 'true'){
		$out .= " autoplay";
	}
	$out .= " controls>";
	$out .= "<source src='" . esc_url(str_replace("'", '', $url)) . "'>";
	$out .= "</video>";
	return $out;
}
add_shortcode('hide_reading_time','shortcode_hide_reading_time');
function shortcode_hide_reading_time($attr,$content=""){
	return "";
}
add_shortcode('post_time','shortcode_post_time');
function shortcode_post_time($attr,$content=""){
	$format = isset( $attr['format'] ) ? $attr['format'] : 'Y-n-d G:i:s';
	return get_the_time($format);
}
add_shortcode('post_modified_time','shortcode_post_modified_time');
function shortcode_post_modified_time($attr,$content=""){
	$format = isset( $attr['format'] ) ? $attr['format'] : 'Y-n-d G:i:s';
	return get_the_modified_time($format);
}
add_shortcode('noshortcode','shortcode_noshortcode');
function shortcode_noshortcode($attr,$content=""){
	return $content;
}
//Reference Footnote
add_shortcode('ref','shortcode_ref');
$post_references = array();
$post_reference_keys_first_index = array();
$post_reference_contents_first_index = array();
function argon_get_ref_html($content, $index, $subIndex){
	$index++;
	return "<sup class='reference' id='ref_" . $index . "_" . $subIndex . "' data-content='" . esc_attr($content) . "' tabindex='0'><a class='reference-link' href='#ref_" . $index . "'>[" . $index . "]</a></sup>";
}
function shortcode_ref($attr,$content=""){
	global $post_references;
	global $post_reference_keys_first_index;
	global $post_reference_contents_first_index;
	$content = preg_replace(
		'/<p>(.*?)<\/p>/is',
		'</br>$1',
		$content
	);
	$content = wp_kses($content, array(
		'a' => array(
			'href' => array(),
			'title' => array(),
			'target' => array()
		),
		'br' => array(),
		'em' => array(),
		'strong' => array(),
		'b' => array(),
		'sup' => array(),
		'sub' => array(),
		'small' => array()
	));
	if (isset($attr['id'])){
		if (isset($post_reference_keys_first_index[$attr['id']])){
			$post_references[$post_reference_keys_first_index[$attr['id']]]['count']++;
		}else{
			array_push($post_references, array('content' => $content, 'count' => 1));
			$post_reference_keys_first_index[$attr['id']] = count($post_references) - 1;
		}
		$index = $post_reference_keys_first_index[$attr['id']];
		return argon_get_ref_html($post_references[$index]['content'], $index, $post_references[$index]['count']);
	}else{
		if (isset($post_reference_contents_first_index[$content])){
			$post_references[$post_reference_contents_first_index[$content]]['count']++;
			$index = $post_reference_contents_first_index[$content];
			return argon_get_ref_html($post_references[$index]['content'], $index, $post_references[$index]['count']);
		}else{
			array_push($post_references, array('content' => $content, 'count' => 1));
			$post_reference_contents_first_index[$content] = count($post_references) - 1;
			$index = count($post_references) - 1;
			return argon_get_ref_html($post_references[$index]['content'], $index, $post_references[$index]['count']);
		}
	}
}
function get_reference_list(){
	global $post_references;
	if (count($post_references) == 0){
		return "";
	}
	$res = "<div class='reference-list-container'>";
	$res .= "<h3>" . (get_option('argon_reference_list_title') == "" ? __('参考', 'argon') : get_option('argon_reference_list_title')) . "</h3>";
	$res .= "<ol class='reference-list'>";
		foreach ($post_references as $index => $ref) {
			$res .= "<li id='ref_" . ($index + 1)  . "'><div>";
			if ($ref['count'] == 1){
				$res .= "<a class='reference-list-backlink' href='#ref_" . ($index + 1) . "_1' aria-label='back'>^</a>";
			}else{
				$res .= "<span class='reference-list-backlink'>^</span>";
				for ($i = 1, $j = 'a'; $i <= $ref['count']; $i++, $j++){
					$res .= "<sup><a class='reference-list-backlink' href='#ref_" . ($index + 1) . "_" . $i . "' aria-label='back'>" . $j . "</a></sup>";
				}
			}
			$res .= "<span>" . $ref['content'] . "</span>";
			$res .= "<div class='space' tabindex='-1'></div>";
			$res .= "</div></li>";
		}
	$res .= "</ol>";
	$res .= "</div>";
	return $res;
}
//TinyMce 按钮
function argon_tinymce_extra_buttons(){
	if(!current_user_can('edit_posts') && !current_user_can('edit_pages')){
		return;
	}
	if(get_user_option('rich_editing') == 'true'){
		add_filter('mce_external_plugins', 'argon_tinymce_add_plugin');
		add_filter('mce_buttons', 'argon_tinymce_register_button');
		add_editor_style($GLOBALS['assets_path'] . "/assets/tinymce_assets/tinymce_editor_codeblock.css");
	}
}
add_action('init', 'argon_tinymce_extra_buttons');
function argon_tinymce_register_button($buttons){
	array_push($buttons, "|", "codeblock");
	array_push($buttons, "|", "label");
	array_push($buttons, "", "checkbox");
	array_push($buttons, "", "progressbar");
	array_push($buttons, "", "alert");
	array_push($buttons, "", "admonition");
	array_push($buttons, "", "collapse");
	array_push($buttons, "", "timeline");
	array_push($buttons, "", "github");
	array_push($buttons, "", "video");
	array_push($buttons, "", "hiddentext");
	return $buttons;
}
function argon_tinymce_add_plugin($plugins){
	$plugins['codeblock'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['label'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['checkbox'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['progressbar'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['alert'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['admonition'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['collapse'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['timeline'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['github'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['video'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['hiddentext'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	return $plugins;
}
//主题选项页面
function themeoptions_admin_menu(){
	/*后台管理面板侧栏添加选项*/
	add_menu_page(__("Argon 主题设置", 'argon'), __("Argon 主题选项", 'argon'), 'edit_theme_options', basename(__FILE__), 'themeoptions_page');
	/*友链自助申请整理页*/
	add_submenu_page(basename(__FILE__), __("友链申请", 'argon'), __("友链申请", 'argon'), 'edit_theme_options', 'argon-friendlinks', 'argon_fl_admin_page');
}
include_once(get_template_directory() . '/settings.php');
	
/*主题菜单*/
add_action('init', 'init_nav_menus');
function init_nav_menus(){
	register_nav_menus( array(
		'toolbar_menu' => __('顶部导航', 'argon'),
		'leftbar_menu' => __('左侧栏菜单', 'argon'),
		'leftbar_author_links' => __('左侧栏作者个人链接', 'argon'),
		'leftbar_friend_links' => __('左侧栏友情链接', 'argon')
	));
}

//隐藏 admin 管理条
//show_admin_bar(false);

/*说说*/
add_action('init', 'init_shuoshuo');
function init_shuoshuo(){
	$labels = array(
		'name' => __('说说', 'argon'),
		'singular_name' => __('说说', 'argon'),
		'add_new' => __('发表说说', 'argon'),
		'add_new_item' => __('发表说说', 'argon'),
		'edit_item' => __('编辑说说', 'argon'),
		'new_item' => __('新说说', 'argon'),
		'view_item' => __('查看说说', 'argon'),
		'search_items' => __('搜索说说', 'argon'),
		'not_found' => __('暂无说说', 'argon'),
		'not_found_in_trash' => __('没有已遗弃的说说', 'argon'),
		'parent_item_colon' => '',
		'menu_name' => __('说说', 'argon')
	);
	$args = array(
		'labels' => $labels,
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'exclude_from_search' => true,
		'query_var' => true,
		'rewrite' => array(
			'slug' => 'shuoshuo',
			'with_front' => false
		),
		'capability_type' => 'post',
		'has_archive' => false,
		'hierarchical' => false,
		'menu_position' => null,
		'menu_icon' => 'dashicons-format-quote',
		'supports' => array('editor', 'author', 'title', 'custom-fields', 'comments')
	);
	register_post_type('shuoshuo', $args);
}

function argon_get_search_post_type_array(){
	$search_filters_type = get_option("argon_search_filters_type", "*post,*page,shuoshuo");
	$search_filters_type = explode(',', $search_filters_type);
	if (!isset($_GET['post_type'])) {
		$default = array_filter($search_filters_type, function ($str) {	return $str[0] == '*'; });
		$default = array_map(function ($str) { return substr($str, 1) ;}, $default);
		return $default;
	}
	$search_filters_type = array_map(function ($str) { return $str[0] == '*' ? substr($str, 1) : $str; }, $search_filters_type);
	$post_type = explode(',', $_GET['post_type']);
	$arr = array();
	foreach ($search_filters_type as $type) {
		if (in_array($type, $post_type)) {
			array_push($arr, $type);
		}
	}
	if (count($arr) == 0) {
		array_push($arr, 'none');
	}
	return $arr;
}
function search_filter($query) {
	if (!$query -> is_search || is_admin()) {
		return $query;
	}
	if (get_option('argon_enable_search_filters', 'true') == 'false'){
		return $query;
	}
	$query -> set('post_type', argon_get_search_post_type_array());
	return $query;
}
add_filter('pre_get_posts', 'search_filter');

/*恢复链接管理器*/
add_filter('pre_option_link_manager_enabled', '__return_true');

/*登录界面 CSS*/
function argon_login_page_style() {
	wp_enqueue_style("argon_login_css", $GLOBALS['assets_path'] . "/login.css", null, $GLOBALS['theme_version']);
}
if (get_option('argon_enable_login_css') == 'true'){
	add_action('login_head', 'argon_login_page_style');
}

/*强制加载 WP 区块样式：主题为经典 PHP 主题，WP 仅在有区块的页面才自动入队
  wp-block-library，而 Pjax 只替换 #primary 不刷新 <head>，导致换页后
  画廊(display:flex)、表格(border-collapse)等区块布局缺失。统一每页入队，
  使其在 <head> 常驻，Pjax 下始终可用。*/
function argon_enqueue_block_library_always(){
	if (!wp_style_is('wp-block-library', 'registered')){
		return;
	}
	wp_enqueue_style('wp-block-library');
	if (wp_style_is('wp-block-library-theme', 'registered')){
		wp_enqueue_style('wp-block-library-theme');
	}
}
add_action('wp_enqueue_scripts', 'argon_enqueue_block_library_always', 20);

/* ============================================================
   Enhanced 功能（本分支新增，区别于原版 Argon）
   仅保留「文章级点赞」（评论/说说点赞原版已有，文章级为新增）
   其余 Enhanced 功能（A 结构化数据 / D 实时搜索 / 文章点赞）开关
   统一在后台「Enhanced」分组
   ============================================================ */

/* ---------- 文章级点赞（区别于评论/说说点赞，原版缺失） ---------- */
function get_post_upvotes($ID){
	$count = get_post_meta($ID, 'argon_post_upvotes', true);
	if ($count === '' || $count === null || $count === false){
		$count = 0;
	}
	return intval($count);
}
function set_post_upvotes($ID){
	$count = get_post_upvotes($ID);
	update_post_meta($ID, 'argon_post_upvotes', $count + 1);
	return $count + 1;
}
function argon_get_upvoted_post_ids(){
	if (is_user_logged_in()){
		$list = get_user_meta(get_current_user_id(), 'argon_upvoted_posts', true);
		return is_array($list) ? $list : array();
	}
	$list = isset($_COOKIE['argon_post_upvoted']) ? $_COOKIE['argon_post_upvoted'] : '';
	return array_filter(array_map('intval', explode(',', $list)), function($v){ return $v > 0; });
}
function argon_mark_post_upvoted($ID){
	if (is_user_logged_in()){
		$list = argon_get_upvoted_post_ids();
		if (!in_array((int)$ID, $list, true)){
			$list[] = (int) $ID;
			update_user_meta(get_current_user_id(), 'argon_upvoted_posts', $list);
		}
	}else{
		$list = isset($_COOKIE['argon_post_upvoted']) ? $_COOKIE['argon_post_upvoted'] : '';
		$ids = array_filter(array_map('intval', explode(',', $list)));
		if (!in_array((int) $ID, $ids, true)) {
			$ids[] = (int) $ID;
		}
		// 去重 + 保留最近 200 个，防止 Cookie 累积超限导致请求头过大 (HTTP 400)
		$ids = array_unique($ids);
		$ids = array_slice($ids, -200);
		setcookie('argon_post_upvoted', implode(',', $ids) . ',', time() + 3153600000, '/');
	}
}
function is_post_upvoted($ID){
	return in_array((int) $ID, argon_get_upvoted_post_ids(), true);
}
function upvote_post(){
	argon_verify_ajax_nonce();
	header('Content-Type:application/json; charset=utf-8');
	if (get_option('argon_enable_post_like', 'true') != 'true'){
		exit(json_encode(array('status' => 'failed', 'msg' => __('文章点赞未启用', 'argon'), 'total_upvote' => 0)));
	}
	$ID = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	$post = get_post($ID);
	if ($post == null || !in_array($post -> post_type, array('post', 'page'))){
		exit(json_encode(array('status' => 'failed', 'msg' => __('文章不存在', 'argon'), 'total_upvote' => 0)));
	}
	if (is_post_upvoted($ID)){
		exit(json_encode(array('status' => 'failed', 'msg' => __('该文章已被赞过', 'argon'), 'total_upvote' => get_post_upvotes($ID))));
	}
	set_post_upvotes($ID);
	argon_mark_post_upvoted($ID);
	exit(json_encode(array(
		'ID' => $ID,
		'status' => 'success',
		'msg' => __('点赞成功', 'argon'),
		'total_upvote' => format_number_in_kilos(get_post_upvotes($ID))
	)));
}
add_action('wp_ajax_upvote_post', 'upvote_post');
add_action('wp_ajax_nopriv_upvote_post', 'upvote_post');

function argon_render_post_like($ID = 0, $compact = false){
	if (get_option('argon_enable_post_like', 'true') != 'true'){
		return;
	}
	if (!$ID){
		$ID = get_the_ID();
	}
	$upvoted = is_post_upvoted($ID) ? ' upvoted' : '';
	$compact_class = $compact ? ' post-upvote-meta' : '';
	$heart_outline = '<svg class="icon-heart-outline" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.27 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.08C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.77-3.4 6.86-8.55 11.53L12 21.35z"/></svg>';
	$heart_filled = '<svg class="icon-heart-filled" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.27 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.08C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.77-3.4 6.86-8.55 11.53L12 21.35z"/></svg>';
	echo '<button class="post-upvote' . $compact_class . $upvoted . '" type="button" data-id="' . esc_attr($ID) . '">'
		. '<span class="btn-inner--icon">' . $heart_outline . $heart_filled . '</span>'
		. '<span class="btn-inner--text">'
		. '<span class="post-upvote-label"><span class="label-like">' . __('点赞', 'argon') . '</span><span class="label-liked">' . __('已赞', 'argon') . '</span></span>'
		. ' <span class="post-upvote-num">' . format_number_in_kilos(get_post_upvotes($ID)) . '</span>'
		. '</span>'
		. '</button>';
}
/* ============================================================
   Enhanced 友链自主申请（本分支新增）
   - 在 [friendlinks] 友链界面提供「申请友链」按钮，点击弹出填写窗口（评论区风格）。
   - 提交信息以“待审评论”形式落到当前友链页，复用评论的邮箱收集与审核流，
     不写 wp_links / 不引入额外存储。
   - 审核通过后，该评论以友链卡片形式渲染进 [friendlinks]（普通评论列表中隐藏，避免重复）。
   ============================================================ */

// AJAX：访客提交友链申请 -> 创建待审评论
// 获取访客 IP（仅用于发信限流；反代头只取第一个合法 IP）
function argon_fl_client_ip(){
	$candidates = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
	foreach ($candidates as $key){
		if (empty($_SERVER[$key])){
			continue;
		}
		$parts = explode(',', $_SERVER[$key]);
		$ip = trim($parts[0]);
		if (filter_var($ip, FILTER_VALIDATE_IP)){
			return $ip;
		}
	}
	return '0.0.0.0';
}

// 通知日志：写入选项 argon_fl_notifications（环形保留最近 50 条），后台「友链申请」页展示
function argon_fl_log_notification($type, $msg, $link_id = 0){
	$logs = get_option('argon_fl_notifications', array());
	if (!is_array($logs)){
		$logs = array();
	}
	array_unshift($logs, array(
		'time'    => time(),
		'type'    => $type,
		'msg'     => $msg,
		'link_id' => (int) $link_id,
	));
	if (count($logs) > 50){
		$logs = array_slice($logs, 0, 50);
	}
	update_option('argon_fl_notifications', $logs);
}

// 友链邮件 HTML 模板：与主题「评论回复通知」邮件同风格（白卡片 + 大标题 + 分隔线 + 内容块 + 蓝色主按钮 + 次链接）
function argon_fl_mail_html($full_title, $content_html, $primary = array(), $secondary = array()){
	$out = '<div style="background: #fff;box-shadow: 0 15px 35px rgba(50,50,93,.1), 0 5px 15px rgba(0,0,0,.07);border-radius: 6px;margin: 15px auto 50px auto;padding: 35px 30px;max-width: min(calc(100% - 100px), 1200px);">'
		. '<div style="font-size:28px;text-align:center;margin-bottom:15px;line-height:1.4;">' . htmlspecialchars($full_title) . '</div>'
		. '<div style="background: rgba(0, 0, 0, .15);height: 1px;width: 300px;margin: auto;margin-bottom: 35px;"></div>'
		. '<div style="font-size: 16px;line-height: 1.8;border-left: 4px solid rgba(0, 0, 0, .15);margin: auto;padding: 20px 30px;background: rgba(0,0,0,.08);border-radius: 6px;box-shadow: 0 2px 4px rgba(0,0,0,.075)!important;max-width: 90%;margin-bottom: 35px;">' . $content_html . '</div>';
	if (!empty($primary['url'])){
		$out .= '<div style="text-align:center;margin-bottom:10px;"><a href="' . esc_url($primary['url']) . '" style="display: inline-block;line-height: 1;color: #fff;background-color: #5e72e4;border-color: #5e72e4;box-shadow: 0 4px 6px rgba(50,50,93,.11), 0 1px 3px rgba(0,0,0,.08);padding: 15px 25px;font-size: 18px;border-radius: 4px;text-decoration: none;">' . htmlspecialchars($primary['text']) . '</a></div>';
	}
	if (!empty($secondary['url'])){
		$out .= '<div style="text-align:center;margin-top:10px;"><a href="' . esc_url($secondary['url']) . '" style="color: #5e72e4;font-size: 15px;text-decoration: none;">' . htmlspecialchars($secondary['text']) . '</a></div>';
	}
	$out .= '</div>';
	return '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html charset=UTF-8" /></head><body><div style="background:#f8f9fb;padding:10px 0;">' . $out . '</div></body></html>';
}

// 统一通知入口：按类型发邮件（管理员一律用 admin_email，argon_fl_notify_email 非空时优先）+ 记录后台日志
function argon_fl_notify($type, $data = array()){
	$site    = get_bloginfo('name');
	$headers = array('Content-Type: text/html; charset=UTF-8');
	$admin   = get_option('argon_fl_notify_email', '');
	if ($admin === ''){
		$admin = get_option('admin_email');
	}
	$name     = isset($data['name']) ? $data['name'] : '';
	$url      = isset($data['url']) ? $data['url'] : '';
	$email    = isset($data['email']) ? $data['email'] : '';
	$linkpage = isset($data['linkpage']) ? $data['linkpage'] : '';
	$checked  = isset($data['checked_url']) ? $data['checked_url'] : '';
	$link_id  = isset($data['link_id']) ? (int) $data['link_id'] : 0;

	switch ($type){
		// 提交后发确认链接（申请者）：点击后才正式进入待审
		case 'confirm_mail':
			$subject = '[' . $site . '] ' . __('请确认您的友链申请', 'argon');
			$body = argon_fl_mail_html(
				__('请确认您的友链申请', 'argon'),
				'<strong>' . __('站点名称：', 'argon') . '</strong>' . htmlspecialchars($name) . '<br>'
					. '<strong>' . __('链接：', 'argon') . '</strong>' . htmlspecialchars($url) . '<br><br>'
					. __('请点击下方按钮确认提交（1 小时内有效）：', 'argon'),
				array('url' => isset($data['confirm_url']) ? $data['confirm_url'] : '', 'text' => __('确认提交', 'argon')),
				array('url' => home_url('/'), 'text' => __('若非本人操作，请忽略此邮件', 'argon'))
			);
			$ok = ($email !== '') ? wp_mail($email, $subject, $body, $headers) : false;
			argon_fl_log_notification($type, $ok ? sprintf(__('已向 %1$s 发送确认邮件（%2$s）', 'argon'), $email, $name) : sprintf(__('确认邮件发送失败：%s', 'argon'), $email));
			return $ok;

		// 确认成功、正式进入待审（申请者 + 管理员）
		case 'submitted':
			$ok = true;
			if (get_option('argon_fl_notify_submitted', 'true') == 'true' && $email !== ''){
				$subject = '[' . $site . '] ' . __('您的友链申请已提交', 'argon');
				$body = argon_fl_mail_html(
					__('您的友链申请已提交', 'argon'),
					'<strong>' . __('站点名称：', 'argon') . '</strong>' . htmlspecialchars($name) . '<br>'
						. '<strong>' . __('链接：', 'argon') . '</strong>' . htmlspecialchars($url) . '<br><br>'
						. __('审核通过后会再发一封邮件通知您，并附上可自助修改友链信息的管理链接。', 'argon'),
					array('url' => home_url('/'), 'text' => __('返回首页', 'argon'))
				);
				$ok = wp_mail($email, $subject, $body, $headers);
			}
			wp_mail(
				$admin,
				'[' . $site . '] ' . __('新友链申请待审核', 'argon'),
				argon_fl_mail_html(
					__('新友链申请待审核', 'argon'),
					'<strong>' . __('站点名称：', 'argon') . '</strong>' . htmlspecialchars($name) . '<br>'
						. '<strong>' . __('链接：', 'argon') . '</strong>' . htmlspecialchars($url) . '<br>'
						. '<strong>' . __('邮箱：', 'argon') . '</strong>' . htmlspecialchars($email) . '<br>'
						. ($linkpage !== '' ? '<strong>' . __('友链页：', 'argon') . '</strong>' . htmlspecialchars($linkpage) . '<br>' : ''),
					array('url' => admin_url('admin.php?page=argon-friendlinks'), 'text' => __('前往审核', 'argon'))
				),
				$headers
			);
			argon_fl_log_notification($type, sprintf(__('友链申请已提交待审：%1$s（%2$s）', 'argon'), $name, $url));
			return $ok;

		// 审核通过（申请者）：添加友链提醒 + 管理链接
		case 'approved':
			$subject = '[' . $site . '] ' . __('您的友链申请已通过审核', 'argon');
			$content = '<strong>' . __('站点名称：', 'argon') . '</strong>' . htmlspecialchars($name) . '<br>'
				. '<strong>' . __('链接：', 'argon') . '</strong>' . htmlspecialchars($url) . '<br>';
			if (!empty($data['desc'])){
				$content .= '<strong>' . __('描述：', 'argon') . '</strong>' . htmlspecialchars($data['desc']) . '<br>';
			}
			$content .= '<br>' . __('别忘了在您的站点添加本站友链，保持互链：', 'argon') . '<br>'
				. '<strong>' . __('本站名称：', 'argon') . '</strong>' . htmlspecialchars($site) . '<br>'
				. '<strong>' . __('本站链接：', 'argon') . '</strong>' . esc_url(home_url('/')) . '<br>';
			if (!empty($data['edit_url'])){
				$content .= '<br>' . __('如需修改友链信息（名称 / 链接 / 描述 / 站点图 / 友链页），请访问下方按钮。该管理链接仅您本人持有，换浏览器或换网络均可使用，请妥善保管。', 'argon');
			}
			$body = argon_fl_mail_html(
				__('您的友链申请已通过审核', 'argon'),
				$content,
				!empty($data['edit_url']) ? array('url' => $data['edit_url'], 'text' => __('管理我的友链', 'argon')) : array(),
				array('url' => home_url('/'), 'text' => __('返回首页', 'argon'))
			);
			$ok = ($email !== '') ? wp_mail($email, $subject, $body, $headers) : false;
			argon_fl_log_notification($type, sprintf(__('友链已通过审核：%s', 'argon'), $name), $link_id);
			return $ok;

		// 填了友链页但未检测到互链：提醒管理员与申请者（不阻断审核）
		case 'backlink_missing':
			if (get_option('argon_fl_backlink_notify_missing', 'true') != 'true'){
				return false;
			}
			wp_mail(
				$admin,
				'[' . $site . '] ' . __('友链互链未检测到', 'argon'),
				argon_fl_mail_html(
					__('友链互链未检测到', 'argon'),
					sprintf(__('友链「%s」填写了友链页，但未在该页面检测到指向本站的链接。', 'argon'), htmlspecialchars($name)) . '<br>'
						. '<strong>' . __('检查地址：', 'argon') . '</strong>' . htmlspecialchars($checked) . '<br>'
						. '<strong>' . __('对方站点：', 'argon') . '</strong>' . htmlspecialchars($url) . '<br><br>'
						. __('这不影响审核，请按需人工确认。', 'argon'),
					array('url' => admin_url('admin.php?page=argon-friendlinks'), 'text' => __('前往查看', 'argon'))
				),
				$headers
			);
			if ($email !== ''){
				wp_mail(
					$email,
					'[' . $site . '] ' . __('未检测到您站点上的本站友链', 'argon'),
					argon_fl_mail_html(
						__('未检测到您站点上的本站友链', 'argon'),
						__('我们在您提供的友链页未检测到指向本站的链接：', 'argon') . '<br>'
							. htmlspecialchars($checked) . '<br><br>'
							. __('若您已添加，可能是缓存或页面结构原因，可忽略此邮件；若尚未添加，欢迎补充以保持互链：', 'argon') . '<br>'
							. '<strong>' . __('本站名称：', 'argon') . '</strong>' . htmlspecialchars($site) . '<br>'
							. '<strong>' . __('本站链接：', 'argon') . '</strong>' . esc_url(home_url('/')),
						array('url' => home_url('/'), 'text' => __('返回首页', 'argon'))
					),
					$headers
				);
			}
			argon_fl_log_notification($type, sprintf(__('未检测到互链：%1$s（检查 %2$s）', 'argon'), $name, $checked), $link_id);
			return true;

		// 互链状态由「已互链」变为「未互链 / 不可达」：疑似撤链或站点异常，提醒管理员
		case 'backlink_changed':
			$reason = isset($data['reason']) ? $data['reason'] : 'none';
			if ($reason === 'error'){
				$subject = '[' . $site . '] ' . __('友链站点异常', 'argon');
				$title = __('友链站点异常', 'argon');
				$desc = sprintf(__('友链「%s」此前检测为已互链，本次复查无法访问其页面（可能已关闭站点或网络异常）。', 'argon'), htmlspecialchars($name));
			} else {
				$subject = '[' . $site . '] ' . __('友链疑似已撤链', 'argon');
				$title = __('友链疑似已撤链', 'argon');
				$desc = sprintf(__('友链「%s」此前检测为已互链，本次复查未再检测到指向本站的链接。', 'argon'), htmlspecialchars($name));
			}
			$body = argon_fl_mail_html(
				$title,
				$desc . '<br>'
					. '<strong>' . __('对方站点：', 'argon') . '</strong>' . htmlspecialchars($url) . '<br>'
					. '<strong>' . __('检查地址：', 'argon') . '</strong>' . htmlspecialchars($checked) . '<br><br>'
					. __('请核查后决定是否保留该友链。', 'argon'),
				array('url' => admin_url('admin.php?page=argon-friendlinks'), 'text' => __('前往查看', 'argon'))
			);
			wp_mail($admin, $subject, $body, $headers);
			argon_fl_log_notification($type, sprintf(__('疑似撤链/异常：%s', 'argon'), $name), $link_id);
			return true;

		// 首次检测到互链：仅记录后台日志，不额外发信（避免噪音）
		case 'backlink_mutual':
			argon_fl_log_notification($type, sprintf(__('已确认互链：%s', 'argon'), $name), $link_id);
			return true;

		// 申请被拒绝（申请者）
		case 'rejected':
			$subject = '[' . $site . '] ' . __('友链申请未通过', 'argon');
			$body = argon_fl_mail_html(
				__('友链申请未通过', 'argon'),
				'<strong>' . __('站点名称：', 'argon') . '</strong>' . htmlspecialchars($name) . '<br>'
					. '<strong>' . __('链接：', 'argon') . '</strong>' . htmlspecialchars($url) . '<br><br>'
					. __('如您认为有误，可联系本站管理员。', 'argon'),
				array(),
				array('url' => home_url('/'), 'text' => __('返回首页', 'argon'))
			);
			$ok = ($email !== '') ? wp_mail($email, $subject, $body, $headers) : false;
			argon_fl_log_notification($type, sprintf(__('已拒绝友链申请：%s', 'argon'), $name));
			return $ok;
	}
	return false;
}

// 发信限流检查：同邮箱 60 秒 1 封；同 IP 每分钟 / 每日上限（后台可配）。返回空串表示允许
// 返回空字符串表示通过；否则返回 array('status' => ..., 'msg' => ...)。
// status 语义：need_confirm=「该邮箱已在限流窗内发过确认邮件，视为已发送成功，前端走成功流程并自动关弹窗」；
//             error=「真实限流（过频/超日配额），前端红色提示且不关弹窗」。
function argon_fl_mail_rate_limit_check($email){
	if (get_transient('argon_fl_mail_e_' . md5(strtolower($email)))){
		return array(
			'status' => 'need_confirm',
			'msg'    => __('验证邮件已发送，请前往邮箱点击链接完成提交；若未收到，可稍后重试。', 'argon'),
		);
	}
	$ip      = argon_fl_client_ip();
	$per_min = max(1, (int) get_option('argon_fl_mail_limit_ip_min', 10));
	$per_day = max(1, (int) get_option('argon_fl_mail_limit_ip_day', 100));
	if ((int) get_transient('argon_fl_mail_ipm_' . md5($ip)) >= $per_min){
		return array(
			'status' => 'error',
			'msg'    => __('提交有点频繁，请稍等片刻再试～', 'argon'),
		);
	}
	if ((int) get_transient('argon_fl_mail_ipd_' . md5($ip . date('Ymd'))) >= $per_day){
		return array(
			'status' => 'error',
			'msg'    => __('今天提交的次数有点多啦，请明天再来～', 'argon'),
		);
	}
	return '';
}

// 发信限流计数：发送成功后调用
function argon_fl_mail_rate_limit_hit($email){
	$ip   = argon_fl_client_ip();
	$mkey = 'argon_fl_mail_ipm_' . md5($ip);
	$dkey = 'argon_fl_mail_ipd_' . md5($ip . date('Ymd'));
	set_transient('argon_fl_mail_e_' . md5(strtolower($email)), 1, MINUTE_IN_SECONDS);
	set_transient($mkey, ((int) get_transient($mkey)) + 1, MINUTE_IN_SECONDS);
	set_transient($dkey, ((int) get_transient($dkey)) + 1, DAY_IN_SECONDS);
}

// —— 友链申请：独立存储（option 数组），不再使用评论 ——
// 申请以自增 ID 存于选项 argon_fl_applications，status 区分 pending/approved/rejected，
// 避免污染评论系统（重复提醒、评论审核流程干扰、评论区错乱）。

// —— 请求内缓存：applications / link_tokens / link_status 三组 option 频繁读写，
// 用「引用静态变量」缓存，读操作避免重复 get_option，写操作直接改缓存再落库，无脏读风险 ——
function &argon_fl_applications_ref(){
	static $apps = null;
	if ($apps === null){
		$v = get_option('argon_fl_applications', array());
		$apps = is_array($v) ? $v : array();
	}
	return $apps;
}

function &argon_fl_link_tokens_ref(){
	static $tokens = null;
	if ($tokens === null){
		$v = get_option('argon_fl_link_tokens', array());
		$tokens = is_array($v) ? $v : array();
	}
	return $tokens;
}

function argon_fl_get_applications(){
	$apps =& argon_fl_applications_ref();
	return $apps;
}

function argon_fl_get_application($id){
	$apps =& argon_fl_applications_ref();
	return isset($apps[$id]) ? $apps[$id] : null;
}

function argon_fl_update_application($id, $patch){
	$apps =& argon_fl_applications_ref();
	if (!isset($apps[$id])){
		return false;
	}
	$apps[$id] = array_merge($apps[$id], $patch);
	update_option('argon_fl_applications', $apps);
	return true;
}

function argon_fl_delete_application($id){
	$apps =& argon_fl_applications_ref();
	if (isset($apps[$id])){
		unset($apps[$id]);
		update_option('argon_fl_applications', $apps);
		return true;
	}
	return false;
}

// 由申请数据创建独立「待审核」申请；邮箱确认链接与关闭确认两条路径共用。返回申请 ID
function argon_fl_create_pending($data){
	$apps =& argon_fl_applications_ref();
	$id = (int) get_option('argon_fl_apply_seq', 0) + 1;
	update_option('argon_fl_apply_seq', $id);
	$apps[$id] = array(
		'id'       => $id,
		'name'     => isset($data['name']) ? $data['name'] : '',
		'url'      => isset($data['url']) ? $data['url'] : '',
		'image'    => isset($data['image']) ? $data['image'] : '',
		'desc'     => isset($data['desc']) ? $data['desc'] : '',
		'email'    => isset($data['email']) ? $data['email'] : '',
		'linkpage' => isset($data['linkpage']) ? $data['linkpage'] : '',
		'post_id'  => isset($data['post_id']) ? (int) $data['post_id'] : 0,
		'status'   => 'pending',
		'date'     => time(),
	);
	// 防止 option 无限膨胀：最多保留 200 条，超额时裁剪最旧的已处理（approved/rejected）申请，pending 不裁剪
	if (count($apps) > 200){
		$excess = count($apps) - 200;
		$removed = 0;
		foreach ($apps as $aid => $app){
			if ($removed >= $excess){
				break;
			}
			if ($app['status'] !== 'pending'){
				unset($apps[$aid]);
				$removed++;
			}
		}
	}
	update_option('argon_fl_applications', $apps);
	return $id;
}

// 友链 AJAX 统一返回：先清空所有输出缓冲，丢弃 wp_mail 等可能产生的 PHP 警告/输出，
// 否则这些输出会被前置到 JSON 之前，导致前端 fetch().json() 解析失败 → 误报“提交错误”且不关闭弹窗
function argon_fl_send_json($data){
	while (ob_get_level() > 0){ ob_end_clean(); }
	if (!headers_sent()){
		header('Content-Type: application/json; charset=utf-8');
		header('X-Content-Type-Options: nosniff');
	}
	echo wp_json_encode($data);
	exit;
}
function argon_fl_apply_ajax(){
	ob_start();
	if (get_option('argon_fl_enable', 'false') != 'true'){
		argon_fl_send_json(array('status' => 'error', 'msg' => __('友链自助申请功能未开启', 'argon')));
	}
	argon_verify_ajax_nonce(); // 校验失败会直接 exit（主题约定：无返回值=通过）
	$name  = sanitize_text_field($_POST['argon_fl_name'] ?? '');
	$url   = esc_url_raw(trim($_POST['argon_fl_url'] ?? ''));
	$image = esc_url_raw(trim($_POST['argon_fl_image'] ?? ''));
	$desc  = sanitize_textarea_field($_POST['argon_fl_desc'] ?? '');
	$email = sanitize_email(trim($_POST['argon_fl_email'] ?? ''));
	$linkpage = esc_url_raw(trim($_POST['argon_fl_linkpage'] ?? ''));
	$post_id = intval($_POST['argon_fl_post_id'] ?? 0);

	$errors = array();
	if ($name === ''){
		$errors[] = __('请填写站点名称', 'argon');
	}
	if ($url === '' || !wp_http_validate_url($url)){
		$errors[] = __('请填写合法的网站 URL', 'argon');
	}
	if ($email === ''){
		$errors[] = __('请填写邮箱（用于确认申请与发送可管理链接）', 'argon');
	}
	if ($linkpage !== '' && !wp_http_validate_url($linkpage)){
		$errors[] = __('友链页 URL 不合法', 'argon');
	}
	if ($post_id <= 0 || !get_post($post_id)){
		$errors[] = __('提交目标页面无效', 'argon');
	}
	if (!empty($errors)){
		argon_fl_send_json(array('status' => 'error', 'msg' => implode('<br>', $errors)));
	}

	$data = array(
		'name'     => $name,
		'url'      => $url,
		'image'    => $image,
		'desc'     => $desc,
		'email'    => $email,
		'linkpage' => $linkpage,
		'post_id'  => $post_id,
	);

	// 未启用邮箱确认：退回「直接进入待审 + 人工审核」
	if (get_option('argon_fl_email_confirm_enable', 'true') != 'true'){
		$apply_id = argon_fl_create_pending($data);
		if (empty($apply_id)){
			argon_fl_send_json(array('status' => 'error', 'msg' => __('提交失败，请稍后重试', 'argon')));
		}
		argon_fl_notify('submitted', $data);
		argon_fl_send_json(array('status' => 'success', 'msg' => __('提交成功，等待管理员审核。审核通过后将在友链界面展示。', 'argon')));
	}

	// 发信限流：防脚本刷邮件
	$limit = argon_fl_mail_rate_limit_check($email);
	if ($limit !== ''){
		// $limit['status'] 已是 need_confirm（已发过，前端走成功关弹窗）或 error（真实限流）
		argon_fl_send_json(array('status' => $limit['status'], 'msg' => $limit['msg']));
	}

	// 暂存待确认数据（1 小时过期）。未点击确认链接则自动丢弃：不入库、不审核、不做回链检查
	$token = get_random_token();
	set_transient('argon_fl_pending_' . $token, $data, HOUR_IN_SECONDS);

	$confirm_url = get_template_directory_uri() . '/confirm-friendlink.php?t=' . rawurlencode($token);
	$ok = argon_fl_notify('confirm_mail', array_merge($data, array('confirm_url' => $confirm_url)));
	if (!$ok){
		// 发信失败：不要设置限流 transient（允许立即重试，避免误判为「已发送」），并明确告知失败
		argon_fl_send_json(array('status' => 'error', 'msg' => __('验证邮件发送失败，请稍后重试；若多次失败可联系管理员。', 'argon')));
	}
	// 仅发送成功才计入限流，确保 60 秒内重复提交能正确命中「已发过」分支
	argon_fl_mail_rate_limit_hit($email);

	argon_fl_send_json(array(
		'status' => 'need_confirm',
		'msg'    => sprintf(__('验证邮件已发送至 %s，请查收并点击邮件中的链接完成提交。', 'argon'), $email),
	));
}
add_action('wp_ajax_nopriv_argon_fl_apply', 'argon_fl_apply_ajax');
add_action('wp_ajax_argon_fl_apply', 'argon_fl_apply_ajax');

// 审核通过：把独立申请转成标准友链（wp_links），生成可管理 token，发送编辑邮件。
// 友链按主题原有方式渲染（get_bookmarks）、可在 link-manager.php 管理。
function argon_fl_approve_application($apply_id){
	$app = argon_fl_get_application($apply_id);
	if (!$app || $app['status'] === 'approved'){
		return false;
	}
	// wp_insert_link 定义在 wp-admin/includes/bookmark.php，仅后台自动加载；前台需手动引入
	if (!function_exists('wp_insert_link')){
		require_once ABSPATH . 'wp-admin/includes/bookmark.php';
	}
	$link_id = wp_insert_link(array(
		'link_name'        => $app['name'],
		'link_url'         => $app['url'],
		'link_description' => $app['desc'],
		'link_image'       => $app['image'],
		'link_visible'     => 'Y',
	));
	if (empty($link_id)){
		return false;
	}
	// wp_links 无 meta，用选项映射记录 link 维度的可管理 token / 申请者邮箱 / 友链页 URL
	// 短 token（16 位）：仅作一次性管理链接凭证，避免超长 URL；旧 32 位 token 仍兼容
	$token = wp_generate_password(16, false);
	$fl_tokens =& argon_fl_link_tokens_ref();
	$fl_tokens[$link_id] = array(
		'token'    => $token,
		'email'    => $app['email'],
		'linkpage' => $app['linkpage'],
		'post_id'  => isset($app['post_id']) ? (int) $app['post_id'] : 0,
	);
	update_option('argon_fl_link_tokens', $fl_tokens);
	// 审核通过通知（含添加友链提醒 + 管理链接），受开关 argon_fl_send_edit_link 控制
	if (get_option('argon_fl_send_edit_link', 'true') == 'true'){
		// 短链形式：/?fl_edit={link_id}&fl_token={token}，由 argon_fl_handle_edit_request 验证后跳转到友链页（带 fl_edit_verified 参数），前端弹窗复用申请弹窗进入编辑模式
		$edit_url = add_query_arg(array('fl_edit' => $link_id, 'fl_token' => $token), home_url('/'));
	} else {
		$edit_url = '';
	}
	argon_fl_notify('approved', array(
		'name'     => $app['name'],
		'url'      => $app['url'],
		'desc'     => $app['desc'],
		'email'    => $app['email'],
		'link_id'  => $link_id,
		'edit_url' => $edit_url,
	));
	// 审核通过即触发首次回链检查；填了友链页但未检测到时由 check 内触发 missing 通知
	if (get_option('argon_fl_backlink_enable', 'true') == 'true'){
		argon_fl_check_backlink_with_notify($link_id);
	}
	argon_fl_update_application($apply_id, array('status' => 'approved', 'link_id' => $link_id));
	return $link_id;
}

// 友链管理短链：/?fl_edit={id}&fl_token={token}（或旧格式 ?link=&token=）验证通过后，
// 跳转到友链页并带 fl_edit_verified 参数，前端弹窗自动进入编辑模式（复用申请弹窗，无独立编辑页）
add_action('init', 'argon_fl_handle_edit_request');
function argon_fl_handle_edit_request(){
	$link_id = intval(isset($_GET['fl_edit']) ? $_GET['fl_edit'] : ($_GET['link'] ?? 0));
	$token   = isset($_GET['fl_token']) ? $_GET['fl_token'] : ($_GET['token'] ?? '');
	if ($link_id <= 0){
		return;
	}
	$fl_tokens =& argon_fl_link_tokens_ref();
	if (!isset($fl_tokens[$link_id]) || $fl_tokens[$link_id]['token'] !== $token || !get_bookmark($link_id)){
		return; // 无效 token：走正常路由
	}
	// 定位友链页：设置项「友链页面 URL」→ 申请时记录的 post_id → 搜索含 [friendlinks] 的页面 → 首页
	$target = home_url('/');
	$cfg_url = get_option('argon_fl_page_url', '');
	if ($cfg_url && wp_http_validate_url($cfg_url)){
		$target = $cfg_url;
	} else {
		$pid = isset($fl_tokens[$link_id]['post_id']) ? (int) $fl_tokens[$link_id]['post_id'] : 0;
		if ($pid > 0 && get_post($pid)){
			$target = get_permalink($pid);
		} else {
			$pages = get_posts(array('post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 1, 's' => '[friendlinks]', 'fields' => 'ids'));
			if (!empty($pages)){
				$target = get_permalink($pages[0]);
			}
		}
	}
	// 验证通过：跳转到友链页（带 fl_edit_verified 参数），由 wp_footer 钩子直接验证并输出 editCfg
	wp_redirect(add_query_arg(array('fl_edit_verified' => $link_id, 'fl_token' => $token), $target));
	exit;
}

// wp_footer 输出 editCfg：脱离 the_content 过滤链（避免 convert_chars 转 entity）。
// 直接在带验证参数的页面按 token 从 wp_links 读取数据，不依赖 transient（transient 会过期/跨请求丢失）
add_action('wp_footer', 'argon_fl_output_edit_cfg');
function argon_fl_output_edit_cfg(){
	if (!isset($_GET['fl_edit_verified']) || !isset($_GET['fl_token'])){
		return;
	}
	$link_id = intval($_GET['fl_edit_verified']);
	$token   = $_GET['fl_token'];
	$fl_tokens =& argon_fl_link_tokens_ref();
	if (!isset($fl_tokens[$link_id]) || $fl_tokens[$link_id]['token'] !== $token){
		return; // token 无效
	}
	$link = get_bookmark($link_id);
	if (!$link){
		return;
	}
	echo '<script>window.argonFriendLinkEdit = ' . wp_json_encode(array(
		'edit_mode' => true,
		'link_id'   => $link_id,
		'name'      => $link->link_name,
		'url'       => $link->link_url,
		'image'     => $link->link_image,
		'desc'      => $link->link_description,
		'linkpage'  => isset($fl_tokens[$link_id]['linkpage']) ? $fl_tokens[$link_id]['linkpage'] : '',
		'token'     => $token,
	), JSON_UNESCAPED_UNICODE) . ';</script>';
}

// wp_footer 输出友链提交成功动效全局函数（脱离 the_content 过滤链，避免 </span></svg> 等闭合标签被 balanceTags/kses 破坏）
add_action('wp_footer', 'argon_fl_output_fl_fx');
function argon_fl_output_fl_fx(){
	echo '<script>window.flSuccessFx = function(text){'
		. 'var rocket = document.createElement("span");rocket.className = "argon-like-rocket argon-fl-fx";document.body.appendChild(rocket);setTimeout(function(){rocket.remove();},600);'
		. 'var burst = document.createElement("span");burst.className = "argon-like-burst argon-fl-fx";document.body.appendChild(burst);setTimeout(function(){burst.remove();},1300);'
		. 'var envSVG = \'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 6 10 7L22 6"/></svg>\';'
		. 'var toast = document.createElement("div");toast.className = "argon-like-toast argon-fl-toast";'
		. 'toast.innerHTML = \'<span class="toast-heart">\' + envSVG + \'</span><span class="toast-text">\' + text + \'</span>\';'
		. 'document.body.appendChild(toast);setTimeout(function(){toast.remove();},2400);'
		. 'var sparkColors = ["#5e72e4","#7e8df5","#4dd0e1","#b39ddb","#64b5f6","#a78bfa"];'
		. 'for(var i = 0; i < 22; i++){var angle = (Math.PI * 2 * i) / 22 + (Math.random() - 0.5) * 0.5;var dist = 150 + Math.random() * 190;'
		. 'var tx = Math.cos(angle) * dist;var ty = Math.sin(angle) * dist - 35;var color = sparkColors[Math.floor(Math.random() * sparkColors.length)];'
		. 'var size = 10 + Math.random() * 9;var delay = Math.random() * 0.12;var spark = document.createElement("span");'
		. 'spark.className = "argon-like-spark argon-fl-fx";spark.style.setProperty("--tx", tx.toFixed(1) + "px");spark.style.setProperty("--ty", ty.toFixed(1) + "px");'
		. 'spark.style.setProperty("--c", color);spark.style.setProperty("--s", size.toFixed(1) + "px");spark.style.setProperty("--d", delay.toFixed(2) + "s");'
		. 'document.body.appendChild(spark);(function(s){setTimeout(function(){s.remove();},2000);})(spark);}'
		. '};<\/script>';
}

// wp_footer 输出「提交成功后展示本站友链信息」弹窗：配置 + DOM + JS（脱离 the_content 过滤链，避免闭合标签被破坏）
add_action('wp_footer', 'argon_fl_output_site_info_modal');
function argon_fl_output_site_info_modal(){
	if (get_option('argon_fl_show_site_info', 'false') != 'true'){
		return;
	}
	$name   = get_option('argon_fl_site_name', '');
	$url    = get_option('argon_fl_site_url', '');
	$desc   = get_option('argon_fl_site_desc', '');
	$avatar = get_option('argon_fl_site_avatar', '');
	if ($name === ''){ $name = get_bloginfo('name'); }
	if ($url === ''){ $url = home_url('/'); }
	$cfg = array('name' => $name, 'url' => $url, 'desc' => $desc, 'avatar' => $avatar);
	echo '<div class="fl-site-info" id="fl_site_info" aria-hidden="true">'
		. '<div class="fl-site-info-backdrop"></div>'
		. '<div class="fl-site-info-card" role="dialog" aria-modal="true" aria-label="' . esc_attr__('本站友链信息', 'argon') . '">'
		. '<button class="fl-site-info-close" type="button" aria-label="' . esc_attr__('关闭', 'argon') . '">' . __('×', 'argon') . '</button>'
		. '<div class="fl-site-info-title">' . esc_html__('欢迎添加本站为友链', 'argon') . '</div>'
		. '<div class="fl-site-info-body">'
		. ($avatar !== '' ? '<div class="fl-site-info-avatar"><img src="' . esc_url($avatar) . '" alt="' . esc_attr($name) . '"></div>' : '')
		. '<div class="fl-site-info-meta">'
		. '<div class="fl-site-info-name">' . esc_html($name) . '</div>'
		. ($desc !== '' ? '<div class="fl-site-info-desc">' . esc_html($desc) . '</div>' : '')
		. '<a class="fl-site-info-link" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a>'
		. '</div></div>'
		. '<button class="fl-site-info-copy" type="button">' . esc_html__('复制友链信息', 'argon') . '</button>'
		. '<div class="fl-site-info-copied">' . esc_html__('已复制，欢迎添加～', 'argon') . '</div>'
		. '</div></div>'
		. '<script>window.argonFlSiteInfo = ' . wp_json_encode($cfg, JSON_UNESCAPED_UNICODE) . ';'
		. 'window.argonFlShowSiteInfo = function(){var m = document.getElementById("fl_site_info");if(m){m.classList.add("open");m.setAttribute("aria-hidden","false");}};'
		. 'window.argonFlHideSiteInfo = function(){var m = document.getElementById("fl_site_info");if(m){m.classList.remove("open");m.setAttribute("aria-hidden","true");}};'
		. 'window.argonFlCopySiteInfo = function(){var c = window.argonFlSiteInfo || {};var txt = "名称：" + (c.name||"") + "\\n链接：" + (c.url||"") + (c.desc ? "\\n描述：" + c.desc : "") + (c.avatar ? "\\n头像：" + c.avatar : "");'
		. 'var done = function(){var el = document.querySelector(".fl-site-info-copied");if(el){el.classList.add("show");setTimeout(function(){el.classList.remove("show");},1800);}};'
		. 'if(navigator.clipboard && navigator.clipboard.writeText){navigator.clipboard.writeText(txt).then(done,function(){var ta = document.createElement("textarea");ta.value = txt;document.body.appendChild(ta);ta.select();document.execCommand("copy");ta.remove();done();});}'
		. 'else{var ta = document.createElement("textarea");ta.value = txt;document.body.appendChild(ta);ta.select();document.execCommand("copy");ta.remove();done();}};'
		. 'document.addEventListener("click",function(e){if(e.target && (e.target.classList.contains("fl-site-info-close") || e.target.classList.contains("fl-site-info-backdrop"))){window.argonFlHideSiteInfo();}});'
		. 'document.addEventListener("click",function(e){if(e.target && e.target.classList.contains("fl-site-info-copy")){window.argonFlCopySiteInfo();}});'
		. 'document.addEventListener("keydown",function(e){if(e.key === "Escape"){window.argonFlHideSiteInfo();}});'
		. '<\/script>';
}

// —— 自动回链检查（Phase 2/3） ——

// —— link_status 请求内引用缓存 ——
function &argon_fl_link_status_ref(){
	static $all = null;
	if ($all === null){
		$v = get_option('argon_fl_link_status', array());
		$all = is_array($v) ? $v : array();
	}
	return $all;
}

// 取 link 的检查结果缓存
function argon_fl_get_link_status($link_id){
	$all =& argon_fl_link_status_ref();
	return isset($all[$link_id]) ? $all[$link_id] : array();
}

// 保存 link 的检查结果
function argon_fl_save_link_status($link_id, $status, $checked_url, $found_url = ''){
	$all =& argon_fl_link_status_ref();
	$all[$link_id] = array(
		'last_check'  => time(),
		'status'      => $status, // mutual | none | error
		'checked_url' => $checked_url,
		'found_url'   => $found_url,
	);
	update_option('argon_fl_link_status', $all);
}

// 归一化主机名（去 www. 前缀、小写），用于互链比对
function argon_fl_host_key($url){
	$host = parse_url($url, PHP_URL_HOST);
	if (!is_string($host)){
		return '';
	}
	$host = strtolower($host);
	if (strpos($host, 'www.') === 0){
		$host = substr($host, 4);
	}
	return $host;
}

// 在 HTML 中查找指向本站的链接；可选排除 nofollow
function argon_fl_find_self_link($html, $strict){
	if ($html === '' || strpos($html, ':') === false){
		return '';
	}
	$self = argon_fl_host_key(home_url('/'));
	if ($self === ''){
		return '';
	}
	$found = '';
	if (class_exists('WP_HTML_Tag_Processor')){
		$p = new WP_HTML_Tag_Processor($html);
		while ($p->next_tag('a')){
			$href = $p->get_attribute('href');
			if (!is_string($href) || $href === ''){
				continue;
			}
			if (argon_fl_host_key($href) !== $self){
				continue;
			}
			if ($strict){
				$rel = $p->get_attribute('rel');
				if (is_string($rel) && preg_match('/\bnofollow\b/i', $rel)){
					continue;
				}
			}
			$found = $href;
			break;
		}
	}
	if ($found === ''){
		// 回退：无 WP_HTML_Tag_Processor 时用轻量正则（仅取 href 属性值）
		if (preg_match_all('/<a[^>]+href\s*=\s*(["\'])(.*?)\1/i', $html, $m)){
			foreach ($m[2] as $href){
				if (argon_fl_host_key($href) !== $self){
					continue;
				}
				if ($strict && preg_match('/rel\s*=\s*(["\'])[^"\']*nofollow/i', $m[0][array_search($href, $m[2], true)] ?? '')){
					continue;
				}
				$found = $href;
				break;
			}
		}
	}
	return $found;
}

// 核心：抓取对方友链页/首页并检查是否包含指向本站的链接。返回本次 status。
// $force=true 表示手动重查，忽略 24h 结果缓存
function argon_fl_check_backlink($link_id, $force = false){
	if (!$force){
		$cached = argon_fl_get_link_status($link_id);
		if (!empty($cached['last_check']) && (time() - (int) $cached['last_check']) < DAY_IN_SECONDS){
			return isset($cached['status']) ? $cached['status'] : 'error';
		}
	}
	$link = get_bookmark($link_id);
	if (!$link || empty($link->link_url)){
		return 'error';
	}
	$fl_tokens =& argon_fl_link_tokens_ref();
	$linkpage  = (isset($fl_tokens[$link_id]['linkpage'])) ? $fl_tokens[$link_id]['linkpage'] : '';
	$target = ($linkpage !== '') ? $linkpage : $link->link_url;
	if (!wp_http_validate_url($target)){
		return 'error';
	}
	$resp = wp_remote_get($target, array(
		'timeout'     => 5,
		'redirection' => 3,
		'user-agent'  => 'ArgonFriendlinkChecker/1.0',
		'sslverify'   => true,
	));
	if (is_wp_error($resp)){
		argon_fl_save_link_status($link_id, 'error', $target);
		return 'error';
	}
	$strict = (get_option('argon_fl_backlink_nofollow_strict', 'false') == 'true');
	$body   = wp_remote_retrieve_body($resp);
	$found  = argon_fl_find_self_link($body, $strict);
	$status = ($found !== '') ? 'mutual' : 'none';
	argon_fl_save_link_status($link_id, $status, $target, $found);
	return $status;
}

// 回链检查包装：检查后按需触发通知。
// missing 通知仅当「填了友链页 URL」且「首次进入 none」时发一次（计划书 4.3），避免 cron 重复骚扰
function argon_fl_check_backlink_with_notify($link_id, $force = false){
	$prev   = argon_fl_get_link_status($link_id); // 必须先读旧状态，check 会覆盖写入
	$status = argon_fl_check_backlink($link_id, $force);
	if ($status === 'none'){
		$link = get_bookmark($link_id);
		$fl_tokens =& argon_fl_link_tokens_ref();
		$linkpage  = isset($fl_tokens[$link_id]['linkpage']) ? $fl_tokens[$link_id]['linkpage'] : '';
		$was_not_none = empty($prev['status']) || $prev['status'] !== 'none';
		if ($link && $linkpage !== '' && $was_not_none){
			$cur = argon_fl_get_link_status($link_id);
			argon_fl_notify('backlink_missing', array(
				'name'        => $link->link_name,
				'url'         => $link->link_url,
				'email'       => isset($fl_tokens[$link_id]['email']) ? $fl_tokens[$link_id]['email'] : '',
				'link_id'     => $link_id,
				'checked_url' => isset($cur['checked_url']) ? $cur['checked_url'] : '',
			));
		}
	}
	return $status;
}

// 手动「重新检查」：前台管理页与后台共用（前台需 link+token 校验）
function argon_fl_recheck_ajax(){
	ob_start();
	$link_id = intval($_POST['link_id'] ?? 0);
	if ($link_id <= 0 || !get_bookmark($link_id)){
		argon_fl_send_json(array('status' => 'error', 'msg' => __('友链不存在', 'argon')));
	}
	$fl_tokens =& argon_fl_link_tokens_ref();
	if (!current_user_can('edit_theme_options')){
		$token = $_POST['token'] ?? '';
		if ($token === '' || !isset($fl_tokens[$link_id]['token']) || $fl_tokens[$link_id]['token'] !== $token){
			argon_fl_send_json(array('status' => 'error', 'msg' => __('管理链接无效或已失效', 'argon')));
		}
	}
	if (get_option('argon_fl_backlink_enable', 'true') != 'true'){
		argon_fl_send_json(array('status' => 'error', 'msg' => __('回链检查功能未开启', 'argon')));
	}
	$status = argon_fl_check_backlink_with_notify($link_id, true); // 手动重查：忽略 24h 缓存
	$labels = array(
		'mutual' => __('已互链', 'argon'),
		'none'   => __('未检测到互链', 'argon'),
		'error'  => __('检查失败', 'argon'),
	);
	argon_fl_send_json(array(
		'status' => 'ok',
		'result' => isset($labels[$status]) ? $labels[$status] : $status,
	));
}
add_action('wp_ajax_nopriv_argon_fl_recheck', 'argon_fl_recheck_ajax');
add_action('wp_ajax_argon_fl_recheck', 'argon_fl_recheck_ajax');

// 编辑友链（弹窗编辑模式）：token 校验通过后更新 wp_links 与友链页 URL
add_action('wp_ajax_nopriv_argon_fl_edit', 'argon_fl_edit_ajax');
add_action('wp_ajax_argon_fl_edit', 'argon_fl_edit_ajax');
function argon_fl_edit_ajax(){
	ob_start();
	argon_verify_ajax_nonce(); // 校验失败会直接 exit
	$link_id = intval($_POST['link_id'] ?? 0);
	$token   = $_POST['token'] ?? '';
	$fl_tokens =& argon_fl_link_tokens_ref();
	if (!isset($fl_tokens[$link_id]) || $fl_tokens[$link_id]['token'] !== $token || !get_bookmark($link_id)){
		argon_fl_send_json(array('status' => 'error', 'msg' => __('管理链接无效或已失效', 'argon')));
	}
	$name     = sanitize_text_field($_POST['argon_fl_name'] ?? '');
	$url      = esc_url_raw(trim($_POST['argon_fl_url'] ?? ''));
	$image    = esc_url_raw(trim($_POST['argon_fl_image'] ?? ''));
	$desc     = sanitize_textarea_field($_POST['argon_fl_desc'] ?? '');
	$linkpage = esc_url_raw(trim($_POST['argon_fl_linkpage'] ?? ''));
	$errors = array();
	if ($name === ''){
		$errors[] = __('请填写站点名称', 'argon');
	}
	if ($url === '' || !wp_http_validate_url($url)){
		$errors[] = __('请填写合法的网站 URL', 'argon');
	}
	if ($linkpage !== '' && !wp_http_validate_url($linkpage)){
		$errors[] = __('友链页 URL 不合法', 'argon');
	}
	if (!empty($errors)){
		argon_fl_send_json(array('status' => 'error', 'msg' => implode('<br>', $errors)));
	}
	if (!function_exists('wp_update_link')){
		require_once ABSPATH . 'wp-admin/includes/bookmark.php';
	}
	wp_update_link(array(
		'link_id'          => $link_id,
		'link_name'        => $name,
		'link_url'         => $url,
		'link_description' => $desc,
		'link_image'       => $image,
	));
	$fl_tokens[$link_id]['linkpage'] = $linkpage;
	update_option('argon_fl_link_tokens', $fl_tokens);
	// 保存后立即重新回链检查（URL / 友链页可能已变更，避免状态过期）
	if (get_option('argon_fl_backlink_enable', 'true') == 'true'){
		argon_fl_check_backlink_with_notify($link_id, true);
	}
	argon_fl_send_json(array('status' => 'success', 'msg' => __('友链信息已更新，将在友链界面立即生效。', 'argon')));
}

// 每日 Cron：02:00–05:00 分批复查全部自助友链
add_action('init', 'argon_fl_maybe_schedule_backlink_cron');
function argon_fl_maybe_schedule_backlink_cron(){
	if (get_option('argon_fl_backlink_cron_enable', 'true') != 'true'){
		wp_clear_scheduled_hook('argon_fl_daily_backlink');
		return;
	}
	if (!wp_next_scheduled('argon_fl_daily_backlink')){
		$hour = rand(2, 4);            // 02:00–05:00 随机起点
		$min  = rand(0, 59);
		$ts   = strtotime(sprintf('today %02d:%02d', $hour, $min));
		if ($ts < time()){
			$ts += DAY_IN_SECONDS;
		}
		wp_schedule_event($ts, 'daily', 'argon_fl_daily_backlink');
	}
}
add_action('switch_theme', 'argon_fl_clear_backlink_cron');
function argon_fl_clear_backlink_cron(){
	wp_clear_scheduled_hook('argon_fl_daily_backlink');
}

add_action('argon_fl_daily_backlink', 'argon_fl_run_daily_backlink');
function argon_fl_run_daily_backlink(){
	if (get_option('argon_fl_backlink_enable', 'true') != 'true'){
		return;
	}
	// 双保险限窗口：防止服务器时区漂移导致不在 02:00–05:00 执行
	if ((int) date('G') < 2 || (int) date('G') >= 5){
		return;
	}
	$fl_tokens =& argon_fl_link_tokens_ref();
	$ids = is_array($fl_tokens) ? array_keys($fl_tokens) : array();
	if (empty($ids)){
		return;
	}
	$prev = get_option('argon_fl_link_status', array());
	$checked = 0;
	foreach ($ids as $link_id){
		if ($checked >= 20){
			break;
		}
		$before = isset($prev[$link_id]['status']) ? $prev[$link_id]['status'] : '';
		$after  = argon_fl_check_backlink($link_id);
		// 由「已互链」变为「未互链 / 不可达」：提示对方可能撤链或站点异常（仅状态变更时通知一次，避免重复骚扰）
		if ($before === 'mutual' && ($after === 'none' || $after === 'error')){
			$link = get_bookmark($link_id);
			$status_data = argon_fl_get_link_status($link_id);
			if ($link){
				argon_fl_notify('backlink_changed', array(
					'name'        => $link->link_name,
					'url'         => $link->link_url,
					'link_id'     => $link_id,
					'checked_url' => isset($status_data['checked_url']) ? $status_data['checked_url'] : '',
					'reason'      => $after,
				));
			}
		}
		// 首次检测到互链：记录日志
		if ($before !== 'mutual' && $after === 'mutual'){
			$link = get_bookmark($link_id);
			if ($link){
				argon_fl_notify('backlink_mutual', array(
					'name'    => $link->link_name,
					'link_id' => $link_id,
				));
			}
		}
		$checked++;
	}
	argon_fl_log_notification('cron_run', sprintf(__('每日回链复查完成，共检查 %d 个友链。', 'argon'), $checked));
}

// 后台「友链申请」管理页：列出待审申请（评论）与已通过友链（wp_links）
function argon_fl_admin_page(){
	if (!current_user_can('edit_theme_options')){
		wp_die(__('权限不足', 'argon'));
	}
	$apps = argon_fl_get_applications();
	$pending = array();
	if (!empty($apps)){
		foreach ($apps as $app){
			if ($app['status'] === 'pending'){
				$pending[] = $app;
			}
		}
	}
	$fl_tokens =& argon_fl_link_tokens_ref();
	// 清理孤儿 token / 回链状态（link 已在「链接」页被手动删除的残留）
	if (is_array($fl_tokens) && !empty($fl_tokens)){
		$status_all = get_option('argon_fl_link_status', array());
		$t_changed = false;
		$s_changed = false;
		foreach (array_keys($fl_tokens) as $lid){
			if (!get_bookmark($lid)){
				unset($fl_tokens[$lid]);
				unset($status_all[$lid]);
				$t_changed = true;
				$s_changed = true;
			}
		}
		if ($t_changed){ update_option('argon_fl_link_tokens', $fl_tokens); }
		if ($s_changed){ update_option('argon_fl_link_status', $status_all); }
	}
	$approved_links = array();
	if (!empty($fl_tokens)){
		$approved_links = get_bookmarks(array('include' => array_keys($fl_tokens)));
	}
	echo '<div class="wrap"><h1>' . __('友链申请', 'argon') . '</h1>';
	echo '<p class="fl-admin-desc">' . __('手动添加的友链请在后台「链接」中管理；此处仅列出访客自助申请。', 'argon') . '</p>';
	// 后台管理页 UI 样式（仅本页生效）
	echo '<style>'
		. '.fl-admin-desc{color:#64748b;font-size:13px;margin:-4px 0 14px;}'
		. '.fl-admin-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 1px 3px rgba(15,23,42,.06);overflow:hidden;margin-bottom:20px;}'
		. '.fl-admin-card table{width:100%;border-collapse:collapse;}'
		. '.fl-admin-card th{background:#f8fafc;color:#64748b;font-weight:600;font-size:12px;letter-spacing:.03em;padding:12px 14px;border-bottom:1px solid #e2e8f0;text-align:left;}'
		. '.fl-admin-card td{padding:12px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;font-size:13px;color:#334155;}'
		. '.fl-admin-card tbody tr:last-child td{border-bottom:none;}'
		. '.fl-admin-card tbody tr:hover td{background:#f8fafc;}'
		. '.fl-admin-card .fl-url{color:#5e72e4;text-decoration:none;word-break:break-all;}'
		. '.fl-admin-card .fl-url:hover{text-decoration:underline;}'
		. '.fl-admin-card .fl-sub{color:#94a3b8;font-size:11px;margin-top:2px;}'
		. '.fl-admin-empty{background:#fff;border:1px dashed #d1d9e6;border-radius:10px;padding:36px 20px;text-align:center;color:#94a3b8;font-size:13px;}'
		. '.fl-acts{display:flex;gap:6px;flex-wrap:wrap;}'
		. '.fl-btn{display:inline-flex !important;align-items:center !important;justify-content:center !important;gap:5px;padding:5px 12px !important;border-radius:6px !important;font-size:12px !important;font-weight:600;line-height:1.5 !important;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:all .15s;background:none;text-align:center;min-height:auto;}'
		. '.fl-btn i{font-size:12px;line-height:1;}'
		. '.fl-btn:hover{text-decoration:none;}'
		. '.fl-btn-success{background:#2dce89;color:#fff;}'
		. '.fl-btn-success:hover{background:#1fb377;color:#fff;}'
		. '.fl-btn-danger{background:#fff;color:#f5365c;border-color:#f5c6cb;}'
		. '.fl-btn-danger:hover{background:#f5365c;color:#fff;}'
		. '.fl-btn-outline{background:#fff;color:#475569;border-color:#d1d9e6;}'
		. '.fl-btn-outline:hover{color:#5e72e4;border-color:#5e72e4;}'
		. '.fl-badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.5;}'
		. '.fl-badge-success{background:#e6f9f0;color:#1fb377;}'
		. '.fl-badge-warning{background:#fef3e2;color:#d97706;}'
		. '.fl-badge-danger{background:#feecee;color:#f5365c;}'
		. '.fl-badge-muted{background:#f1f5f9;color:#64748b;}'
		. '</style>';
	if (isset($_GET['updated'])){
		echo '<div class="notice notice-success"><p>' . __('设置已保存。', 'argon') . '</p></div>';
	}
	if (isset($_GET['recheck'])){ // admin_post 重查后带回结果
		echo '<div class="notice notice-info"><p>' . esc_html($_GET['recheck']) . '</p></div>';
	}

	// 顶部导航（WP 原生 nav-tab）：待审核 / 已通过 / 设置 / 通知记录
	$tabs = array(
		'pending'  => __('待审核', 'argon'),
		'approved' => __('已通过', 'argon'),
		'settings' => __('设置', 'argon'),
		'notices'  => __('通知记录', 'argon'),
	);
	$current = (isset($_GET['tab']) && isset($tabs[$_GET['tab']])) ? $_GET['tab'] : 'pending';
	echo '<nav class="nav-tab-wrapper" style="margin-bottom:16px;">';
	foreach ($tabs as $key => $label){
		$count = 0;
		if ($key === 'pending'){ $count = count($pending); }
		if ($key === 'approved'){ $count = count($approved_links); }
		$badge = $count > 0 ? ' <span class="update-plugins count-' . $count . '"><span class="plugin-count">' . $count . '</span></span>' : '';
		$cls = ($key === $current) ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a href="' . esc_url(admin_url('admin.php?page=argon-friendlinks&tab=' . $key)) . '" class="' . esc_attr($cls) . '">'
			. esc_html($label) . $badge . '</a>';
	}
	echo '</nav>';

	// —— 各 tab 内容 ——
	if ($current === 'settings'){
		// 设置（决策 7：全部放在本页，不动主题主设置界面）
		// 每项：[分组, 名称, 说明, 类型, 默认值]
		$set = array(
			'argon_fl_enable'                  => array('basic', __('启用友链自助申请', 'argon'), __('关闭后申请按钮与提交接口均不可用', 'argon'), 'bool', 'false'),
			'argon_fl_email_confirm_enable'    => array('basic', __('启用邮箱确认链接', 'argon'), __('开启后提交需点击邮件链接确认才进入待审，可防脚本刷申请', 'argon'), 'bool', 'true'),
			'argon_fl_notify_submitted'        => array('basic', __('发送「已提交待审核」确认邮件', 'argon'), __('点击确认链接提交成功后，通知申请者申请已受理', 'argon'), 'bool', 'true'),
			'argon_fl_send_edit_link'          => array('basic', __('审核通过邮件附管理链接', 'argon'), __('申请者可凭链接自助修改友链信息', 'argon'), 'bool', 'true'),
			'argon_fl_show_site_info'          => array('basic', __('提交成功后展示本站友链信息', 'argon'), __('申请提交成功后弹窗展示本站友链信息（名称/链接/描述/头像），方便对方添加本站友链', 'argon'), 'bool', 'false'),
			'argon_fl_site_name'               => array('basic', __('本站友链名称', 'argon'), __('展示在提交成功弹窗中；留空则使用站点名称', 'argon'), 'text', ''),
			'argon_fl_site_url'                => array('basic', __('本站友链链接', 'argon'), __('展示在提交成功弹窗中；留空则使用站点首页', 'argon'), 'url', ''),
			'argon_fl_site_desc'               => array('basic', __('本站友链描述', 'argon'), __('展示在提交成功弹窗中', 'argon'), 'text', ''),
			'argon_fl_site_avatar'             => array('basic', __('本站友链头像', 'argon'), __('图片 URL，展示在提交成功弹窗中；留空则不展示头像', 'argon'), 'url', ''),
			'argon_fl_mail_limit_ip_min'       => array('mail',  __('同 IP 每分钟发信上限', 'argon'), __('同一 IP 每分钟最多发送确认邮件数，防止恶意刷邮件', 'argon'), 'num', 10),
			'argon_fl_mail_limit_ip_day'       => array('mail',  __('同 IP 每日发信上限', 'argon'), __('同一 IP 每日最多发送确认邮件数，防止恶意刷邮件', 'argon'), 'num', 100),
			'argon_fl_backlink_enable'         => array('link',  __('启用自动互链检查', 'argon'), __('审核通过时检查，并每日 Cron 复查；检查目标为对方「友链页 URL」，未填则抓首页', 'argon'), 'bool', 'true'),
			'argon_fl_backlink_nofollow_strict'=> array('link',  __('互链严格模式', 'argon'), __('勾选后带 nofollow 的回链不计为互链', 'argon'), 'bool', 'false'),
			'argon_fl_backlink_notify_missing' => array('link',  __('未检测到互链时发通知', 'argon'), __('填了友链页但未检测到互链，通知管理员与申请者（不阻断审核）', 'argon'), 'bool', 'true'),
			'argon_fl_backlink_cron_enable'    => array('link',  __('启用每日复查（02:00–05:00）', 'argon'), __('关闭后仅审核通过时与手动检查', 'argon'), 'bool', 'true'),
			'argon_fl_page_url'                => array('link',  __('友链页面 URL', 'argon'), __('填写后可确保「编辑友链」邮件链接准确跳转到友链页；留空则自动定位（申请页 → 搜索含 [friendlinks] 的页面 → 首页）', 'argon'), 'url', ''),
		);
		$groups = array(
			'basic' => array(__('基本功能', 'argon'), __('友链自助申请的启用与邮件流程', 'argon')),
			'mail'  => array(__('邮件与限流', 'argon'), __('确认邮件发送频次控制，防止恶意请求', 'argon')),
			'link'  => array(__('回链检查', 'argon'), __('自动互链检查的开关与检查策略', 'argon')),
		);
		echo '<style>'
			. '.fl-settings-card{border:1px solid #dcdcde;border-radius:8px;background:#fff;padding:14px 20px 6px;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.03);}'
			. '.fl-settings-card h3{margin:0 0 2px;font-size:14px;font-weight:600;}'
			. '.fl-settings-card .fl-settings-desc{color:#646970;margin:0 0 10px;font-size:12px;}'
			. '.fl-settings-card table{margin-top:0;width:100%;}'
			. '</style>';
		echo '<form method="post" action="' . admin_url('admin-post.php') . '">'
			. '<input type="hidden" name="action" value="argon_fl_save_settings">'
			. wp_nonce_field('argon_fl_admin', '_wpnonce', false);
		foreach ($groups as $gid => $ginfo){
			echo '<div class="fl-settings-card">'
				. '<h3>' . esc_html($ginfo[0]) . '</h3>'
				. '<p class="fl-settings-desc">' . esc_html($ginfo[1]) . '</p>'
				. '<table class="form-table"><tbody>';
			foreach ($set as $opt => $meta){
				if ($meta[0] !== $gid){
					continue;
				}
				$cur = get_option($opt, $meta[4]);
				echo '<tr><th scope="row" style="width:220px;"><label for="' . esc_attr($opt) . '">' . esc_html($meta[1]) . '</label></th><td>';
				if ($meta[3] === 'bool'){
					echo '<input type="checkbox" id="' . esc_attr($opt) . '" name="' . esc_attr($opt) . '" value="1" ' . checked($cur, 'true', false) . '>'
						. '<p class="description" style="margin-top:4px;">' . esc_html($meta[2]) . '</p>';
				} elseif ($meta[3] === 'url'){
					echo '<input type="url" class="regular-text" id="' . esc_attr($opt) . '" name="' . esc_attr($opt) . '" value="' . esc_attr($cur) . '" placeholder="https://">'
						. '<p class="description" style="margin-top:4px;">' . esc_html($meta[2]) . '</p>';
				} elseif ($meta[3] === 'text'){
					echo '<input type="text" class="regular-text" id="' . esc_attr($opt) . '" name="' . esc_attr($opt) . '" value="' . esc_attr($cur) . '">'
						. '<p class="description" style="margin-top:4px;">' . esc_html($meta[2]) . '</p>';
				} else {
					echo '<input type="number" min="1" class="small-text" id="' . esc_attr($opt) . '" name="' . esc_attr($opt) . '" value="' . esc_attr($cur ? $cur : 1) . '">'
						. '<p class="description" style="margin-top:4px;">' . esc_html($meta[2]) . '</p>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			// 回链检查组尾部：显示每日复查 Cron 调度状态
			if ($gid === 'link'){
				$next = wp_next_scheduled('argon_fl_daily_backlink');
				$cron_on = (get_option('argon_fl_backlink_cron_enable', 'true') == 'true');
				echo '<p class="description" style="border-top:1px solid #f0f0f1;padding-top:10px;">'
					. __('每日复查 Cron：', 'argon')
					. ($cron_on
						? ($next ? sprintf(__('已调度，下次执行 %s', 'argon'), wp_date('Y-m-d H:i', $next)) : __('待首次调度（下次访问站点后生效）', 'argon'))
						: __('已关闭', 'argon'))
					. '</p>';
			}
			echo '</div>';
		}
		echo '<p class="submit"><button type="submit" class="button button-primary">' . __('保存设置', 'argon') . '</button></p>'
			. '</form>';
	} elseif ($current === 'approved'){
		// —— 已通过（自助申请） ——
		if (empty($approved_links)){
			echo '<div class="fl-admin-empty"><i class="fa fa-link" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:10px;"></i>' . __('暂无已通过的自助友链。', 'argon') . '</div>';
		} else {
			$status_labels = array('mutual' => __('已互链', 'argon'), 'none' => __('未检测到互链', 'argon'), 'error' => __('检查失败', 'argon'));
			$status_badges = array('mutual' => 'fl-badge-success', 'none' => 'fl-badge-warning', 'error' => 'fl-badge-danger');
			echo '<div class="fl-admin-card"><table><thead><tr>'
				. '<th>' . __('站点名称', 'argon') . '</th>'
				. '<th>' . __('网站 URL', 'argon') . '</th>'
				. '<th>' . __('邮箱', 'argon') . '</th>'
				. '<th>' . __('互链状态', 'argon') . '</th>'
				. '<th style="width:220px;">' . __('操作', 'argon') . '</th>'
				. '</tr></thead><tbody>';
			foreach ($approved_links as $l){
				$info   = $fl_tokens[$l->link_id];
				$st     = argon_fl_get_link_status($l->link_id);
				$sval   = isset($st['status']) ? $st['status'] : '';
				$slabel = isset($status_labels[$sval]) ? $status_labels[$sval] : __('未检查', 'argon');
				$sbadge = isset($status_badges[$sval]) ? $status_badges[$sval] : 'fl-badge-muted';
				echo '<tr>'
					. '<td><strong>' . esc_html($l->link_name) . '</strong></td>'
					. '<td><a class="fl-url" href="' . esc_url($l->link_url) . '" target="_blank">' . esc_html($l->link_url) . '</a></td>'
					. '<td>' . esc_html(isset($info['email']) ? $info['email'] : '') . '</td>'
					. '<td><span class="fl-badge ' . $sbadge . '">' . $slabel . '</span>'
						. (isset($st['checked_url']) && $sval !== '' ? '<div class="fl-sub">' . esc_html($st['checked_url']) . '</div>' : '')
						. '</td>'
					. '<td><div class="fl-acts">'
					. '<a class="fl-btn fl-btn-outline" href="' . admin_url('link.php?action=edit&link_id=' . $l->link_id) . '"><i class="fa fa-pencil"></i>' . __('编辑', 'argon') . '</a> '
					. '<form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline;">'
						. '<input type="hidden" name="action" value="argon_fl_recheck_admin">'
						. '<input type="hidden" name="link_id" value="' . $l->link_id . '">'
						. wp_nonce_field('argon_fl_admin', '_wpnonce', false)
						. '<button type="submit" class="fl-btn fl-btn-outline"><i class="fa fa-refresh"></i>' . __('重新检查', 'argon') . '</button></form> '
					. '<form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline;" onsubmit="return confirm(\'' . esc_js(__('确定删除该友链？', 'argon')) . '\');">'
						. '<input type="hidden" name="action" value="argon_fl_delete">'
						. '<input type="hidden" name="link_id" value="' . $l->link_id . '">'
						. wp_nonce_field('argon_fl_admin', '_wpnonce', false)
						. '<button type="submit" class="fl-btn fl-btn-danger"><i class="fa fa-trash"></i>' . __('删除', 'argon') . '</button></form>'
					. '</div></td></tr>';
			}
			echo '</tbody></table></div>';
		}
	} elseif ($current === 'notices'){
		// —— 通知记录 ——
		$logs = get_option('argon_fl_notifications', array());
		if (!is_array($logs) || empty($logs)){
			echo '<div class="fl-admin-empty"><i class="fa fa-bell-slash" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:10px;"></i>' . __('暂无通知记录。', 'argon') . '</div>';
		} else {
			echo '<div class="fl-admin-card"><table><thead><tr>'
				. '<th style="width:160px;">' . __('时间', 'argon') . '</th>'
				. '<th>' . __('内容', 'argon') . '</th>'
				. '</tr></thead><tbody>';
			foreach (array_slice($logs, 0, 50) as $log){
				echo '<tr><td>' . esc_html(wp_date('Y-m-d H:i', (int) $log['time'])) . '</td>'
					. '<td>' . esc_html($log['msg']) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
	} else {
		// —— 待审核（默认 tab）：独立申请（option 存储），非评论 ——
		if (empty($pending)){
			echo '<div class="fl-admin-empty"><i class="fa fa-inbox" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:10px;"></i>' . __('暂无待审核的友链申请。', 'argon') . '</div>';
		} else {
			echo '<div class="fl-admin-card"><table><thead><tr>'
				. '<th>' . __('站点名称', 'argon') . '</th>'
				. '<th>' . __('网站 URL', 'argon') . '</th>'
				. '<th>' . __('邮箱', 'argon') . '</th>'
				. '<th>' . __('友链页', 'argon') . '</th>'
				. '<th style="width:130px;">' . __('提交时间', 'argon') . '</th>'
				. '<th style="width:160px;">' . __('操作', 'argon') . '</th>'
				. '</tr></thead><tbody>';
			foreach ($pending as $app){
				echo '<tr>'
					. '<td><strong>' . esc_html($app['name']) . '</strong>'
						. ($app['desc'] !== '' ? '<div class="fl-sub">' . esc_html(mb_substr($app['desc'], 0, 40)) . ($app['desc'] !== mb_substr($app['desc'], 0, 40) ? '…' : '') . '</div>' : '')
						. '</td>'
					. '<td><a class="fl-url" href="' . esc_url($app['url']) . '" target="_blank">' . esc_html($app['url']) . '</a></td>'
					. '<td>' . esc_html($app['email']) . '</td>'
					. '<td>' . ($app['linkpage'] !== '' ? '<a class="fl-url" href="' . esc_url($app['linkpage']) . '" target="_blank">' . esc_html($app['linkpage']) . '</a>' : '<span class="fl-sub">—</span>') . '</td>'
					. '<td>' . esc_html(wp_date('Y-m-d H:i', (int) $app['date'])) . '</td>'
					. '<td><div class="fl-acts">'
					. '<form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline;">'
						. '<input type="hidden" name="action" value="argon_fl_approve">'
						. '<input type="hidden" name="apply_id" value="' . (int) $app['id'] . '">'
						. wp_nonce_field('argon_fl_admin', '_wpnonce', false)
						. '<button type="submit" class="fl-btn fl-btn-success"><i class="fa fa-check"></i>' . __('通过', 'argon') . '</button></form> '
					. '<form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline;">'
						. '<input type="hidden" name="action" value="argon_fl_reject">'
						. '<input type="hidden" name="apply_id" value="' . (int) $app['id'] . '">'
						. wp_nonce_field('argon_fl_admin', '_wpnonce', false)
						. '<button type="submit" class="fl-btn fl-btn-outline"><i class="fa fa-times"></i>' . __('拒绝', 'argon') . '</button></form> '
					. '<form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline;" onsubmit="return confirm(\'' . esc_js(__('确定删除该友链申请？', 'argon')) . '\');">'
						. '<input type="hidden" name="action" value="argon_fl_delete">'
						. '<input type="hidden" name="apply_id" value="' . (int) $app['id'] . '">'
						. wp_nonce_field('argon_fl_admin', '_wpnonce', false)
						. '<button type="submit" class="fl-btn fl-btn-danger"><i class="fa fa-trash"></i>' . __('删除', 'argon') . '</button></form>'
					. '</div></td></tr>';
			}
			echo '</tbody></table></div>';
		}
	}

	echo '</div>';
}

// 后台设置保存：校验 nonce 后逐项 update_option，并按需调整每日 Cron
add_action('admin_post_argon_fl_save_settings', 'argon_fl_admin_save_settings');
function argon_fl_admin_save_settings(){
	if (!current_user_can('edit_theme_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'argon_fl_admin')){
		wp_die(__('权限不足或校验失败', 'argon'));
	}
	$bool_opts = array('argon_fl_enable', 'argon_fl_email_confirm_enable', 'argon_fl_backlink_enable', 'argon_fl_backlink_nofollow_strict', 'argon_fl_backlink_notify_missing', 'argon_fl_backlink_cron_enable', 'argon_fl_notify_submitted', 'argon_fl_send_edit_link', 'argon_fl_show_site_info');
	foreach ($bool_opts as $opt){
		update_option($opt, isset($_POST[$opt]) ? 'true' : 'false');
	}
	// 文本 / URL 选项（本站友链信息弹窗等）
	$text_opts = array('argon_fl_site_name', 'argon_fl_site_desc');
	foreach ($text_opts as $opt){
		update_option($opt, sanitize_text_field($_POST[$opt] ?? ''));
	}
	$url_opts = array('argon_fl_site_url', 'argon_fl_site_avatar');
	foreach ($url_opts as $opt){
		update_option($opt, esc_url_raw(trim($_POST[$opt] ?? '')));
	}
	$num_opts = array('argon_fl_mail_limit_ip_min', 'argon_fl_mail_limit_ip_day');
	foreach ($num_opts as $opt){
		update_option($opt, max(1, (int) ($_POST[$opt] ?? 1)));
	}
	// 友链页 URL（用于编辑邮件链接跳转定位）
	$page_url = esc_url_raw(trim($_POST['argon_fl_page_url'] ?? ''));
	update_option('argon_fl_page_url', $page_url);
	// 每日 Cron 随开关启停
	if (get_option('argon_fl_backlink_cron_enable', 'true') == 'true'){
		if (!wp_next_scheduled('argon_fl_daily_backlink')){
			argon_fl_maybe_schedule_backlink_cron();
		}
	} else {
		wp_clear_scheduled_hook('argon_fl_daily_backlink');
	}
	wp_redirect(add_query_arg(array('updated' => 1, 'tab' => 'settings'), admin_url('admin.php?page=argon-friendlinks')));
	exit;
}
add_action('admin_post_argon_fl_recheck_admin', 'argon_fl_admin_recheck');
function argon_fl_admin_recheck(){
	if (!current_user_can('edit_theme_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'argon_fl_admin')){
		wp_die(__('权限不足或校验失败', 'argon'));
	}
	$link_id = intval($_POST['link_id'] ?? 0);
	if ($link_id <= 0 || !get_bookmark($link_id)){
		wp_die(__('友链不存在', 'argon'));
	}
	$status = argon_fl_check_backlink_with_notify($link_id, true); // 手动重查：忽略 24h 缓存
	$labels = array('mutual' => __('互链检查完成：已互链。', 'argon'), 'none' => __('互链检查完成：未检测到互链。', 'argon'), 'error' => __('互链检查失败（网络或超时）。', 'argon'));
	wp_redirect(add_query_arg(array('recheck' => rawurlencode(isset($labels[$status]) ? $labels[$status] : __('互链检查完成。', 'argon')), 'tab' => 'approved'), admin_url('admin.php?page=argon-friendlinks')));
	exit;
}

// 管理页快捷操作
add_action('admin_post_argon_fl_approve', 'argon_fl_admin_approve');
function argon_fl_admin_approve(){
	if (!current_user_can('edit_theme_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'argon_fl_admin')){
		wp_die(__('权限不足或校验失败', 'argon'));
	}
	$apply_id = intval($_POST['apply_id'] ?? 0);
	if ($apply_id > 0){
		argon_fl_approve_application($apply_id); // 转 wp_links + 发邮件 + 回链检查 + 标记 approved
	}
	wp_redirect(admin_url('admin.php?page=argon-friendlinks'));
	exit;
}
add_action('admin_post_argon_fl_delete', 'argon_fl_admin_delete');
function argon_fl_admin_delete(){
	if (!current_user_can('edit_theme_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'argon_fl_admin')){
		wp_die(__('权限不足或校验失败', 'argon'));
	}
	$apply_id = intval($_POST['apply_id'] ?? 0);
	if ($apply_id > 0){
		argon_fl_delete_application($apply_id); // 删除独立申请记录
	}
	if (!empty($_POST['link_id'])){
		$lid = intval($_POST['link_id']);
		if (!function_exists('wp_delete_link')){
			require_once ABSPATH . 'wp-admin/includes/bookmark.php';
		}
		wp_delete_link($lid);
		$fl_tokens =& argon_fl_link_tokens_ref();
		unset($fl_tokens[$lid]);
		update_option('argon_fl_link_tokens', $fl_tokens);
		// 清理回链状态，避免脏数据残留
		$status = get_option('argon_fl_link_status', array());
		unset($status[$lid]);
		update_option('argon_fl_link_status', $status);
		// 联动删除该友链对应的申请记录
		$apps = argon_fl_get_applications();
		$changed = false;
		foreach ($apps as $aid => $app){
			if (isset($app['link_id']) && (int) $app['link_id'] === $lid){
				unset($apps[$aid]);
				$changed = true;
			}
		}
		if ($changed){
			update_option('argon_fl_applications', $apps);
		}
	}
	wp_redirect(admin_url('admin.php?page=argon-friendlinks'));
	exit;
}

// 拒绝申请：状态置 rejected 并通知申请者
function argon_fl_reject_application($apply_id){
	$app = argon_fl_get_application($apply_id);
	if (!$app || $app['status'] !== 'pending'){
		return false;
	}
	argon_fl_update_application($apply_id, array('status' => 'rejected'));
	argon_fl_notify('rejected', array(
		'name'  => $app['name'],
		'url'   => $app['url'],
		'email' => $app['email'],
	));
	return true;
}
add_action('admin_post_argon_fl_reject', 'argon_fl_admin_reject');
function argon_fl_admin_reject(){
	if (!current_user_can('edit_theme_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'argon_fl_admin')){
		wp_die(__('权限不足或校验失败', 'argon'));
	}
	$apply_id = intval($_POST['apply_id'] ?? 0);
	if ($apply_id > 0){
		argon_fl_reject_application($apply_id);
	}
	wp_redirect(admin_url('admin.php?page=argon-friendlinks'));
	exit;
}

// 「申请友链」按钮 + 弹窗（评论区风格），仅当功能启用时由 [friendlinks] 调用
function argon_fl_apply_button_html($style = '1'){
	$nonce   = wp_create_nonce('argon_ajax_action');
	$ajaxurl = admin_url('admin-ajax.php');
	$post_id = (int) get_the_ID();
	// 编辑模式：邮件短链验证通过后（?fl_edit_verified=），自动打开弹窗编辑已有友链（复用申请弹窗）
	$edit_cfg = null;
	if (isset($_GET['fl_edit_verified'])){
		$el_id    = intval($_GET['fl_edit_verified']);
		$el_token = isset($_GET['fl_token']) ? $_GET['fl_token'] : '';
		$el_tokens =& argon_fl_link_tokens_ref();
		$el_link = ($el_id > 0 && isset($el_tokens[$el_id]['token']) && $el_tokens[$el_id]['token'] === $el_token) ? get_bookmark($el_id) : null;
		if ($el_link){
			$edit_cfg = array(
				'edit_mode' => true,
				'link_id'   => $el_id,
				'name'      => $el_link->link_name,
				'url'       => $el_link->link_url,
				'image'     => $el_link->link_image,
				'desc'      => $el_link->link_description,
				'linkpage'  => isset($el_tokens[$el_id]['linkpage']) ? $el_tokens[$el_id]['linkpage'] : '',
				'token'     => $el_token,
			);
		}
	}
	// 头像圆角跟随当前友链墙风格（style1 为「右圆破框」，方/style2 为直角），确保预览与主题一致
	switch ($style){
		case '1':          $avatar_radius = '0 65px 65px 0'; break;
		case '1-square':   $avatar_radius = '0'; break;
		default:           $avatar_radius = '0'; break; // style2 / 2-big 等
	}
	// 连接动画中“本站”气泡用的真实站点图标（站点图标，缺失时回退到内置默认）
	$site_icon_url = get_site_icon_url();
	if (!$site_icon_url){
		$site_icon_url = 'data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20viewBox=%220%200%2024%2024%22%3E%3Ccircle%20cx=%2212%22%20cy=%2212%22%20r=%2210%22%20fill=%22%23ffffff%22/%3E%3Ccircle%20cx=%2212%22%20cy=%2212%22%20r=%225%22%20fill=%22%23ff6b81%22/%3E%3C/svg%3E';
	}
	ob_start();
	?>
	<div class="friendlink-apply">
		<button type="button" id="friendlink_apply_btn" class="friendlink-apply-btn">
			<span class="btn-inner--icon"><i class="fa fa-link"></i></span>
			<span class="btn-inner--text"><?php _e('申请友链', 'argon'); ?></span>
		</button>
		<div class="friendlink-apply-backdrop" id="friendlink_apply_backdrop"></div>
		<div class="friendlink-apply-modal" role="dialog" aria-modal="true" aria-label="<?php echo $edit_cfg ? esc_attr__('编辑友链', 'argon') : esc_attr__('申请友链', 'argon'); ?>">
			<!-- 左：连接舞台（渐变 + 流动连线 + 破框预览卡） -->
			<div class="friendlink-apply-stage">
				<p class="fl-stage-kicker"><?php echo $edit_cfg ? __('编辑友链', 'argon') : __('申请友链', 'argon'); ?></p>
				<h3 class="fl-stage-title"><?php echo $edit_cfg ? __('更新你的友链信息', 'argon') : __('和本站<br>互换友链', 'argon'); ?></h3>
				<div class="fl-stage-flow">
					<svg viewBox="0 0 240 120" aria-hidden="true">
						<defs>
							<linearGradient id="flLineGrad" x1="0" y1="0" x2="1" y2="0">
								<stop offset="0%" stop-color="rgba(255,255,255,.22)"/>
								<stop offset="100%" stop-color="rgba(255,255,255,.7)"/>
							</linearGradient>
							<clipPath id="flBubbleClip"><circle cx="44" cy="30" r="20"/></clipPath>
							<clipPath id="flBubbleClip2"><circle cx="196" cy="30" r="20"/></clipPath>
							<radialGradient id="flHalo"><stop offset="0%" stop-color="rgba(255,255,255,.9)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient>
						</defs>
						<!-- 连接动画（辅助，细节丰富）：纤细半透明，让上方站点图气泡成为焦点 -->
						<line x1="44" y1="82" x2="196" y2="82" stroke="url(#flLineGrad)" stroke-width="1.5" stroke-linecap="round" opacity=".7"/>
						<!-- 流动虚线：连接“正在建立” -->
						<line x1="44" y1="82" x2="196" y2="82" class="fl-link-flow" stroke="#fff" stroke-width="1.5" stroke-linecap="round" opacity=".8"/>
						<!-- 连接高亮脉冲：周期性提亮，强调“已连通” -->
						<line x1="44" y1="82" x2="196" y2="82" class="fl-link-glow" stroke="#fff" stroke-width="1.5" stroke-linecap="round" opacity=".5"/>
						<!-- 刻度标记（细节） -->
						<g class="fl-ticks" stroke="rgba(255,255,255,.32)" stroke-width="1">
							<line x1="68" y1="79" x2="68" y2="85"/>
							<line x1="92" y1="79" x2="92" y2="85"/>
							<line x1="120" y1="78" x2="120" y2="86"/>
							<line x1="148" y1="79" x2="148" y2="85"/>
							<line x1="172" y1="79" x2="172" y2="85"/>
						</g>
						<!-- 气泡到节点的引线：把焦点气泡与下方连线视觉串联 -->
						<line x1="44" y1="54" x2="44" y2="70" stroke="rgba(255,255,255,.3)" stroke-width="1" stroke-dasharray="1.5 3" class="fl-drop"/>
						<line x1="196" y1="54" x2="196" y2="70" stroke="rgba(255,255,255,.3)" stroke-width="1" stroke-dasharray="1.5 3" class="fl-drop fl-drop2"/>
						<!-- 方向指示：已移除，避免与粒子流重复表达双向 -->

						<!-- 节点柔光晕（丰富层次） -->
						<circle cx="44" cy="82" r="16" fill="url(#flHalo)" class="fl-node-halo"/>
						<circle cx="196" cy="82" r="16" fill="url(#flHalo)" class="fl-node-halo2"/>
						<!-- 两端节点声纳环 + 核心 -->
						<circle cx="44" cy="82" r="9" fill="none" stroke="rgba(255,255,255,.4)" stroke-width="1.5" class="fl-node-ring"/>
						<circle cx="196" cy="82" r="9" fill="none" stroke="rgba(255,255,255,.4)" stroke-width="1.5" class="fl-node-ring2"/>
						<circle cx="44" cy="82" r="5" fill="#fff" class="fl-node-core"/>
						<circle cx="196" cy="82" r="5" fill="rgba(255,255,255,.92)" class="fl-node-core2"/>
						<!-- 中心连接点（握手中点） -->
						<circle cx="120" cy="82" r="3" fill="#fff" class="fl-center-pulse"/>
						<!-- 双向数据包（主） -->
						<circle cx="0" cy="82" r="4" fill="#fff" class="fl-packet"/>
						<circle cx="0" cy="82" r="3.5" fill="rgba(255,255,255,.7)" class="fl-packet2"/>
						<!-- 双向数据流（细节粒子，正反各 2 颗，跟在主数据包之后形成尾迹） -->
						<circle cx="0" cy="82" r="1.8" fill="rgba(255,255,255,.85)" class="fl-particle pf1"/>
						<circle cx="0" cy="82" r="1.4" fill="rgba(255,255,255,.7)" class="fl-particle pf2"/>
						<circle cx="0" cy="82" r="1.6" fill="rgba(255,255,255,.55)" class="fl-particle pr1"/>
						<circle cx="0" cy="82" r="1.3" fill="rgba(255,255,255,.45)" class="fl-particle pr2"/>
						<text x="44" y="106" text-anchor="middle" fill="rgba(255,255,255,.7)" font-size="10" font-weight="600"><?php _e('本站', 'argon'); ?></text>
						<text x="196" y="106" text-anchor="middle" fill="rgba(255,255,255,.7)" font-size="10" font-weight="600"><?php _e('友站', 'argon'); ?></text>
						<!-- 真实站点图气泡（焦点）：漂浮在两端上方，分别显示“本站”与“友站”的真实站点图 -->
						<g class="fl-site-bubble" clip-path="url(#flBubbleClip)">
							<circle cx="44" cy="30" r="20" fill="rgba(255,255,255,.95)"/>
							<image id="fl_bubble_site" x="24" y="10" width="40" height="40" preserveAspectRatio="xMidYMid slice" href="<?php echo (strpos($site_icon_url, 'data:') === 0) ? $site_icon_url : esc_url($site_icon_url); ?>"/>
						</g>
						<g class="fl-site-bubble2" clip-path="url(#flBubbleClip2)">
							<circle cx="196" cy="30" r="20" fill="rgba(255,255,255,.95)"/>
							<image id="fl_bubble_yours" x="176" y="10" width="40" height="40" preserveAspectRatio="xMidYMid slice"/>
						</g>
						<!-- 气泡描边 + 呼吸光环（强化焦点层次） -->
						<circle cx="44" cy="30" r="20" fill="none" stroke="rgba(255,255,255,.55)" stroke-width="1.2" class="fl-bubble-edge"/>
						<circle cx="196" cy="30" r="20" fill="none" stroke="rgba(255,255,255,.55)" stroke-width="1.2" class="fl-bubble-edge fl-bubble-edge2"/>
						<circle cx="44" cy="30" r="23" fill="none" stroke="rgba(255,255,255,.4)" stroke-width="1" class="fl-bubble-ring"/>
						<circle cx="196" cy="30" r="23" fill="none" stroke="rgba(255,255,255,.4)" stroke-width="1" class="fl-bubble-ring fl-bubble-ring2"/>
						<!-- 环绕卫星点：气泡周围缓慢公转，增加细节 -->
						<g class="fl-orbit"><circle cx="44" cy="4" r="2" fill="rgba(255,255,255,.85)"/></g>
						<g class="fl-orbit fl-orbit2"><circle cx="196" cy="4" r="2" fill="rgba(255,255,255,.85)"/></g>
					</svg>
				</div>
				<!-- 实时预览卡：与主题友链墙同款结构（friend-link-container / friend-link-content / friend-link-avatar 等） -->
				<div class="fl-preview friend-link-container card shadow-sm is-empty" id="fl_preview">
					<img id="fl_pv_avatar" class="friend-link-avatar bg-gradient-secondary" alt="" style="border-radius:<?php echo $avatar_radius; ?>">
					<div class="friend-link-content">
						<div class="friend-link-title title text-primary">
							<a id="fl_pv_name" href="#" target="_blank" rel="noopener"><?php _e('友站', 'argon'); ?></a>
						</div>
						<div id="fl_pv_desc" class="friend-link-description"><?php _e('填写后这里会实时预览友链墙效果', 'argon'); ?></div>
						<div class="friend-link-links">
							<a id="fl_pv_arrow" href="#" target="_blank" rel="noopener"><i class="fa fa-angle-right" style="font-weight: bold;"></i></a>
						</div>
					</div>
				</div>
				<!-- 默认文案：由浏览器解码 HTML 实体后再交给 JS，避免 .text() 直接显示 &#xxxx; -->
				<div class="fl-preview-defaults" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden;">
					<span id="fl_pv_default_name"><?php _e('友站', 'argon'); ?></span>
					<span id="fl_pv_default_desc_empty"><?php _e('填写后这里会实时预览友链墙效果', 'argon'); ?></span>
					<span id="fl_pv_default_desc_filled"><?php _e('这里会显示友站描述，让访客一眼了解你。', 'argon'); ?></span>
				</div>
			</div>
			<!-- 右：表单 -->
			<div class="friendlink-apply-form">
				<div class="fl-form-head">
					<h3 class="fl-form-title">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
						<?php echo $edit_cfg ? __('编辑友链', 'argon') : __('申请友链', 'argon'); ?>
					</h3>
					<button type="button" class="fl-close" aria-label="<?php esc_attr_e('关闭', 'argon'); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</div>
				<div class="fl-form-body">
					<form id="friendlink_apply_form" method="post" action="" data-ajaxurl="<?php echo esc_url($ajaxurl); ?>" data-nonce="<?php echo $nonce; ?>" data-msg-saved="<?php echo esc_attr__('保存成功', 'argon'); ?>" data-msg-submitted="<?php echo esc_attr__('提交成功', 'argon'); ?>" data-msg-mail-sent="<?php echo esc_attr__('邮件已发送', 'argon'); ?>" data-msg-unknown="<?php echo esc_attr__('未知错误', 'argon'); ?>" data-msg-fail="<?php echo esc_attr__('提交失败，请稍后重试', 'argon'); ?>">
						<input type="hidden" name="argon_fl_post_id" value="<?php echo $post_id; ?>">
					<div class="fl-grid">
						<div class="fl-field">
							<label class="fl-label" for="flf_name"><?php _e('站点名称', 'argon'); ?> <span class="req">*</span></label>
							<input type="text" class="form-control" id="flf_name" name="argon_fl_name" required>
						</div>
						<div class="fl-field">
							<label class="fl-label" for="flf_url"><?php _e('网站 URL', 'argon'); ?> <span class="req">*</span></label>
							<input type="url" class="form-control" id="flf_url" name="argon_fl_url" required placeholder="https://">
						</div>
						<div class="fl-field">
							<label class="fl-label" for="flf_image"><?php _e('站点图 URL', 'argon'); ?> <span class="opt">（可选）</span></label>
							<input type="url" class="form-control" id="flf_image" name="argon_fl_image" placeholder="https://">
						</div>
					<div class="fl-field" <?php if ($edit_cfg): ?>style="display:none;"<?php endif; ?>>
						<label class="fl-label" for="flf_email"><?php _e('邮箱', 'argon'); ?> <span class="req">*</span></label>
						<input type="email" class="form-control" id="flf_email" name="argon_fl_email" <?php echo $edit_cfg ? '' : 'required'; ?> placeholder="your@email.com">
					</div>
						<div class="fl-field fl-field-full">
							<label class="fl-label" for="flf_desc"><?php _e('描述', 'argon'); ?> <span class="opt">（可选）</span></label>
							<textarea class="form-control" id="flf_desc" name="argon_fl_desc" rows="3" maxlength="60"></textarea>
						</div>
						<div class="fl-field fl-field-full">
							<label class="fl-label" for="flf_linkpage"><?php _e('友链页 URL', 'argon'); ?> <span class="opt">（可选，用于自动互链检查）</span></label>
							<input type="url" class="form-control" id="flf_linkpage" name="argon_fl_linkpage" placeholder="https://">
						</div>
					</div>
						<p class="fl-msg"></p>
						<button type="submit" class="fl-submit"><?php echo $edit_cfg ? __('保存修改', 'argon') : __('建立友链连接', 'argon'); ?> <span class="arrow">&rarr;</span></button>
					</form>
				</div>
			</div>
		</div>
	</div>
	<script>
		(function(){
			// 原生 JS 实现，避免依赖 jQuery 加载时序（主题 jQuery 在页脚合并包中，body 内联脚本执行时尚未就绪）
			function initFriendLinkModal(){
				// 编辑模式配置：邮件短链验证通过后由 wp_footer 钩子注入到 window.argonFriendLinkEdit。
				// 注意须在 DOMContentLoaded（wp_footer 输出之后）再读取，body 内联脚本立即执行时该变量尚不存在。
				var editCfg = (typeof window.argonFriendLinkEdit !== 'undefined') ? window.argonFriendLinkEdit : null;
				var modal = document.querySelector('.friendlink-apply-modal');
				if (!modal || modal.dataset.flmReady) return;   // 幂等：Pjax 重复初始化时跳过
				modal.dataset.flmReady = '1';

				var btn      = document.getElementById('friendlink_apply_btn');
				var backdrop = document.getElementById('friendlink_apply_backdrop');
				var form     = document.getElementById('friendlink_apply_form');
				var preview  = document.getElementById('fl_preview');
				var nameEl   = document.getElementById('fl_pv_name');
				var descEl   = document.getElementById('fl_pv_desc');
				var avatar   = document.getElementById('fl_pv_avatar');
				var arrowEl  = document.getElementById('fl_pv_arrow');
				var bubbleYours = document.getElementById('fl_bubble_yours');
				var defName        = document.getElementById('fl_pv_default_name');
				var defDescEmpty   = document.getElementById('fl_pv_default_desc_empty');
				var defDescFilled  = document.getElementById('fl_pv_default_desc_filled');

				function val(name){ var el = form.querySelector && form.querySelector('[name="' + name + '"]'); return el ? el.value.trim() : ''; }

				// 编辑模式：填充已有友链数据（预览卡由下方 sync() 统一同步）
				if (editCfg && editCfg.edit_mode){
					var setName = form.querySelector('[name="argon_fl_name"]');
					var setUrl  = form.querySelector('[name="argon_fl_url"]');
					var setImg  = form.querySelector('[name="argon_fl_image"]');
					var setDesc = form.querySelector('[name="argon_fl_desc"]');
					var setLp   = form.querySelector('[name="argon_fl_linkpage"]');
					if (setName) setName.value = editCfg.name || '';
					if (setUrl)  setUrl.value  = editCfg.url || '';
					if (setImg)  setImg.value  = editCfg.image || '';
					if (setDesc) setDesc.value = editCfg.desc || '';
					if (setLp)   setLp.value   = editCfg.linkpage || '';
				}

				// 主题会对 article 内所有图片自动加 lazyload 类，其 CSS 会把头像撑成 500px 高/100% 宽，
				// 导致预览卡 UI 错乱。这里在初始化与每次同步时剥离这些类（spinner 规则随之失效），
				// 并用清晰的默认头像占位，避免空态「头像不显示」。
				var FL_DEFAULT_AVATAR = 'data:image/svg+xml,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%2024%2024%27%20fill=%27%2394a3b8%27%3E%3Ccircle%20cx=%2712%27%20cy=%278%27%20r=%274%27/%3E%3Cpath%20d=%27M4%2020c0-4%204-6%208-6s8%202%208%206%27/%3E%3C/svg%3E';
				function stripLazyload(){
					if (!avatar) return;
					avatar.classList.remove('lazyload');
					avatar.className = avatar.className.replace(/\blazyload-style-\d+\b/g, '').replace(/\s{2,}/g, ' ').trim();
				}

				// 数据变化时的「模糊渐变」入场：仅在字段从空↔有 的状态切换时触发，
				// 避免每改一个字就整段重放动画。用 CSS 类重放，规避 WAAPI filter 在内联元素上不生效的问题
				function blurIn(el){
					if (!el || !el.classList) return;
					el.classList.remove('fl-blur-in');
					void el.offsetWidth;   // 强制重排以重启动画
					el.classList.add('fl-blur-in');
				}
				var prev = {name:'', desc:'', img:''};

				// 实时预览：随表单输入更新，结构与友链墙一致；描述与名称解耦，任意字段有内容即退出空态
				function sync(){
					var name = val('argon_fl_name');
					var desc = val('argon_fl_desc');
					var img  = val('argon_fl_image');
					var url  = val('argon_fl_url');

					// 头像：仅由「站点图 URL」决定，与站点名称是否填写无关（否则只填图片不显示）
					stripLazyload();
					if (img){
						if (avatar.getAttribute('src') !== img){ avatar.src = img; if (prev.img === '') blurIn(avatar); }   // 仅从“无图”变为“有图”时模糊渐入
						avatar.setAttribute('data-original', img);
					} else {
						if (avatar.getAttribute('src') !== FL_DEFAULT_AVATAR){ avatar.src = FL_DEFAULT_AVATAR; }
						avatar.removeAttribute('data-original');
					}
					// “友站”连接气泡同步显示所填站点图（无图时清空，露出底层白色圆作占位）
					if (bubbleYours){
						if (img){ bubbleYours.setAttribute('href', img); } else { bubbleYours.removeAttribute('href'); }
					}

					// 空态：仅当所有可见字段都为空时才显示占位文案
					var hasContent = name || desc || img || url;
					if (hasContent){
						preview.classList.remove('is-empty');
					} else {
						preview.classList.add('is-empty');
					}

					// 名称与描述各自独立实时更新；仅当字段“从无到有 / 从有到无”时对该字段做模糊渐变，
					// 避免每改一个字就让整段文字反复模糊（即上次反馈的“整段数据动画”异常）
					var newName = name || (defName ? defName.textContent : '<?php echo esc_js(__('友站', 'argon')); ?>');
					nameEl.textContent = newName;
					if ((name !== '') !== (prev.name !== '')){ blurIn(nameEl.parentElement || nameEl); }

					var newDesc;
					if (desc){
						newDesc = desc;
					} else if (name){
						newDesc = defDescFilled ? defDescFilled.textContent : '';
					} else {
						newDesc = defDescEmpty ? defDescEmpty.textContent : '<?php echo esc_js(__('填写后这里会实时预览友链墙效果', 'argon')); ?>';
					}
					descEl.textContent = newDesc;
					if ((desc !== '') !== (prev.desc !== '')){ blurIn(descEl); }

					// 站点链接：标题与箭头指向所填 URL（头像不再包裹链接，与主题友链墙一致）
					nameEl.href = url || '#';
					arrowEl.href = url || '#';

					// 描述输入框随内容自动增高（无手动调节手柄）
					autoResizeTextarea();

					prev.name = name; prev.desc = desc; prev.img = img;
				}
				function autoResizeTextarea(){
					var ta = form.querySelector('textarea[name="argon_fl_desc"]');
					if (!ta) return;
					ta.style.height = 'auto';
					ta.style.height = ta.scrollHeight + 'px';
				}
				form.addEventListener('input', sync);

			// 居中兜底（CSS 已用 fixed 居中，这里仅处理超出视口的情况）
			function positionModal(){
				if (!modal.classList.contains('open')) return;
				// 移动端（窄屏）完全交给 CSS（inset:0 + margin:auto）居中，
				// 不再用像素覆盖 top/left，避免 translate + 动态视口在内层滚动时造成整块偏移
				if (window.matchMedia('(max-width:680px)').matches){
					modal.style.left = ''; modal.style.top = '';
					modal.style.right = ''; modal.style.bottom = '';
					modal.style.margin = ''; modal.style.transform = '';
					return;
				}
				var vw = window.innerWidth, vh = window.innerHeight;
				var mw = modal.offsetWidth, mh = modal.offsetHeight;
				var left = vw / 2, top = vh / 2;
				if (mw > vw - 16) left = (vw - mw) / 2 + 8;
				if (mh > vh - 16) top  = (vh - mh) / 2 + 8;
				modal.style.left = left + 'px';
				modal.style.top  = top + 'px';
			}
				var rafId = null;
				function onScrollResize(){
					if (rafId) return;
					rafId = requestAnimationFrame(function(){ rafId = null; positionModal(); });
				}
			function closeModal(){
				modal.classList.remove('open');
				backdrop.classList.remove('open');
				document.body.style.overflow = '';
				window.removeEventListener('scroll', onScrollResize);
				window.removeEventListener('resize', onScrollResize);
			}

				btn.addEventListener('click', function(e){
					e.preventDefault();
					if (modal.classList.contains('open')){ closeModal(); return; }
					// 移出到 body，脱离祖先 overflow/transform 包含块，避免被裁剪或定位错位
					document.querySelectorAll('.friendlink-apply-modal').forEach(function(m){ if (m !== modal) m.remove(); });
					document.querySelectorAll('.friendlink-apply-backdrop').forEach(function(b){ if (b !== backdrop) b.remove(); });
					if (modal.parentNode !== document.body) document.body.appendChild(modal);
					if (backdrop.parentNode !== document.body) document.body.appendChild(backdrop);
					sync();
					positionModal();
				// 强制回流：先让浏览器以「关闭态」完成一次样式计算，否则与上面的 DOM 移动同帧执行时会跳过过渡，弹窗直接闪现
				void modal.offsetWidth;
				document.body.style.overflow = 'hidden';   // 锁定背景滚动，避免移动端内层滚动拖动整页、弹窗漂移出屏
				backdrop.classList.add('open');
				modal.classList.add('open');
				window.addEventListener('scroll', onScrollResize);
				window.addEventListener('resize', onScrollResize);
				});
				backdrop.addEventListener('click', closeModal);
				modal.querySelector('.fl-close').addEventListener('click', function(e){ e.preventDefault(); closeModal(); });
				document.addEventListener('click', function(e){
					if (modal.classList.contains('open') && !modal.contains(e.target) && !btn.contains(e.target)) closeModal();
				});
				document.addEventListener('keydown', function(e){
					if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
				});

				// 提交成功动效：调用 wp_footer 注入的全局 flSuccessFx（脱离 the_content 过滤链，避免闭合标签被破坏）
				form.addEventListener('submit', function(e){
					e.preventDefault();
					if (!form.checkValidity()){ form.reportValidity(); return; }
					var ajaxurl = form.getAttribute('data-ajaxurl');
					var nonce   = form.getAttribute('data-nonce');
					var fd = new FormData(form);
					if (editCfg && editCfg.edit_mode){
						fd.append('action', 'argon_fl_edit');
						fd.append('link_id', editCfg.link_id);
						fd.append('token', editCfg.token);
					} else {
						fd.append('action', 'argon_fl_apply');
					}
					fd.append('argon_ajax_nonce', nonce);
					fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
						.then(function(r){ return r.json(); })
						.then(function(res){
							if (res && res.status === 'success'){
								if (editCfg && editCfg.edit_mode){
									// 编辑保存成功：清理 URL 验证参数，动画独立播放，弹窗立即关闭，随后刷新页面让友链墙显示新数据
									try {
										var u = new URL(location.href);
										u.searchParams.delete('fl_edit_verified');
										u.searchParams.delete('fl_token');
										history.replaceState(null, '', u.toString());
									} catch (e) {}
									window.flSuccessFx(form.dataset.msgSaved);
									closeModal();
									btn.classList.add('just-submitted');
									setTimeout(function(){ btn.classList.remove('just-submitted'); }, 600);
									setTimeout(function(){ location.reload(); }, 1200);
									return;
								}
								window.flSuccessFx(form.dataset.msgSubmitted);
								form.reset();
								sync();
								btn.classList.add('just-submitted');
								setTimeout(function(){ btn.classList.remove('just-submitted'); }, 600);
								closeModal(); // 动画独立播放，弹窗立即关
								// 提交成功后展示本站友链信息弹窗（若后台启用）
								if (typeof window.argonFlShowSiteInfo === 'function'){ setTimeout(window.argonFlShowSiteInfo, 600); }
							} else if (res && res.status === 'need_confirm'){
								// 邮箱确认流程：邮件已发送，提示查收邮件点击链接
								window.flSuccessFx(form.dataset.msgMailSent);
								btn.classList.add('just-submitted');
								setTimeout(function(){ btn.classList.remove('just-submitted'); }, 600);
								closeModal(); // 动画独立播放，弹窗立即关
								// 提交成功后展示本站友链信息弹窗（若后台启用）
								if (typeof window.argonFlShowSiteInfo === 'function'){ setTimeout(window.argonFlShowSiteInfo, 600); }
							} else {
								// 错误：iziToast 红色提示（与评论发送失败同款）
								if (typeof iziToast !== 'undefined'){
									iziToast.show({
										title: form.dataset.msgUnknown,
										message: (res && res.msg) ? res.msg : form.dataset.msgFail,
										class: 'shadow-sm',
										position: 'topRight',
										backgroundColor: '#f5365c',
										titleColor: '#ffffff',
										messageColor: '#ffffff',
										iconColor: '#ffffff',
										progressBarColor: '#ffffff',
										icon: 'fa fa-close',
										timeout: 5000
									});
								}
							}
						})
						.catch(function(){
							// 请求失败：iziToast 红色提示（与评论发送失败同款）
							if (typeof iziToast !== 'undefined'){
								iziToast.show({
									title: form.dataset.msgFail,
									class: 'shadow-sm',
									position: 'topRight',
									backgroundColor: '#f5365c',
									titleColor: '#ffffff',
									messageColor: '#ffffff',
									iconColor: '#ffffff',
									progressBarColor: '#ffffff',
									icon: 'fa fa-close',
									timeout: 5000
								});
							}
						});
				});

				sync();
				// 编辑模式：页面加载后自动打开编辑弹窗（用户从邮件短链进入）
				if (editCfg && editCfg.edit_mode){
					setTimeout(function(){ if (btn) btn.click(); }, 300);
				}
			}

			if (document.readyState === 'loading'){
				document.addEventListener('DOMContentLoaded', initFriendLinkModal);
			} else {
				initFriendLinkModal();
			}
			document.addEventListener('pjax:end', initFriendLinkModal);
			window.addEventListener('load', initFriendLinkModal);
		})();
	</script>
	<?php
	return ob_get_clean();
}
