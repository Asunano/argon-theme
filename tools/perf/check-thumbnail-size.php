<?php
/**
 * check-thumbnail-size.php —— 验证头图改用 large、以及不再生成 srcset 专用尺寸
 *
 * 背景：argon_get_post_thumbnail() 原来取 full，而它服务于卡片/时间线/文章头图/页面头图/
 *       相关文章 —— 最宽只显示约 941px。实测首页 11 张卡片头图合计 6.27 MiB，
 *       其中 #1535 那张是 4000×2333 / 6.01 MiB。
 *       另外主题会移除正文图片的 srcset，所以 WP 生成的 768/1536/2048 三档永不被请求。
 *
 * 用法: php tools/perf/check-thumbnail-size.php
 */

$theme = dirname(__DIR__, 2);
$src = file_get_contents($theme . '/functions.php');

$fail = 0;
function chk($label, $cond, $note = '') {
	global $fail;
	printf("  %s %s%s\n", $cond ? 'PASS' : 'FAIL', str_pad($label, 52), $cond ? '' : '  ' . $note);
	if (!$cond) { $fail++; }
}

echo "=== 1. 静态检查：argon_get_post_thumbnail 取的是 large ===\n";
$i = strpos($src, 'function argon_get_post_thumbnail(');
$j = $i === false ? false : strpos($src, "\n}", $i);
$fn = ($i !== false && $j !== false) ? substr($src, $i, $j - $i) : '';
chk('找得到函数定义', $fn !== '');
chk('取 large 尺寸（或经 \ 参数）', strpos($fn, '"large"') !== false || strpos($fn, '$size') !== false, '未找到 "large" 或 $size');
chk('不再直接取 full', strpos($fn, 'get_post_thumbnail_id($postID), "full")') === false || substr_count($fn, '"full"') <= 1,
	'仍有多处直接取 full');
chk('有 false 兜底（避免附件无图时报警）', strpos($fn, 'if (!$src)') !== false);

echo "\n=== 2. 静态检查：摘掉 srcset 专用的三档尺寸 ===\n";
chk('注册了 intermediate_image_sizes_advanced 过滤器',
	strpos($src, "add_filter('intermediate_image_sizes_advanced'") !== false);
chk('摘掉 medium_large', strpos($src, "'medium_large'") !== false);
chk('摘掉 1536x1536', strpos($src, "'1536x1536'") !== false);
chk('摘掉 2048x2048', strpos($src, "'2048x2048'") !== false);
chk('未动 thumbnail（实时搜索在用）', strpos($src, "unset(\$sizes['thumbnail'])") === false);

echo "\n=== 3. 行为检查：过滤器真的会摘掉那三档 ===\n";
/* 抽出 add_filter 那段并执行（桩掉 add_filter 收集回调） */
$GLOBALS['__filters'] = array();
function add_filter($tag, $cb, $prio = 10, $args = 1) { $GLOBALS['__filters'][$tag][] = $cb; }

$k = strpos($src, "add_filter('intermediate_image_sizes_advanced'");
$k2 = $k === false ? false : strpos($src, '});', $k);
$block = ($k !== false && $k2 !== false) ? substr($src, $k, $k2 - $k + 3) : '';
chk('抽到了过滤器代码', $block !== '');
if ($block !== '') {
	eval($block);
	$cb = $GLOBALS['__filters']['intermediate_image_sizes_advanced'][0] ?? null;
	chk('回调已注册', is_callable($cb));

	/* WordPress 默认会生成的尺寸（5.3+） */
	$sizes = array(
		'thumbnail'    => array(150, 150, true),
		'medium'       => array(300, 300, false),
		'medium_large' => array(768, 0, false),
		'large'        => array(1024, 1024, false),
		'1536x1536'    => array(1536, 1536, false),
		'2048x2048'    => array(2048, 2048, false),
	);
	$out = $cb($sizes);
	printf("    过滤前: %s\n", implode(', ', array_keys($sizes)));
	printf("    过滤后: %s\n", implode(', ', array_keys($out)));
	chk('medium_large 被摘掉', !isset($out['medium_large']));
	chk('1536x1536 被摘掉', !isset($out['1536x1536']));
	chk('2048x2048 被摘掉', !isset($out['2048x2048']));
	chk('thumbnail 保留', isset($out['thumbnail']));
	chk('medium 保留', isset($out['medium']));
	chk('large 保留（头图现在用它）', isset($out['large']));
	chk('没有新增尺寸', count($out) === count($sizes) - 3, '过滤后 ' . count($out) . ' 个');
}

echo "\n";
if ($fail === 0) {
	echo "=== 全部通过 ===\n";
	exit(0);
}
echo "=== $fail 项失败 ===\n";
exit(1);
