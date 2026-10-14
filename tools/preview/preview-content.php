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
 * preview-content.php —— 只渲染【正文图片】那一段（argon_lazyload 的产物），
 *                       用来单独验证正文图的占位与尺寸预留
 *
 * 与 local-preview.php 分开，是因为正文图片要跑的是 argon_lazyload()，
 * 需要额外一堆 WP 桩（is_feed/is_robots/is_home/attachment_url_to_postid…），
 * 单独一个文件更容易看清。
 *
 * 用法: php tools/preview/preview-content.php [--reset]
 */

$THEME = dirname(__DIR__, 2);
$WORK_DIR = __DIR__ . '/.work';
$META_FILE = __DIR__ . '/.preview-content-meta.json';

if (in_array('--reset', $argv)) {
	@unlink($META_FILE);
	echo "已清掉缓存的附件元数据\n";
}
if (!is_dir($WORK_DIR)) { mkdir($WORK_DIR, 0777, true); }

/* ============ 桩 ============ */
class WP_Error { public function __construct($c = '', $m = '') {} }
function is_wp_error($t) { return $t instanceof WP_Error; }
function is_feed() { return false; }
function is_robots() { return false; }
function is_home() { return false; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function apply_filters($t, $v) { return $v; }
$GLOBALS['__options'] = array('argon_lazyload_loading_style' => 'none');
function get_option($k, $d = false) { return $GLOBALS['__options'][$k] ?? $d; }

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
function wp_get_attachment_metadata($id) { $m = __meta_load(); return $m[$id] ?? array(); }
function wp_update_attachment_metadata($id, $meta) { __meta_load(); $GLOBALS['__meta'][$id] = $meta; __meta_save(); }
function get_attached_file($id) { $m = __meta_load(); return $m['files'][$id] ?? ''; }
function attachment_url_to_postid($u) { $m = __meta_load(); return $m['url2id'][$u] ?? 0; }
function wp_get_attachment_image_src($id, $size = 'full') {
	$m = __meta_load();
	$base = $m['files'][$id] ?? '';
	if (!$base) { return false; }
	if ($size === 'argon-lqip' && !empty($m[$id]['sizes']['argon-lqip']['file'])) {
		return array(__url_of(dirname($base) . '/' . $m[$id]['sizes']['argon-lqip']['file']), 32, 0, false);
	}
	return array(__url_of($base), 0, 0, false);
}
function __url_of($abs) {
	$rel = str_replace('\\', '/', substr($abs, strlen(dirname(__DIR__, 2))));
	return '/wp-content/themes/argon-theme' . $rel;
}

class Preview_Image_Editor {
	private $src, $img, $w, $h, $quality = 82;
	public function __construct($file) {
		$info = @getimagesize($file);
		if (!$info) { throw new Exception('无法读取图片'); }
		$this->w = $info[0]; $this->h = $info[1];
		$this->img = @imagecreatefromstring(@file_get_contents($file));
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
	catch (Exception $e) { return new WP_Error('x', $e->getMessage()); }
}

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
foreach (array('argon_generate_lqip', 'argon_get_lqip_data_uri', 'argon_get_img_meta_for_src', 'argon_get_lqip_for_img_src') as $fn) {
	$code .= extract_fn($src, $fn) . "\n";
}
$lazy = extract_fn($src, 'argon_lazyload');
/* argon_lazyload 里还有一段 else 分支（正则兜底），一并保留 */
$li = strpos($src, 'function argon_lazyload(');
$d = 0; $lj = null;
for ($k = $li; $k < strlen($src); $k++) {
	if ($src[$k] === '{') { $d++; }
	elseif ($src[$k] === '}') { $d--; if ($d === 0) { $lj = $k; break; } }
}
$lazy = substr($src, $li, $lj - $li + 1);
eval($code . "\n" . $lazy);

/* ============ 造几张【不同宽高比】的图 ============ */
$specs = array(
	array('name' => 'wide',    'w' => 1600, 'h' => 900),
	array('name' => 'square',  'w' => 800,  'h' => 800),
	array('name' => 'tall',    'w' => 600,  'h' => 1200),
	array('name' => 'small',   'w' => 320,  'h' => 180),
);
__meta_load();
$url2id = array();
$files = array();
$id = 100;
foreach ($specs as $s) {
	$id++;
	$f = $WORK_DIR . '/content-' . $s['name'] . '.jpg';
	if (!file_exists($f)) {
		$im = imagecreatetruecolor($s['w'], $s['h']);
		/* 画点渐变，方便看出占位是否被拉伸 */
		for ($y = 0; $y < $s['h']; $y += 8) {
			$c = imagecolorallocate($im, (int) (40 + ($y * 180 / max(1, $s['h']))), 90, (int) (200 - ($y * 120 / max(1, $s['h']))));
			imagefilledrectangle($im, 0, $y, $s['w'], $y + 7, $c);
		}
		imagejpeg($im, $f, 85);
		imagedestroy($im);
	}
	$GLOBALS['__meta'][$id] = array('width' => $s['w'], 'height' => $s['h'], 'sizes' => array());
	$files[$id] = $f;
	$url = 'https://example.com/wp-content/uploads/2026/10/content-' . $s['name'] . '.jpg';
	$url2id[$url] = $id;
}
$GLOBALS['__meta']['files'] = $files;
$GLOBALS['__meta']['url2id'] = $url2id;
__meta_save();

/* ============ 跑真实的 argon_lazyload() ============ */
$content = '<p>正文段落，用来占位。</p>';
foreach ($specs as $s) {
	$url = 'https://example.com/wp-content/uploads/2026/10/content-' . $s['name'] . '.jpg';
	$content .= '<figure class="wp-block-image"><img src="' . $url . '" alt="' . $s['name'] . '" class="wp-image-' . $s['name'] . '"></figure>';
}
$out = argon_lazyload($content);

/* ============ 报告 ============ */
echo "=== 正文图片占位预览 ===\n\n";
echo "  输入 " . count($specs) . " 张不同宽高比的图\n\n";
echo "  输出标记（每张 img 的属性）:\n";
preg_match_all('/<img[^>]*>/i', $out, $m);
foreach ($m[0] as $i => $tag) {
	$attrs = array();
	preg_match_all('/([a-zA-Z-]+)="([^"]*)"/', $tag, $a, PREG_SET_ORDER);
	foreach ($a as $x) { $attrs[$x[1]] = $x[2]; }
	$bg = isset($attrs['style']) && preg_match('/background-image:url\(([^)]{0,40})/', $attrs['style'], $bm) ? $bm[1] . '…' : '(无)';
	printf("    #%d  width=%-6s height=%-6s  class=%-34s  bg=%s\n",
		$i + 1,
		$attrs['width'] ?? '(缺)',
		$attrs['height'] ?? '(缺)',
		$attrs['class'] ?? '',
		$bg);
}

/* 用真实的附件元数据核对一遍 */
echo "\n  与附件元数据核对:\n";
$okAll = true;
foreach ($specs as $s) {
	$url = 'https://example.com/wp-content/uploads/2026/10/content-' . $s['name'] . '.jpg';
	$meta = argon_get_img_meta_for_src($url);
	$expect = $s['w'] . 'x' . $s['h'];
	$got = $meta[1] . 'x' . $meta[2];
	$ok = ($meta[1] === $s['w'] && $meta[2] === $s['h']);
	if (!$ok) { $okAll = false; }
	printf("    %-8s 期望 %-10s 得到 %-10s  %s\n", $s['name'], $expect, $got, $ok ? '✓' : '✗');
}
echo "\n  " . ($okAll ? '宽高全部正确 ✓' : '有宽高取错 ✗') . "\n";

file_put_contents(__DIR__ . '/preview-content.html', $out);
echo "\n  标记已写入 tools/preview/preview-content.html\n";
