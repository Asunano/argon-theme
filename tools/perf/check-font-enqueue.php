<?php
/**
 * check-font-enqueue.php —— 网页字体入队的条件分支验证（F1）
 *
 * 背景：衬线模式（argon_font = serif）需要 Noto Serif SC，但测试站跑的是非衬线，
 * 没法远程改选项，所以那一支从未真实跑过。
 *
 * 做法：从 header.php 里【程序化抽取】字体入队那几行（而不是复制一份，
 * 避免测试与实现脱节），用桩函数替换 WP 依赖，然后跑遍选项组合，断言入队结果。
 *
 * 用法: php tools/perf/check-font-enqueue.php
 */

$THEME = dirname(__DIR__, 2);
$header = file_get_contents($THEME . '/header.php');
$lines = explode("\n", $header);

/* ---- 抽取：从 font-awesome-subset 入队 到 googlefont 那层 if 的闭合大括号 ---- */
$start = null;
$end = null;
foreach ($lines as $i => $l) {
    if ($start === null && strpos($l, 'font-awesome-subset') !== false) $start = $i;
    if ($start !== null && $end === null && strpos($l, '"googlefont"') !== false) {
        // googlefont 入队在 if (...) { ... } 内部，必须把闭合括号一起取上，
        // 否则 eval 会报 Unclosed '{'
        for ($j = $i + 1; $j < count($lines); $j++) {
            if (trim($lines[$j]) === '}') { $end = $j; break; }   // trim：行首有缩进
        }
    }
}
if ($start === null || $end === null) {
    fwrite(STDERR, "无法从 header.php 定位字体入队代码\n");
    exit(2);
}
$block = implode("\n", array_slice($lines, $start, $end - $start + 1));

fwrite(STDOUT, "抽取 header.php 第 " . ($start + 1) . "-" . ($end + 1) . " 行（" . ($end - $start + 1) . " 行）\n\n");

/* ---- 桩 ---- */
$GLOBALS['assets_path'] = '/theme';
$GLOBALS['theme_version'] = '1.3.30';
$GLOBALS['__enqueued'] = [];
$GLOBALS['__opt'] = [];

function argon_get_option($k) { return isset($GLOBALS['__opt'][$k]) ? $GLOBALS['__opt'][$k] : ''; }
function get_option($k, $d = false) { return isset($GLOBALS['__opt'][$k]) ? $GLOBALS['__opt'][$k] : $d; }
function wp_enqueue_style($h, $src = '', $dep = null, $ver = null) {
    $GLOBALS['__enqueued'][$h] = $src;
}

/* ---- 跑 4 种组合 ---- */
$cases = [
    ['googlefont 启用 + 非衬线', 'false', ''],
    ['googlefont 启用 + 衬线',   'false', 'serif'],
    ['googlefont 禁用 + 非衬线', 'true',  ''],
    ['googlefont 禁用 + 衬线',   'true',  'serif'],
];

$fails = 0;
foreach ($cases as [$name, $disable, $font]) {
    $GLOBALS['__opt'] = ['argon_disable_googlefont' => $disable, 'argon_font' => $font];
    $GLOBALS['__enqueued'] = [];

    /* 抽取出来的就是纯 PHP 代码（原文件里它位于某个 <?php ?> 段内部），
       直接 eval 即可 —— 不能加 '?>'，那会把 PHP 模式关掉、代码变成原样输出。 */
    eval($block);

    $q = $GLOBALS['__enqueued'];
    $has = fn($h) => isset($q[$h]) ? '有' : '无';

    /* 期望：字体启用且非衬线才下 Open Sans；启用且衬线才下 Noto Serif SC */
    $expectOpenSans = ($disable !== 'true' && $font !== 'serif');
    $expectNotoSerif = ($disable !== 'true' && $font === 'serif');

    $okOpenSans = ($has('argon-fonts') === ($expectOpenSans ? '有' : '无'));
    $okNoto = ($has('googlefont') === ($expectNotoSerif ? '有' : '无'));
    $okFa = ($has('font-awesome-subset') === '有');
    $okStyle = ($has('style') === '有');

    $pass = $okOpenSans && $okNoto && $okFa && $okStyle;
    if (!$pass) $fails++;

    printf("  %s %-26s Open Sans=%-2s Noto=%-2s FA子集=%-2s style=%s\n",
        $pass ? 'PASS' : 'FAIL', $name,
        $has('argon-fonts'), $has('googlefont'), $has('font-awesome-subset'), $has('style'));

    $src = isset($q['argon-fonts']) ? $q['argon-fonts'] : '';
    if ($src) printf("       argon-fonts -> %s\n", $src);
}

echo "\n";
if ($fails === 0) {
    echo "=== 全部通过：4 种选项组合的字体入队行为都符合预期 ===\n";
    echo "\n期望表：\n";
    echo "  | 场景 | 自托管 Open Sans | Noto Serif SC |\n";
    echo "  |---|---|---|\n";
    echo "  | 字体启用 + 非衬线 | 加载 | 不加载 |\n";
    echo "  | 字体启用 + 衬线   | 不加载 | 加载 |\n";
    echo "  | 字体禁用（任意）  | 不加载 | 不加载 |\n";
    exit(0);
}
echo "=== 有 $fails 项不符合预期 ===\n";
exit(1);
