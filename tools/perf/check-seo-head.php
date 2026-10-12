<?php
/**
 * check-seo-head.php —— 验证 header.php 的 SEO 头部区块
 *
 * 覆盖两项修复：
 *   P1-1  favicon / manifest 必须【独立于】argon_enable_social_meta 输出
 *   P3-b  og:url 必须与 canonical 一致（原先在朴素固定链接下退化成首页）
 *
 * 做法与 check-seo-switches.php 一致：从 header.php 抽出真实区块并 eval，
 * 桩掉 WP 条件函数后断言输出。
 *
 * 用法: php tools/perf/check-seo-head.php
 */

$theme = dirname(__DIR__, 2);
$lines = explode("\n", file_get_contents($theme . '/header.php'));

/* 抽取：从 `<!-- SEO / OG / Twitter -->` 那行起，到 social_meta 的 if 闭合为止。
   结束锚点用 `twitter:title` 之后紧邻的 `<?php } ?>`（那正是 social_meta 的闭合行）。
   注意不能锚在 og:image:height —— 那之后只关了 og_size / og_image 两层，
   social_meta 那层还在，会得到「Unclosed '{'」。 */
$start = null;
foreach ($lines as $i => $l) {
	if (strpos($l, '<!-- SEO / OG / Twitter -->') !== false) { $start = $i; break; }
}
if ($start === null) { fwrite(STDERR, "找不到 SEO 区块起点\n"); exit(1); }

$end = null;
for ($i = $start; $i < count($lines); $i++) {
	if (strpos($lines[$i], 'twitter:title') !== false) {
		for ($j = $i; $j < $i + 4 && $j < count($lines); $j++) {
			if (strpos($lines[$j], '<?php } ?>') !== false) { $end = $j; break 2; }
		}
	}
}
if ($end === null) { fwrite(STDERR, "找不到 SEO 区块结尾\n"); exit(1); }

$block = implode("\n", array_slice($lines, $start, $end - $start + 1));

/* ---- 桩 ---- */
$GLOBALS['__opts'] = array();
$GLOBALS['__ctx'] = array();
$GLOBALS['__urls'] = array();

function get_option($k, $d = '') {
	return array_key_exists($k, $GLOBALS['__opts']) ? $GLOBALS['__opts'][$k] : $d;
}
function get_site_icon_url($s = 512) { return 'https://example.com/icon-512.png'; }
function get_bloginfo($k = '') { return $k === 'name' ? 'SITE-NAME' : 'SITE-DESC'; }
function wp_get_document_title() { return 'DOC-TITLE'; }
function get_locale() { return 'zh_CN'; }
function home_url($p = '/') { return 'https://example.com' . ($p === '/' ? '/' : $p); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function get_seo_description() { return 'DESC'; }
function get_seo_keywords() { return 'KW'; }
function argon_get_social_image() { return ''; }   // 简化：不测 og:image 分支
function argon_get_image_size($u) { return false; }
function is_singular($t = '') { return !empty($GLOBALS['__ctx']['singular']); }
function is_front_page() { return !empty($GLOBALS['__ctx']['front']); }
function is_home() { return !empty($GLOBALS['__ctx']['home']); }
function is_paged() { return !empty($GLOBALS['__ctx']['paged']); }
function is_category() { return !empty($GLOBALS['__ctx']['category']); }
function is_tag() { return false; }
function is_tax() { return false; }
function is_author() { return false; }
function is_post_type_archive() { return false; }
function is_search() { return !empty($GLOBALS['__ctx']['search']); }
function get_permalink() { return 'https://example.com/archives/123'; }
/* 模拟 WP 的 get_pagenum_link：第 1 页省略页码 */
function get_pagenum_link($n = 1) {
	$base = !empty($GLOBALS['__ctx']['category'])
		? 'https://example.com/category/foo'
		: 'https://example.com/';
	return $n > 1 ? rtrim($base, '/') . '/page/' . $n . '/' : $base;
}
function get_search_link() { return 'https://example.com/?s=hello'; }
function get_query_var($k) { return $k === 'paged' ? (int) ($GLOBALS['__ctx']['paged_num'] ?? 1) : ''; }
/* 区块后半段（article:section / article:tag / theme-color）用到的桩 */
function get_author_posts_url($id = 0) { return 'https://example.com/author/admin'; }
function get_the_category($id = 0) { return array((object) array('name' => 'CAT')); }
function get_the_tags($id = 0) { return array((object) array('name' => 'TAG')); }
function get_the_date($f = '', $id = 0) { return '2026-01-01T00:00:00+08:00'; }
function get_the_modified_date($f = '', $id = 0) { return '2026-01-02T00:00:00+08:00'; }
function hex2str($hex) { return '94,114,228'; }
function argon_get_option($k, $d = '') { return $d; }

$GLOBALS['post'] = (object) array('ID' => 1, 'post_author' => 1);
$GLOBALS['themecolor'] = '#5e72e4';
$GLOBALS['themecolor_origin'] = '#5e72e4';
$GLOBALS['cardradius'] = '4px';
$GLOBALS['cardradius_origin'] = '4px';
$GLOBALS['theme_version'] = '1.0.0';

function run_case($opts, $ctx) {
	$GLOBALS['__opts'] = $opts;
	$GLOBALS['__ctx'] = $ctx;
	ob_start();
	/* 注意：本区块以 HTML 注释 `<!-- SEO / OG / Twitter -->` 开头，
	   所以 eval 时必须先 '?>' 切回 HTML 模式，否则 PHP 会把 '<!--' 当语法错误。
	   （check-font-enqueue.php 抽的块以 PHP 开头，那里【不能】加 '?>'，两种情况相反。） */
	eval('?>' . $GLOBALS['__block']);
	return ob_get_clean();
}

$GLOBALS['__block'] = $block;

$fail = 0;
function chk($label, $cond, $note = '') {
	global $fail;
	printf("  %s %s%s\n", $cond ? 'PASS' : 'FAIL', str_pad($label, 46), $cond ? '' : '  ' . $note);
	if (!$cond) { $fail++; }
}

echo "=== P1-1 favicon / manifest 独立于社交元标签开关 ===\n";
$on  = run_case(array(), array('singular' => true));
$off = run_case(array('argon_enable_social_meta' => 'false'), array('singular' => true));
chk('开关开：manifest 输出', strpos($on, 'rel="manifest"') !== false);
chk('开关关：manifest 仍输出', strpos($off, 'rel="manifest"') !== false, '← 这正是修复点');
chk('开关开：favicon 输出', strpos($on, 'rel="icon"') !== false);
chk('开关关：favicon 仍输出', strpos($off, 'rel="icon"') !== false, '← 这正是修复点');
chk('开关开：og:title 输出', strpos($on, 'og:title') !== false);
chk('开关关：og:title 不输出', strpos($off, 'og:title') === false);

echo "\n=== P3-b og:url 与 canonical 一致 ===\n";
$cases = array(
	array('文章页', array('singular' => true), 'https://example.com/archives/123'),
	array('静态首页（不分页）', array('front' => true), 'https://example.com/'),
	array('博客首页 第1页', array('home' => true, 'front' => true), 'https://example.com/'),
	array('博客首页 第2页', array('home' => true, 'front' => true, 'paged' => true, 'paged_num' => 2), 'https://example.com/page/2/'),
	array('分类页 第1页', array('category' => true), 'https://example.com/category/foo'),
	array('分类页 第2页', array('category' => true, 'paged' => true, 'paged_num' => 2), 'https://example.com/category/foo/page/2/'),
	array('搜索页', array('search' => true), 'https://example.com/?s=hello'),
);
foreach ($cases as $c) {
	list($name, $ctx, $want) = $c;
	$out = run_case(array(), $ctx);
	$got = '';
	if (preg_match('/<meta property="og:url" content="([^"]*)"/', $out, $m)) { $got = $m[1]; }
	chk($name . ' og:url', $got === $want, "得到「{$got}」期望「{$want}」");
}

echo "\n=== 转义（P1-2）：description 三处都要 esc_attr ===\n";
$out = run_case(array(), array('singular' => true));
chk('name="description" 已转义', strpos($out, 'name="description" content="DESC"') !== false);
chk('og:description 已转义', strpos($out, 'og:description" content="DESC"') !== false);
chk('twitter:description 已转义', strpos($out, 'twitter:description" content="DESC"') !== false);

echo "\n";
if ($fail === 0) {
	echo "=== 全部通过 ===\n";
	exit(0);
}
echo "=== $fail 项失败 ===\n";
exit(1);
