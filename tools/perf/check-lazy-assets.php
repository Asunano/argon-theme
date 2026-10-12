<?php
/**
 * check-lazy-assets.php —— 验证 header.php 下发的按需加载清单随选项正确变化
 *
 * 做法与 check-font-enqueue.php 一致：从 header.php 里【抽出真实代码块】，
 * 桩掉 argon_get_option，eval 后断言。不靠人工核对。
 *
 * 覆盖 4 种选项组合：
 *   fancybox 开/关（由 argon_enable_fancybox 与 argon_enable_zoomify 共同决定）
 *   取色器 开/关（argon_show_customize_theme_color_picker）
 *
 * 用法: php tools/perf/check-lazy-assets.php
 */

$theme = dirname(__DIR__, 2);
$src = file_get_contents($theme . '/header.php');
$lines = explode("\n", $src);

/* 抽出 $argon_lazy_assets 的构建块：
   从「$argon_lazy_assets = array();」开始，到该 PHP 段结束的 '?>' 之前。
   注意【不能】用花括号配对找结尾 —— 第一个 if 结束时深度就归零了，
   会把后面 pickr/nouislider 那两段漏掉（踩过）。 */
$start = null;
foreach ($lines as $i => $l) {
	if (strpos($l, '$argon_lazy_assets = array();') !== false) { $start = $i; break; }
}
if ($start === null) {
	fwrite(STDERR, "找不到 \$argon_lazy_assets 构建块\n");
	exit(1);
}
$end = null;
for ($i = $start; $i < count($lines); $i++) {
	if (strpos($lines[$i], '?>') !== false) { $end = $i - 1; break; }
}
if ($end === null || $end < $start) {
	fwrite(STDERR, "找不到 \$argon_lazy_assets 块的结尾\n");
	exit(1);
}
$block = implode("\n", array_slice($lines, $start, $end - $start + 1));

/* 桩：把 argon_get_option 换成查表 */
$GLOBALS['__opts'] = array();
function argon_get_option($k, $d = '') {
	return array_key_exists($k, $GLOBALS['__opts']) ? $GLOBALS['__opts'][$k] : $d;
}
$GLOBALS['assets_path'] = 'https://cdn.example.com';
$GLOBALS['theme_version'] = '9.9.9';

function run_case($opts) {
	$GLOBALS['__opts'] = $opts;
	$GLOBALS['argon_lazy_assets'] = null;
	$code = 'global $argon_lazy_assets, $assets_path, $theme_version;' . "\n" . $GLOBALS['__block'];
	eval($code);
	return $GLOBALS['argon_lazy_assets'];
}

$GLOBALS['__block'] = $block;

$cases = array(
	array(
		'name' => 'fancybox 开 + 取色器 开',
		'opts' => array('argon_enable_fancybox' => 'true', 'argon_enable_zoomify' => 'false', 'argon_show_customize_theme_color_picker' => 'true'),
		'want' => array('fancybox', 'pickr', 'nouislider'),
	),
	array(
		'name' => 'fancybox 关 + 取色器 开',
		'opts' => array('argon_enable_fancybox' => 'false', 'argon_enable_zoomify' => 'false', 'argon_show_customize_theme_color_picker' => 'true'),
		'want' => array('pickr', 'nouislider'),
	),
	array(
		'name' => 'fancybox 开 + 取色器 关',
		'opts' => array('argon_enable_fancybox' => 'true', 'argon_enable_zoomify' => 'false', 'argon_show_customize_theme_color_picker' => 'false'),
		'want' => array('fancybox'),
	),
	array(
		'name' => 'zoomify 开（应顶掉 fancybox）+ 取色器 关',
		'opts' => array('argon_enable_fancybox' => 'true', 'argon_enable_zoomify' => 'true', 'argon_show_customize_theme_color_picker' => 'false'),
		'want' => array(),
	),
);

$fail = 0;
foreach ($cases as $c) {
	$got = run_case($c['opts']);
	$keys = $got === null ? array() : array_keys($got);
	sort($keys);
	$want = $c['want'];
	sort($want);
	$ok = ($keys === $want);

	// URL 必须走 assets_path 且带 ?ver=
	$urlOk = true;
	$urlNote = array();
	foreach ((array)$got as $name => $spec) {
		foreach (array('js', 'css') as $kind) {
			if (empty($spec[$kind])) continue;
			foreach ($spec[$kind] as $u) {
				$hasBase = strpos($u, $GLOBALS['assets_path']) === 0;
				$hasVer  = strpos($u, '?ver=' . $GLOBALS['theme_version']) !== false;
				if (!$hasBase || !$hasVer) {
					$urlOk = false;
					$urlNote[] = $name . '.' . $kind . ' -> ' . $u;
				}
			}
		}
	}

	printf("  %s %s  键=[%s]%s\n",
		($ok && $urlOk) ? 'PASS' : 'FAIL',
		str_pad($c['name'], 42),
		implode(', ', $keys),
		$ok ? '' : '  期望=[' . implode(', ', $want) . ']'
	);
	if (!$urlOk) {
		foreach ($urlNote as $n) { echo "         URL 不合规: $n\n"; }
	}
	if (!$ok || !$urlOk) { $fail++; }
}

echo "\n";
if ($fail === 0) {
	echo "=== 全部通过：按需加载清单随选项正确变化，且 URL 均走 assets_path + ?ver= ===\n";
	exit(0);
}
echo "=== $fail 项失败 ===\n";
exit(1);
