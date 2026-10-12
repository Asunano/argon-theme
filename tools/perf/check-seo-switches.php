<?php
/**
 * check-seo-switches.php —— 验证 Description / Keywords 两个新开关的行为
 *
 * 做法：从 header.php 抽出真实的 SEO 元标签输出块，桩掉 get_option /
 * get_seo_description / get_seo_keywords，eval 后断言 4 种选项组合的输出。
 *
 * 重点断言：
 *   · 两个开关【互不影响】，也【不影响】og:description / twitter:description
 *     （那两个属社交元标签，由 argon_enable_social_meta 管）
 *   · 默认值必须是「开」，保证升级后行为与改动前一致
 *
 * 用法: php tools/perf/check-seo-switches.php
 */

$theme = dirname(__DIR__, 2);
$lines = explode("\n", file_get_contents($theme . '/header.php'));

/* 抽取：从 `$seo_description = get_seo_description();` 到 `$seo_keywords` 那个 if 的结束。
   用「遇到下一个 <link rel="manifest" 或 og:image 之前」太脆，改用固定锚点：
   从 $seo_description 赋值行，到包含 `name="keywords"` 的那个 if 闭合 `<?php } ?>`。 */
$start = null;
foreach ($lines as $i => $l) {
	if (strpos($l, '$seo_description = get_seo_description();') !== false) { $start = $i; break; }
}
if ($start === null) { fwrite(STDERR, "找不到 SEO 输出块起点\n"); exit(1); }

$end = null;
for ($i = $start; $i < count($lines); $i++) {
	if (strpos($lines[$i], 'name="keywords"') !== false) {
		/* 注意：这里【不能】用 // 注释写 PHP 结束标签 —— PHP 的单行注释会在
		   "?>" 处提前结束，那个 "?>" 会真的关掉 PHP 模式，导致后面全变成 HTML 输出。
		   本文件最初就是这么写的，表现为 "Unclosed '{' on line 29"。 */
		for ($j = $i; $j < $i + 3 && $j < count($lines); $j++) {
			if (strpos($lines[$j], '<?php } ?>') !== false) { $end = $j; break 2; }
		}
	}
}
if ($end === null) { fwrite(STDERR, "找不到 SEO 输出块结尾\n"); exit(1); }

$block = implode("\n", array_slice($lines, $start, $end - $start + 1));

/* --dump：打印抽取结果，便于排查锚点失效 */
if (in_array('--dump', $argv, true)) {
	fwrite(STDERR, 'start=' . ($start + 1) . ' end=' . ($end + 1) . ' blockLines=' . substr_count($block, "\n") . "\n");
	fwrite(STDERR, "--- block ---\n" . $block . "\n--- /block ---\n");
}

/* ---- 桩 ---- */
$GLOBALS['__opts'] = array();
function get_option($k, $d = '') {
	return array_key_exists($k, $GLOBALS['__opts']) ? $GLOBALS['__opts'][$k] : $d;
}
function get_seo_description() { return 'DESC-SENTINEL'; }
function get_seo_keywords() { return 'KW-SENTINEL'; }
/* 输出端现在统一走 esc_attr / esc_url（转义已从函数内移到输出端），
   这里给最小实现即可 —— 本测试关心的是「标签是否输出」，不是转义细节。 */
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function run_case($opts) {
	$GLOBALS['__opts'] = $opts;
	ob_start();
	eval($GLOBALS['__block']);
	return ob_get_clean();
}

$GLOBALS['__block'] = $block;

$cases = array(
	array(
		'name' => '两开关都开（默认）',
		'opts' => array(),
		'wantDesc' => true, 'wantKw' => true,
	),
	array(
		'name' => '描述关 / 关键词开',
		'opts' => array('argon_enable_seo_description' => 'false'),
		'wantDesc' => false, 'wantKw' => true,
	),
	array(
		'name' => '描述开 / 关键词关',
		'opts' => array('argon_enable_seo_keywords' => 'false'),
		'wantDesc' => true, 'wantKw' => false,
	),
	array(
		'name' => '两开关都关',
		'opts' => array('argon_enable_seo_description' => 'false', 'argon_enable_seo_keywords' => 'false'),
		'wantDesc' => false, 'wantKw' => false,
	),
);

$fail = 0;
foreach ($cases as $c) {
	$out = run_case($c['opts']);
	$hasDesc = strpos($out, 'name="description"') !== false;
	$hasKw   = strpos($out, 'name="keywords"') !== false;
	// og/twitter description 必须【始终存在】（只要描述非空）—— 它们不受这两个开关影响
	$hasOg   = strpos($out, 'property="og:description"') !== false;
	$hasTw   = strpos($out, 'name="twitter:description"') !== false;

	$ok = ($hasDesc === $c['wantDesc']) && ($hasKw === $c['wantKw']) && $hasOg && $hasTw;
	printf("  %s %-24s description=%s keywords=%s og:description=%s twitter:description=%s\n",
		$ok ? 'PASS' : 'FAIL', $c['name'],
		$hasDesc ? '有' : '无', $hasKw ? '有' : '无',
		$hasOg ? '有' : '无', $hasTw ? '有' : '无');
	if (!$ok) {
		printf("       期望 description=%s keywords=%s，且 og/twitter description 恒为「有」\n",
			$c['wantDesc'] ? '有' : '无', $c['wantKw'] ? '有' : '无');
		$fail++;
	}
}

echo "\n";
if ($fail === 0) {
	echo "=== 全部通过：两个开关互不影响，且不影响 og/twitter description；默认均为「开」 ===\n";
	exit(0);
}
echo "=== $fail 项失败 ===\n";
exit(1);
