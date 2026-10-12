<?php
/**
 * check-option-cache.php —— 验证 argon_get_option() 的请求内缓存不会「串默认值」
 *
 * 背景：该函数按选项名缓存，而 get_option($option, $default) 在选项【不存在】时
 * 返回的就是 $default —— 结果依赖默认值。原实现靠「值 ≠ 默认值才写缓存」规避，
 * 代价是未设置的选项永远不缓存；现改为把默认值编进缓存 key，两者兼得。
 *
 * 本测试断言的是那个不变量：
 *   同一个未设置的选项，用不同默认值查询时，各自拿到自己的默认值。
 *
 * 做法：从 functions.php 抽出真实的 argon_get_option() 源码并 eval，
 * 桩掉 get_option()（可控返回值 + 统计调用次数）。
 *
 * 用法: php tools/perf/check-option-cache.php
 */

$theme = dirname(__DIR__, 2);
$src = file_get_contents($theme . '/functions.php');

/* 抽出 argon_get_option 的完整定义（从 function 行到其匹配的收尾 '}'） */
$start = strpos($src, 'function argon_get_option(');
if ($start === false) { fwrite(STDERR, "找不到 argon_get_option 定义\n"); exit(1); }
$depth = 0; $end = null;
for ($i = $start; $i < strlen($src); $i++) {
	if ($src[$i] === '{') { $depth++; }
	elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
}
if ($end === null) { fwrite(STDERR, "找不到函数结尾\n"); exit(1); }
$fn = substr($src, $start, $end - $start + 1);

/* ---- 桩：可控的 get_option + 调用计数 ---- */
$GLOBALS['__store'] = array();   // 模拟数据库
$GLOBALS['__calls'] = 0;
function get_option($k, $d = false) {
	$GLOBALS['__calls']++;
	return array_key_exists($k, $GLOBALS['__store']) ? $GLOBALS['__store'][$k] : $d;
}

eval($fn);

$fail = 0;
function chk($label, $cond, $note = '') {
	global $fail;
	printf("  %s %s%s\n", $cond ? 'PASS' : 'FAIL', str_pad($label, 52), $cond ? '' : '  ' . $note);
	if (!$cond) { $fail++; }
}

/* 每次用例前重置静态缓存：PHP 没有公开 API，改用「新进程」太慢，
   这里靠一个技巧 —— argon_get_option 的 static 变量在函数内，
   无法从外部重置。因此把三个用例放在同一进程里，
   靠【不同的选项名】来隔离缓存。 */

echo "=== 用例 1：选项【未设置】时，不同默认值互不污染（核心不变量） ===\n";
$a = argon_get_option('opt_unset_a', 'DEFAULT-A');
$b = argon_get_option('opt_unset_a', '');
$c = argon_get_option('opt_unset_a', false);
chk('默认值 DEFAULT-A 得到 DEFAULT-A', $a === 'DEFAULT-A', var_export($a, true));
chk('默认值 "" 得到 ""', $b === '', var_export($b, true));
chk('默认值 false 得到 false', $c === false, var_export($c, true));

echo "\n=== 用例 2：选项【已设置】时，无论默认值是什么都返回库里的值 ===\n";
$GLOBALS['__store']['opt_set_b'] = 'STORED';
$d = argon_get_option('opt_set_b', 'DEFAULT-X');
$e = argon_get_option('opt_set_b', 'DEFAULT-Y');
chk('默认值 DEFAULT-X 得到 STORED', $d === 'STORED', var_export($d, true));
chk('默认值 DEFAULT-Y 也得到 STORED', $e === 'STORED', var_export($e, true));

echo "\n=== 用例 3：真正 memoize（未设置的选项也要缓存，且同 key 只查一次） ===\n";
$GLOBALS['__calls'] = 0;
for ($i = 0; $i < 5; $i++) { argon_get_option('opt_memo_c', 'D'); }
$n1 = $GLOBALS['__calls'];
chk('未设置选项重复 5 次只查 1 次库', $n1 === 1, "实际 {$n1} 次");
$GLOBALS['__calls'] = 0;
for ($i = 0; $i < 5; $i++) { argon_get_option('opt_memo_c', 'E'); }   // 不同默认值 = 不同 key
$n2 = $GLOBALS['__calls'];
chk('换默认值后是新 key，再查 1 次', $n2 === 1, "实际 {$n2} 次");
$GLOBALS['__calls'] = 0;
for ($i = 0; $i < 5; $i++) { argon_get_option('opt_memo_c', 'D'); }   // 回到原默认值
$n3 = $GLOBALS['__calls'];
chk('回到原默认值命中旧 key，0 次查询', $n3 === 0, "实际 {$n3} 次");

echo "\n=== 用例 4：真实场景 —— argon_gravatar_cdn 的两处不同默认值 ===\n";
/* functions.php:3048 用 '' 问「用户设了吗」，:3039 用 'gravatar.pho.ink/avatar/' 问「用哪个值」。
   选项未设置时，两者必须各自拿到自己的默认值，否则头像 URL 会被拼成 "///"。 */
$registered = (argon_get_option('argon_gravatar_cdn_test', '') != '');
$cdn = argon_get_option('argon_gravatar_cdn_test', 'gravatar.pho.ink/avatar/');
chk('「设了吗」判定为否（不注册过滤器）', $registered === false);
chk('「用哪个值」拿到 pho.ink 默认值', $cdn === 'gravatar.pho.ink/avatar/', var_export($cdn, true));

echo "\n";
if ($fail === 0) {
	echo "=== 全部通过：缓存不串默认值，且未设置的选项也能被 memoize ===\n";
	exit(0);
}
echo "=== $fail 项失败 ===\n";
exit(1);
