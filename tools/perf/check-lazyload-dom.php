<?php
/**
 * check-lazyload-dom.php —— 验证 argon_lazyload() 的 DOM 分支不再破坏结构
 *
 * 背景：原先的写法
 *   loadHTML(mb_convert_encoding($content,'HTML-ENTITIES','UTF-8'), LIBXML_HTML_NOIMPLIED|…)
 *   … $content = $dom->saveHTML();
 * 有两个问题：
 *   ① LIBXML_HTML_NOIMPLIED 下 libxml 需要唯一文档根，会把【第一个元素】当根，
 *      后续顶层节点全挂成它的子节点 —— 正文以 <h2> 开头时整篇变成一个标题的子节点。
 *   ② HTML-ENTITIES 把中日韩字符变成 &#NNNNN;，体积膨胀 1.3~1.75 倍。
 *
 * 本测试从 functions.php 抽出【真实的 DOM 分支代码】执行（把图片处理那几行原样保留），
 * 断言：结构保持、中文不变实体、图片仍被改成懒加载。
 *
 * 用法: php tools/perf/check-lazyload-dom.php
 */

if (!class_exists('DOMDocument')) {
	fwrite(STDERR, "本机 PHP 缺少 dom 扩展，无法运行\n");
	exit(2);
}

$theme = dirname(__DIR__, 2);
$src = file_get_contents($theme . '/functions.php');

/* 抽出 DOM 分支：从 `$use_dom = class_exists` 到 `$content = $out;` 那段的 else 分支结束。
   直接用「从 $use_dom 赋值行 到 `if (!$use_dom){` 之前」这一段。 */
$start = strpos($src, '$use_dom = class_exists(\'DOMDocument\')');
if ($start === false) { fwrite(STDERR, "找不到 DOM 分支起点\n"); exit(1); }
$endMark = strpos($src, 'if (!$use_dom){', $start);
if ($endMark === false) { fwrite(STDERR, "找不到 DOM 分支结尾\n"); exit(1); }
$block = substr($src, $start, $endMark - $start);

/* 桩：$lazyload_loading_style 与 $content 由用例提供。
   注意：被抽出的代码是【修改 $content 变量】而不是 echo，
   所以返回 $content，不是输出缓冲。 */
/* argon_lazyload() 现在还会给正文图片挂 LQIP 背景（argon_get_lqip_for_img_src），
   这里桩掉它，返回一个可辨识的 data URI，好断言背景确实挂上了。 */
$GLOBALS['__lqip_on'] = true;
function argon_get_img_meta_for_src($src) {
	if (empty($GLOBALS['__lqip_on'])) { return array('', 0, 0); }
	if ($src == '' || strpos($src, 'data:') === 0) { return array('', 0, 0); }
	/* 顺便返回宽高，用来验证「补宽高」那段 */
	return array('data:image/jpeg;base64,FAKELQIP', 1200, 675);
}
function argon_get_lqip_for_img_src($src) { $m = argon_get_img_meta_for_src($src); return $m[0]; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function run_block($content, $style = 'lazyload-style-none') {
	$lazyload_loading_style = $style;
	eval($GLOBALS['__block']);
	return $content;
}

$GLOBALS['__block'] = $block;

$fail = 0;
function chk($label, $cond, $note = '') {
	global $fail;
	printf("  %s %s%s\n", $cond ? 'PASS' : 'FAIL', str_pad($label, 52), $cond ? '' : '  ' . $note);
	if (!$cond) { $fail++; }
}

/* ---------------- 用例 1：结构不被破坏 ---------------- */
echo "=== 1. 结构：第一个元素不得吞掉后面的兄弟节点 ===\n";
$cases = array(
	'以 <h2> 开头'    => array('<h2 class="wp-block-heading">前言</h2><p>第一段</p><p>第二段</p>',
	                        array('<h2 class="wp-block-heading">前言</h2>', '<p>第一段</p>', '<p>第二段</p>')),
	'以 <div> 开头'    => array('<div class="wp-block-image"><img src="a.jpg"></div><p>文字</p>',
	                        array('</div>', '<p>文字</p>')),
	'以 <figure> 开头'  => array('<figure class="wp-block-video"><video></video></figure><p>文字</p>',
	                        array('</figure>', '<p>文字</p>')),
	'以 <p> 开头'      => array('<p>第一段</p><p>第二段</p>',
	                        array('<p>第一段</p>', '<p>第二段</p>')),
);
foreach ($cases as $name => $c) {
	list($input, $mustContain) = $c;
	$out = run_block($input);
	$ok = true;
	foreach ($mustContain as $frag) {
		if (strpos($out, $frag) === false) { $ok = false; break; }
	}
	/* 关键：不能出现「第一个标签把后面全包住」—— 用标签配对位置判断 */
	$firstTag = null;
	if (preg_match('/^\s*<([a-z][a-z0-9]*)\b/i', $out, $m)) { $firstTag = strtolower($m[1]); }
	$closeAt = $firstTag ? strpos($out, '</' . $firstTag . '>') : false;
	$tailLen = ($closeAt !== false) ? strlen(trim(strip_tags(substr($out, $closeAt)))) : 0;
	$notSwallowed = ($closeAt !== false) && $tailLen > 0;

	chk($name . ' 结构保持', $ok, '输出: ' . substr($out, 0, 90));
	chk($name . ' 首元素未吞掉后续', $notSwallowed, "闭标签位置={$closeAt} 之后文字长度={$tailLen}");
}

/* ---------------- 用例 2：中文不被转成实体 ---------------- */
echo "\n=== 2. 中文保持原样（不被转成 &#NNNNN; 实体） ===\n";
$cn = '<p>这是一段中文，用来测试实体转义导致的体积膨胀。</p>';
$out = run_block($cn);
$hasEntity = preg_match('/&#\d+;/', $out) === 1;
$ratio = strlen($out) / strlen($cn);
chk('输出不含数字实体', !$hasEntity, '输出: ' . substr($out, 0, 80));
chk('中文原样保留', strpos($out, '这是一段中文') !== false);
chk('体积未膨胀（< 1.1 倍）', $ratio < 1.1, sprintf('实际 %.2f 倍', $ratio));

/* ---------------- 用例 3：图片懒加载仍然生效 ---------------- */
echo "\n=== 3. 图片仍被改成懒加载（原功能不能丢） ===\n";
$img = '<p>文字</p><img class="wp-image" src="https://example.com/a.jpg" srcset="a.jpg 1x, b.jpg 2x">';
$out = run_block($img);
chk('加上了 lazyload class', strpos($out, 'lazyload') !== false, substr($out, 0, 120));
chk('加上了 data-original', strpos($out, 'data-original="https://example.com/a.jpg"') !== false, substr($out, 0, 120));
chk('移除了 srcset', strpos($out, 'srcset') === false);
chk('src 换成了占位图', strpos($out, 'data:image/svg+xml;base64') !== false);
chk('保留了原有 class', strpos($out, 'wp-image') !== false);
/* 新增：正文图片也挂 LQIP 占位（背景方案，见 functions.php 的 argon_lazyload） */
chk('挂了 LQIP 背景（has-lqip）', strpos($out, 'has-lqip') !== false);
chk('背景是 LQIP data URI', strpos($out, "background-image:url('data:image/jpeg;base64,FAKELQIP')") !== false, substr($out, 0, 200));
chk('背景铺满（background-size:cover）', strpos($out, 'background-size:cover') !== false);
chk('背景居中（background-position:center）', strpos($out, 'background-position:center') !== false);
/* 尺寸锁定：占位阶段就要与最终一致，否则图片一到会跳 */
chk('用 aspect-ratio 锁定真实比例', strpos($out, 'aspect-ratio:1200 / 675') !== false, substr($out, 0, 240));
  /* 尺寸锁定：占位阶段就要与最终一致，否则图片一到会跳。
     注意不能写 width:min(100%,Npx) —— min() 里的百分比在 shrink-to-fit 上下文
     （正文图常被放在这样的父元素里）是「不确定的」，min() 会退化成 auto，
     而替换元素的 auto 宽度就是占位图的内在宽度 1px，图一到就跳。
     所以拆成 width:100% + max-width:Npx：百分比有确定基准，效果一样。 */
  chk('宽度用 width:100% + max-width 锁定', strpos($out, 'width:100%;max-width:1200px') !== false, substr($out, 0, 240));
  chk('没有用会退化的 min(100%,Npx)', strpos($out, 'width:min(100%') === false);

echo "\n=== 3b. 拿不到 LQIP 时退回 no-lqip（中性底色兜底） ===\n";
$GLOBALS['__lqip_on'] = false;
$outNo = run_block('<p>文字</p><img class="wp-image" src="https://example.com/a.jpg">');
chk('退回 no-lqip', strpos($outNo, 'no-lqip') !== false, substr($outNo, 0, 160));
chk('不挂背景图', strpos($outNo, 'background-image:url(') === false);
chk('懒加载标记仍在', strpos($outNo, 'lazyload') !== false);
$GLOBALS['__lqip_on'] = true;

echo "\n=== 4. data-full-url 优先于 src ===\n";
$img2 = '<img data-full-url="https://example.com/full.jpg" src="https://example.com/thumb.jpg">';
$out2 = run_block($img2);
chk('data-original 取 data-full-url', strpos($out2, 'data-original="https://example.com/full.jpg"') !== false, substr($out2, 0, 140));

echo "\n=== 5. 临时根不残留 ===\n";
$out3 = run_block('<p>甲</p><p>乙</p>');
chk('输出里没有 argon-lazyload-root', strpos($out3, 'argon-lazyload-root') === false, substr($out3, 0, 120));
chk('输出里没有 <div> 包裹层', strpos($out3, '<div') === false, substr($out3, 0, 120));

echo "\n=== 6. 空内容 / 异常输入不崩 ===\n";
$out4 = run_block('');
chk('空内容不报错', is_string($out4));
$out5 = run_block('<p>未闭合的段落');
chk('未闭合标签不报错', is_string($out5) && strpos($out5, '未闭合的段落') !== false, substr($out5, 0, 80));

echo "\n";
if ($fail === 0) {
	echo "=== 全部通过 ===\n";
	exit(0);
}
echo "=== $fail 项失败 ===\n";
exit(1);
