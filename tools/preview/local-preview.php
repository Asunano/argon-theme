<?php
/* LQIP 失败标记现在还会走 post meta 兜底（附件元数据缺失时用），
   桩里必须补上这几个函数，否则会 Fatal。 */
if (!function_exists('get_post_meta')) {
	function get_post_meta($id, $key = '', $single = false){ return isset($GLOBALS['__post_meta'][$id][$key]) ? $GLOBALS['__post_meta'][$id][$key] : ''; }
}
if (!function_exists('update_post_meta')) {
	function update_post_meta($id, $key, $value){ $GLOBALS['__post_meta'][$id][$key] = $value; return true; }
}
if (!function_exists('delete_post_meta')) {
	function delete_post_meta($id, $key){ unset($GLOBALS['__post_meta'][$id][$key]); return true; }
}
/**
 * local-preview.php —— 本地预览：用桩函数渲染【真实模板】，不依赖 WordPress
 *
 * 为什么需要它：
 *   之前的本地验证是「拿线上渲染好的 HTML 套本地 CSS/JS」——
 *   而线上 HTML 是【已部署的旧 PHP】渲染的，所以那些验证根本没跑过新模板，
 *   还要靠 JS 就地合成包裹层来绕过。这个脚本直接跑真实模板，
 *   让 LQIP / 骨架屏在本机就能被看到和量到。
 *
 * 用法:
 *   php tools/preview/local-preview.php            # 生成 preview.html
 *   php tools/preview/local-preview.php --reset    # 清掉缓存的附件元数据（重跑首次生成）
 */

$THEME = dirname(__DIR__, 2);
$OUT_DIR = __DIR__;
$META_FILE = $OUT_DIR . '/.preview-meta.json';
$OUT_HTML = $OUT_DIR . '/preview.html';
/* 所有生成物都落在这个临时工作区里 —— 【绝不写进主题目录】。
   早先的版本让编辑器直接往 assets/img/theme/ 里写 lqip 文件，
   既污染仓库，又会被 glob('*.jpg') 重新捡到、级联生成 -argon-lqip-argon-lqip.jpg。 */
$WORK_DIR = $OUT_DIR . '/.work';

if (in_array('--reset', $argv)) {
	@unlink($META_FILE);
	if (is_dir($WORK_DIR)) {
		foreach (glob($WORK_DIR . '/*') as $f) { @unlink($f); }
	}
	echo "已清掉缓存的附件元数据与工作区\n";
}
if (!is_dir($WORK_DIR)) { mkdir($WORK_DIR, 0777, true); }

/* ============ 桩：WordPress 函数 ============ */

class WP_Error { public function __construct($code = '', $msg = '') {} }
function is_wp_error($t) { return $t instanceof WP_Error; }

$GLOBALS['__meta'] = null;
function __meta_load() {
	global $META_FILE;
	if ($GLOBALS['__meta'] === null) {
		$GLOBALS['__meta'] = file_exists($META_FILE) ? (json_decode(file_get_contents($META_FILE), true) ?: array()) : array();
	}
	return $GLOBALS['__meta'];
}
function __meta_save() {
	global $META_FILE;
	file_put_contents($META_FILE, json_encode($GLOBALS['__meta'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/* 图片编辑器：用真实 GD 实现，行为对齐 WP 的 WP_Image_Editor 子集 */
class Preview_Image_Editor {
	private $src, $img, $w, $h, $quality = 82;
	public function __construct($file) {
		$this->src = $file;
		$info = @getimagesize($file);
		if (!$info) { throw new Exception('无法读取图片'); }
		$this->w = $info[0]; $this->h = $info[1];
		$data = @file_get_contents($file);
		$this->img = @imagecreatefromstring($data);
		if (!$this->img) { throw new Exception('无法解码图片'); }
	}
	public function resize($w, $h = null, $crop = false) {
		if ($h === null || $h === 0) { $h = (int) round($this->h * ($w / $this->w)); }
		$dst = imagecreatetruecolor($w, $h);
		imagealphablending($dst, false); imagesavealpha($dst, true);
		imagecopyresampled($dst, $this->img, 0, 0, 0, 0, $w, $h, $this->w, $this->h);
		imagedestroy($this->img);
		$this->img = $dst; $this->w = $w; $this->h = $h;
		return true;
	}
	public function set_quality($q) { $this->quality = (int) $q; return true; }
	public function save($path) {
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		if ($ext === 'webp' && function_exists('imagewebp')) { imagewebp($this->img, $path, $this->quality); $mime = 'image/webp'; }
		elseif ($ext === 'png') { imagepng($this->img, $path, 6); $mime = 'image/png'; }
		else { imagejpeg($this->img, $path, $this->quality); $mime = 'image/jpeg'; }
		return array('path' => $path, 'file' => basename($path), 'width' => $this->w, 'height' => $this->h, 'mime-type' => $mime);
	}
}
function wp_get_image_editor($f) {
	try { return new Preview_Image_Editor($f); }
	catch (Exception $e) { return new WP_Error('image_editor', $e->getMessage()); }
}

/* 附件元数据：持久化到 JSON，这样第二次运行能走「直接读元数据」的路径 */
function wp_get_attachment_metadata($id) { $m = __meta_load(); return $m[$id] ?? array(); }
function wp_update_attachment_metadata($id, $meta) { __meta_load(); $GLOBALS['__meta'][$id] = $meta; __meta_save(); }
function get_attached_file($id) { $m = __meta_load(); return $m['files'][$id] ?? ''; }
/* 必须返回【URL】而不是文件系统路径 —— 否则浏览器加载不了清晰图，
   表现为「data-original 指向本地路径、图永远不出现」。
   服务端把 /wp-content/themes/<dir>/... 映射到主题目录，所以这里按同样规则拼。 */
function __url_of($abs) {
	$rel = str_replace('\\', '/', substr($abs, strlen(dirname(__DIR__, 2))));
	return '/wp-content/themes/argon-theme' . $rel;
}
function wp_get_attachment_image_src($id, $size = 'full') {
	$m = __meta_load();
	$base = isset($m['files'][$id]) ? $m['files'][$id] : '';
	if (!$base) { return false; }
	if ($size === 'argon-lqip' && !empty($m[$id]['sizes']['argon-lqip']['file'])) {
		return array(__url_of(dirname($base) . '/' . $m[$id]['sizes']['argon-lqip']['file']), 32, 0, false);
	}
	return array(__url_of($base), 0, 0, false);
}

/* 循环与文章相关 */
$GLOBALS['__i'] = 0;
$GLOBALS['__posts'] = array();
function has_post_thumbnail($id = 0) { return true; }
function get_post_thumbnail_id($id = 0) { return $GLOBALS['__posts'][$GLOBALS['__i']]['att'] ?? 1; }
function __set_post($i) { $GLOBALS['post'] = (object) array('ID' => $GLOBALS['__posts'][$i]['id'] ?? 0, 'post_content' => $GLOBALS['__posts'][$i]['excerpt'] ?? '', 'post_excerpt' => ''); }
function argon_has_post_thumbnail($id = 0) { return true; }
function the_ID() { echo $GLOBALS['__posts'][$GLOBALS['__i']]['id'] ?? 1; }
function post_class($c = '') { echo "class='post type-post status-publish format-standard has-post-thumbnail hentry'"; }
function the_permalink() { echo $GLOBALS['__posts'][$GLOBALS['__i']]['link'] ?? '#'; }
function the_title() { echo htmlspecialchars($GLOBALS['__posts'][$GLOBALS['__i']]['title'] ?? '', ENT_QUOTES, 'UTF-8'); }
function get_the_title() { return $GLOBALS['__posts'][$GLOBALS['__i']]['title'] ?? ''; }
function get_post_type() { return 'post'; }
function is_sticky() { return false; }
function is_home() { return true; }
function is_paged() { return false; }
function post_password_required() { return false; }
function has_tag() { return false; }
function get_the_tags() { return array(); }
function get_tag_link($t) { return '#'; }
function is_readingtime_meta_hidden() { return true; }
function get_article_reading_time_meta($c) { return ''; }
function get_article_meta($k) {
	$map = array('time' => '<span>2026-10-13</span>', 'views' => '<span>1.2k 阅读</span>', 'comments' => '<span>3 评论</span>', 'categories' => '<span>技术</span>', 'like' => '<span>12 赞</span>');
	return $map[$k] ?? '';
}
function wp_trim_words($t, $n) { $t = trim(strip_tags($t)); return mb_substr($t, 0, 60) . '…'; }
function do_shortcode($t) { return $t; }
function get_the_content($m = '') { return $GLOBALS['__posts'][$GLOBALS['__i']]['excerpt'] ?? '这是一段示例摘要，用于撑出卡片的正文区域。'; }
function __($s, $d = null) { return $s; }
function _e($s, $d = null) { echo $s; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function apply_filters($tag, $v) { return $v; }

$GLOBALS['__options'] = array(
	'argon_enable_into_article_animation' => 'false',
	'argon_enable_lazyload' => 'true',
	'argon_enable_post_like' => 'true',
	'argon_article_meta' => 'time|views|comments|categories',
	'argon_trim_words_count' => 175,
	'argon_show_readingtime' => 'false',
);
function get_option($k, $d = false) { return $GLOBALS['__options'][$k] ?? $d; }

/* ============ 载入真实代码 ============ */

$src = file_get_contents($THEME . '/functions.php');
function extract_fn($src, $name) {
	$i = strpos($src, "function $name(");
	if ($i === false) { return ''; }
	$d = 0; $j = null;
	for ($k = $i; $k < strlen($src); $k++) {
		if ($src[$k] === '{') { $d++; }
		elseif ($src[$k] === '}') { $d--; if ($d === 0) { $j = $k; break; } }
	}
	return $j === null ? '' : substr($src, $i, $j - $i + 1);
}
$code = '';
foreach (array('argon_generate_lqip', 'argon_get_lqip_data_uri', 'argon_get_post_lqip', 'argon_render_post_thumbnail', 'argon_get_post_thumbnail') as $fn) {
	$code .= extract_fn($src, $fn) . "\n";
}
eval($code);

/* ============ 造数据 ============ */

/* 把示例图【复制到工作区】再当附件用 ——
   这样 lqip 会生成在工作区里，主题目录保持干净。 */
$srcImgs = glob($THEME . '/assets/img/theme/*.jpg');
$titles = array('从零搭建前端工程化', 'OAuth 授权码流程笔记', 'Android 抓包与证书固定', 'CSS 层叠与优先级', 'WebP 与图片优化实践', 'WordPress 主题开发小结');

$posts = array();
$files = array();
foreach ($titles as $i => $t) {
	$src = $srcImgs[$i % count($srcImgs)];
	$id = $i + 1;
	$dst = $WORK_DIR . '/att-' . $id . '-' . basename($src);
	if (!file_exists($dst)) { copy($src, $dst); }
	$posts[] = array('id' => 100 + $i, 'att' => $id, 'title' => $t, 'link' => '#post-' . (100 + $i), 'excerpt' => '这是一段示例摘要，用来撑出卡片的正文区域，长度足够换行。');
	$files[$id] = $dst;
}
$GLOBALS['__posts'] = $posts;
__meta_load();
$GLOBALS['__meta']['files'] = $files;
__meta_save();

/* ============ 渲染真实模板 ============ */

ob_start();
/* 注意：模板里有 `for ($i = 0; $i < count($metaList); $i++)` ——
   它会把外层循环变量 $i 覆盖掉，导致只渲染出第一张卡片。
   所以这里刻意用 $ci 作为外层循环变量。 */
for ($ci = 0; $ci < count($posts); $ci++) {
	$GLOBALS['__i'] = $ci;
	__set_post($ci);
	include $THEME . '/template-parts/content-preview-1.php';
}
$cards = ob_get_clean();

/* 统计本次渲染的实际产出 */
$nWrap = substr_count($cards, "class='post-thumbnail");
$nLqip = substr_count($cards, 'has-lqip');
$nSk = substr_count($cards, 'no-lqip');
$nDataUri = preg_match_all('/src="data:image\/(jpeg|webp|png);base64,/', $cards);
$uriBytes = 0;
if (preg_match_all('/src="data:image\/[a-z]+;base64,([A-Za-z0-9+\/=]+)"/', $cards, $m)) {
	foreach ($m[1] as $b) { $uriBytes += strlen($b); }
}

/* ============ 输出 HTML ============ */

/* 页面骨架必须与真实页面一致 —— 否则 argontheme.js 的第一个 IIFE 会
   因为取不到 #banner_container 而抛错，整个文件后续（包括 LQIP 的 JS
   和 .post-thumbnail.lazyload 的初始化）都不会执行，
   表现为「清晰图永远不换 src、LQIP 永远不淡出」。
   这里按 header.php 的结构补齐，并加载与线上同名的资源。 */
$html = '<!DOCTYPE html>
<html class="no-js article-header-style-default">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Argon 本地预览 — LQIP / 骨架屏</title>
<link rel="stylesheet" href="/wp-content/themes/argon-theme/assets/argon_css_merged.css">
<link rel="stylesheet" href="/wp-content/themes/argon-theme/style.css">
</head>
<body class="is-home">
<script>var argonConfig = {wp_path: "/", pangu: "false", lazyload: {threshold: 800, effect: "fadeIn"}};</script>
<script src="/wp-content/themes/argon-theme/assets/argon_js_merged.js"></script>

<div id="toolbar">
	<nav id="navbar-main" class="navbar navbar-main navbar-expand-lg navbar-transparent navbar-light bg-primary">
		<div class="container"><a class="navbar-brand" href="#">Argon 本地预览</a></div>
	</nav>
</div>
<div id="banner_container" class="banner-container container text-center" style="min-height:180px">
	<div style="padding:60px 0;font-size:22px">banner 占位（让顶栏的滚动区间可用）</div>
</div>

<div id="content" class="site-content">
<div id="primary" class="content-area">
<main id="main" class="site-main article-list article-list-home" role="main">
' . $cards . '
</main>
</div>
</div>

<script src="/wp-content/themes/argon-theme/argontheme.js"></script>
</body>
</html>';

file_put_contents($OUT_HTML, $html);

/* ============ 报告 ============ */

echo "=== 本地预览已生成（跑的是真实模板，不是线上 HTML） ===\n";
echo "  输出: $OUT_HTML\n";
echo "  卡片: " . count($posts) . " 张\n";
echo "\n";
echo "  渲染产出:\n";
printf("    %-34s %d\n", '缩略图 img', $nWrap);
printf("    %-34s %d\n", '带 LQIP 背景（has-lqip）', $nLqip);
printf("    %-34s %d\n", '无 LQIP（no-lqip）', $nSk);
printf("    %-34s %d\n", '内联 data URI', $nDataUri);
printf("    %-34s %.1f KB\n", 'data URI 合计', $uriBytes / 1024);
echo "\n";
echo "  附件元数据缓存: " . (file_exists($META_FILE) ? '有（第二次运行会走「直接读」路径）' : '无') . "\n";
$m = __meta_load();
$withLqip = 0;
foreach ($posts as $p) { if (!empty($m[$p['att']]['sizes']['argon-lqip']['file'])) { $withLqip++; } }
printf("    %-34s %d / %d\n", '已生成 argon-lqip 的附件', $withLqip, count($posts));
$failed = 0;
foreach ($posts as $p) { if (!empty($m[$p['att']]['argon_lqip_failed'])) { $failed++; } }
if ($failed) { printf("    %-34s %d\n", '标记为生成失败', $failed); }
