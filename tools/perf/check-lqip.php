<?php
/**
 * check-lqip.php —— 验证 LQIP（低清占位图）的生成与取用
 *
 * 覆盖：
 *   1 注册了 argon-lqip 尺寸，且【没有】被「摘掉三档尺寸」的过滤器误删
 *   2 元数据里已有 lqip 时直接读取，不再跑图像处理
 *   3 元数据里没有时按需生成，并写回附件元数据
 *   4 取不到（附件不在本地 / 编辑器不可用）时返回 ''，调用方据此退回骨架
 *   5 输出的是 data URI，且 mime 与文件扩展名一致
 *   6 argon_render_post_thumbnail() 在【有/无 LQIP】两种情况下都输出正确的标记
 *
 * 用法: php tools/perf/check-lqip.php
 */

$theme = dirname(__DIR__, 2);
$src = file_get_contents($theme . '/functions.php');

$fail = 0;
function chk($label, $cond, $note = '') {
	global $fail;
	printf("  %s %s%s\n", $cond ? 'PASS' : 'FAIL', str_pad($label, 54), $cond ? '' : '  ' . $note);
	if (!$cond) { $fail++; }
}

echo "=== 1. 静态检查 ===\n";
chk('注册了 argon-lqip 尺寸', strpos($src, "add_image_size('argon-lqip', 32, 0, false)") !== false);
/* 摘掉三档尺寸的过滤器不能把 argon-lqip 也摘了 */
$fk = strpos($src, "add_filter('intermediate_image_sizes_advanced'");
$fk2 = $fk === false ? false : strpos($src, '});', $fk);
$fblock = ($fk !== false && $fk2 !== false) ? substr($src, $fk, $fk2 - $fk) : '';
chk('摘尺寸的过滤器不含 argon-lqip', $fblock !== '' && strpos($fblock, 'argon-lqip') === false);

echo "\n=== 2. 行为检查（桩掉 WP 函数） ===\n";

/* ---- 桩 ---- */
$GLOBALS['__files'] = array();          // 虚拟文件系统: path => contents
$GLOBALS['__meta'] = array();           // attachment_id => metadata
$GLOBALS['__editor_calls'] = 0;
$GLOBALS['__editor_ok'] = true;
$GLOBALS['__updates'] = 0;

function file_exists_stub($p) { return isset($GLOBALS['__files'][$p]); }
function get_attached_file($id) { return $GLOBALS['__files']["attached:$id"] ?? ''; }
function wp_get_attachment_metadata($id) { return $GLOBALS['__meta'][$id] ?? array(); }
function wp_update_attachment_metadata($id, $m) { $GLOBALS['__meta'][$id] = $m; $GLOBALS['__updates']++; }
function get_post_thumbnail_id($id = 0) { return $GLOBALS['__tid'] ?? 7; }
function has_post_thumbnail($id = 0) { return true; }
function wp_get_image_editor($f) {
	$GLOBALS['__editor_calls']++;
	if (!$GLOBALS['__editor_ok']) { return new WP_Error('x'); }
	return new FakeEditor($f);
}
class WP_Error { public function __construct($c = '') {} }
function is_wp_error($t) { return $t instanceof WP_Error; }
class FakeEditor {
	private $f;
	private $w;
	public function __construct($f) { $this->f = $f; }
	public function resize($w, $h, $crop) { $this->w = $w; return true; }
	public function set_quality($q) { return true; }
	public function save($path) {
		/* 写【真实】文件，这样 file_exists / file_get_contents 都能走通 */
		@file_put_contents($path, "\xFF\xD8\xFF\xE0FAKEJPEG");
		return array('path' => $path, 'width' => 32, 'height' => 20, 'mime-type' => 'image/jpeg');
	}
}
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/* 把 file_exists / file_get_contents 换成认识虚拟文件系统的版本：
   用命名空间技巧不可行（源码里是全局函数），改为直接在 eval 前 redefine ——
   PHP 不允许重定义内置函数，所以改用 stream wrapper 太重。
   这里退一步：只测「元数据已有」与「生成失败」两条路径，
   它们不依赖 file_get_contents 的桩。 */

/* 抽出需要测的三个函数 */
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
$fns = '';
foreach (array('argon_generate_lqip', 'argon_get_lqip_data_uri', 'argon_render_post_thumbnail', 'argon_get_post_lqip') as $n) {
	$fns .= extract_fn($src, $n) . "\n";
}
chk('四个函数都抽到了', substr_count($fns, 'function ') === 4, '抽到 ' . substr_count($fns, 'function '));
eval($fns);

echo "\n  场景 A：附件不在本地（get_attached_file 返回空）→ 应返回空串\n";
$GLOBALS['__tid'] = 7;
$GLOBALS['__files'] = array();
$GLOBALS['__meta'] = array();
$r = argon_get_lqip_data_uri(7);
chk('返回空串（调用方据此退回骨架）', $r === '', var_export($r, true));

echo "\n  场景 B：附件在本地、但还没有 lqip → 应按需生成、写回元数据、返回 data URI\n";
$tmp = sys_get_temp_dir() . '/argon-lqip-test-' . getmypid();
@mkdir($tmp, 0777, true);
$orig = $tmp . '/orig.jpg';
file_put_contents($orig, str_repeat('X', 5000));
$GLOBALS['__tid'] = 8;
$GLOBALS['__files'] = array('attached:8' => $orig);
$GLOBALS['__meta'] = array(8 => array('sizes' => array('thumbnail' => array('file' => 'orig-150x150.jpg'))));
$GLOBALS['__editor_calls'] = 0;
$GLOBALS['__updates'] = 0;
$r = argon_get_lqip_data_uri(8);
chk('返回了 data URI', strpos($r, 'data:image/jpeg;base64,') === 0, substr($r, 0, 40));
chk('确实调用了图像编辑器', $GLOBALS['__editor_calls'] === 1, '调用 ' . $GLOBALS['__editor_calls'] . ' 次');
chk('写回了附件元数据', $GLOBALS['__updates'] === 1, '写入 ' . $GLOBALS['__updates'] . ' 次');
chk('元数据里记下了 argon-lqip', !empty($GLOBALS['__meta'][8]['sizes']['argon-lqip']['file']));
chk('元数据保留了原有尺寸', isset($GLOBALS['__meta'][8]['sizes']['thumbnail']));

echo "\n  场景 B2：再取一次 —— 应直接读元数据，不再跑图像处理\n";
$GLOBALS['__editor_calls'] = 0;
$r2 = argon_get_lqip_data_uri(8);
chk('不再调用编辑器', $GLOBALS['__editor_calls'] === 0, '调用 ' . $GLOBALS['__editor_calls'] . ' 次');
chk('返回同样的 data URI', $r2 === $r);
/* 同请求内还应有静态缓存 */
$GLOBALS['__editor_calls'] = 0;
argon_get_lqip_data_uri(8);
chk('同请求内命中静态缓存', $GLOBALS['__editor_calls'] === 0);

echo "\n  场景 B3：有 LQIP 时渲染函数输出模糊层\n";
ob_start();
argon_render_post_thumbnail('https://example.com/a.jpg', true);
$htmlB = ob_get_clean();
chk('有 has-lqip 类', strpos($htmlB, 'has-lqip') !== false);
chk('输出了模糊层', strpos($htmlB, 'post-thumbnail-lqip') !== false);
chk('模糊层是 data URI', strpos($htmlB, 'src="data:image/jpeg;base64,') !== false);
chk('同时仍有清晰层', strpos($htmlB, 'post-thumbnail lazyload') !== false);

echo "\n  场景 C：图像编辑器不可用 → 返回空，不写元数据\n";
$GLOBALS['__editor_ok'] = false;
$GLOBALS['__tid'] = 9;
$GLOBALS['__meta'] = array(9 => array('sizes' => array()));
$GLOBALS['__updates'] = 0;
$r = argon_get_lqip_data_uri(9);
chk('返回空串', $r === '', var_export($r, true));
chk('没有写坏元数据', $GLOBALS['__updates'] === 0, '写入 ' . $GLOBALS['__updates'] . ' 次');
$GLOBALS['__editor_ok'] = true;
@unlink($orig);
@rmdir($tmp);

echo "\n  场景 D：渲染函数 —— 无 LQIP 时退回单 <img> 形态\n";
$GLOBALS['__files'] = array();
$GLOBALS['__meta'] = array();
ob_start();
argon_render_post_thumbnail('https://example.com/a.jpg', true);
$html = ob_get_clean();
chk('有包裹层', strpos($html, 'post-thumbnail-wrap') !== false);
chk('没有 has-lqip 类', strpos($html, 'has-lqip') === false);
chk('没有模糊层', strpos($html, 'post-thumbnail-lqip') === false);
chk('保留 lazyload 与 data-original', strpos($html, 'lazyload') !== false && strpos($html, 'data-original') !== false);
chk('保留 1×1 占位 src', strpos($html, 'data:image/png;base64,') !== false);

echo "\n  场景 E：渲染函数 —— 非懒加载模式\n";
ob_start();
argon_render_post_thumbnail('https://example.com/a.jpg', false);
$html2 = ob_get_clean();
chk('不带 lazyload 类', strpos($html2, 'lazyload') === false);
chk('直接输出 src', strpos($html2, "src=\"https://example.com/a.jpg\"") !== false || strpos($html2, "src='https://example.com/a.jpg'") !== false);
chk('仍有包裹层', strpos($html2, 'post-thumbnail-wrap') !== false);

echo "\n";
if ($fail === 0) {
	echo "=== 全部通过 ===\n";
	exit(0);
}
echo "=== $fail 项失败 ===\n";
exit(1);
