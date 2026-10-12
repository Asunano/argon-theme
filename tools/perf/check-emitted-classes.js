#!/usr/bin/env node
/**
 * check-emitted-classes.js —— 裁剪安全网：PHP 输出的每个类名，CSS 里是否还有规则
 *
 * 为什么需要它：
 *   strip-dead-css.js 按选择器粒度删规则，判断依据是「主题源码 0 引用」。
 *   但线上截图验收只能覆盖测试站上真实存在的页面类型 ——
 *   时间线 / 说说 / 留言板 / 友链这些模板在测试站没有内容，截不到。
 *   本脚本不看渲染结果，直接对「PHP 会输出什么类名」与「CSS 还剩哪些选择器」
 *   做全集比对，因此**不受页面内容限制**，能覆盖所有模板。
 *
 * 判定：
 *   - 逐字节扫描 PHP，取出 class='...' / class="..." 里的字面量记号
 *     （含 PHP 拼接串里被引号夹住的片段）
 *   - 对每个记号，在 style.css + assets/ 下所有 CSS 里找是否存在
 *     形如 .foo / .foo: / .foo, / .foo. / .foo# 的选择器
 *   - 没有对应规则的，列为「无 CSS 的类名」，需要人工判断是否正常
 *
 * 已知正常无规则的情况，用 ALLOW 白名单列出（JS 钩子、WP 核心类等）。
 *
 * 用法:
 *   node check-emitted-classes.js           # 报告
 *   node check-emitted-classes.js --strict  # 有非白名单项则退出码 1
 */
'use strict';

const fs = require('fs');
const path = require('path');

const THEME = path.resolve(__dirname, '..', '..');

const SKIP_DIR = /[\\/](node_modules|vendor|\.git|build|dist|tools|theme-update-checker)[\\/]/;

// 明确允许「有类名但无 CSS」的：JS 钩子、WP 核心输出、语义标记等
const ALLOW = new Set([
  // JS / 行为钩子
  'str', 'row', 'link', 'title', 'card', 'container', 'text', 'icon',
  // WP 核心 / 编辑器输出（样式由 WP 提供，不在主题 CSS 里）
  'wp-block-image', 'wp-block-table', 'wp-block-code', 'wp-block-group',
  'screen-reader-text', 'skip-link', 'no-avatar',
  // 动态拼接产生的中间记号
  'card-body', 'card-header', 'card-footer',
]);

function walk(dir, out = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (SKIP_DIR.test(p + path.sep)) continue;
    if (e.isDirectory()) walk(p, out);
    else if (e.name.endsWith('.php')) out.push(p);
  }
  return out;
}

/** 从 PHP 源码里抽取 class 字面量记号
 *
 * 两个必须做的预处理，否则会把「不是标记的东西」当成类名：
 *   1) 剥离 <?php ... ?> 段 —— 否则 class="<?php if(...) ?>" 会把 if / echo
 *      当成类名抓出来
 *   2) 跳过 preg_replace / preg_match* 所在行 —— 那些行里的 class="next page-numbers"
 *      是【用来匹配并删除】的正则模式，不是主题输出的标记
 */
function extractClasses(src) {
  const found = new Map();   // token -> [file:line]

  const cleaned = src.replace(/<\?(?:php|=)?[\s\S]*?\?>/g, ' ');

  cleaned.split(/\r?\n/).forEach((line, i) => {
    if (/preg_(?:replace|match|match_all|split)\s*\(/.test(line)) return;

    for (const re of [/class='([^']*)'/g, /class="([^"]*)"/g, /class=\\"([^"]*)\\"/g]) {
      re.lastIndex = 0;
      let m;
      while ((m = re.exec(line)) !== null) {
        // PHP 拼接处形如  class='a b" . $x . "c d'，引号会把内容截断，
        // 拿到的片段本身就是干净的记号串，直接按空白切分即可。
        for (const tok of m[1].split(/\s+/)) {
          const t = tok.trim();
          if (!/^[A-Za-z][\w-]*$/.test(t)) continue;   // 丢掉 " . $x . " 之类残留
          if (!found.has(t)) found.set(t, []);
          found.get(t).push(path.basename(name) + ':' + (i + 1));
        }
      }
    }
  });

  return found;
}

let name = '';
function loadCss() {
  const files = [];
  const push = (p) => { if (fs.existsSync(p) && fs.statSync(p).isFile()) files.push(p); };
  push(path.join(THEME, 'style.css'));
  const cssDir = path.join(THEME, 'assets', 'css');
  if (fs.existsSync(cssDir)) {
    for (const f of fs.readdirSync(cssDir)) if (f.endsWith('.css')) push(path.join(cssDir, f));
  }
  // admin.css / login.css 在主题根目录，不在 assets/ 下
  for (const f of ['assets/argon_css_merged.css', 'admin.css', 'login.css']) push(path.join(THEME, f));
  return files.map((f) => ({ file: path.relative(THEME, f), text: fs.readFileSync(f, 'utf8') }));
}

/** 该类名是否作为 class 选择器出现在 CSS 里 */
function hasRule(cssList, cls) {
  const esc = cls.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  // .foo 后面必须跟分隔符，避免 .card 命中 .card-profile
  const re = new RegExp('\\.' + esc + '(?![\\w-])');
  for (const { file, text } of cssList) {
    if (re.test(text)) return file;
  }
  return null;
}

(function main() {
  const strict = process.argv.includes('--strict');
  const phpFiles = walk(THEME);
  const cssList = loadCss();

  const all = new Map();
  const inlineStyles = [];      // PHP 内联 <style> 块也是 CSS 来源
  for (const f of phpFiles) {
    name = f;
    const src = fs.readFileSync(f, 'utf8');
    for (const m of src.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/gi)) {
      inlineStyles.push({ file: path.relative(THEME, f) + ' (inline)', text: m[1] });
    }
    const found = extractClasses(src);
    for (const [tok, locs] of found) {
      if (!all.has(tok)) all.set(tok, []);
      all.get(tok).push(...locs.slice(0, 1));
    }
  }
  cssList.push(...inlineStyles);

  console.log('扫描 PHP 文件: ' + phpFiles.length + ' 个');
  console.log('CSS 文件:      ' + cssList.length + ' 个（' + cssList.map((c) => c.file).join(', ') + '）');
  console.log('PHP 输出的不同类名: ' + all.size + ' 个');
  console.log('');

  const noRule = [];
  const okCount = { n: 0 };
  const withRule = new Set();
  for (const [cls, locs] of [...all].sort()) {
    if (hasRule(cssList, cls)) { okCount.n++; withRule.add(cls); continue; }
    noRule.push([cls, locs]);
  }

  // PHP 拼接会把类名截断，例如
  //   <i class="fa fa-thumbs<?php if (!$upvoted) {?>-o<?php } ?>-up">
  // 剥离 <?php ?> 后只剩 "fa-thumbs"，而真正生效的 fa-thumbs-up / fa-thumbs-o-up
  // 是有规则的。凡「本身无规则、但存在有规则的记号以它为前缀」者，判为截断产物。
  const truncated = [];
  const genuinely = [];
  for (const [cls, locs] of noRule) {
    if (ALLOW.has(cls)) { truncated.push([cls, locs, '白名单']); continue; }
    const longer = [...all.keys()].filter((k) => k !== cls && k.startsWith(cls) && withRule.has(k));
    if (longer.length) truncated.push([cls, locs, '拼接截断 -> ' + longer.slice(0, 3).join(' / ')]);
    else genuinely.push([cls, locs]);
  }

  console.log('有对应 CSS 规则: ' + okCount.n + ' / ' + all.size);
  console.log('');

  if (truncated.length) {
    console.log('=== 无规则但可解释（不算失败）: ' + truncated.length + ' 个 ===');
    for (const [cls, locs, why] of truncated) {
      console.log('  ' + cls.padEnd(30) + ' ' + why);
    }
    console.log('');
  }

  if (genuinely.length === 0) {
    console.log('=== PASS：PHP 输出的类名全部有对应 CSS、或可解释 ===');
  } else {
    console.log('=== 需要人工确认：' + genuinely.length + ' 个类名没有任何 CSS 规则 ===');
    for (const [cls, locs] of genuinely) {
      console.log('  ' + cls.padEnd(34) + ' 出现于 ' + locs.slice(0, 3).join(', '));
    }
    console.log('');
    console.log('（这些未必是错的：可能只是 JS 钩子、WP 核心类、或纯语义标记。');
    console.log('  判断依据：拿 master 的 CSS 比对 —— 若 master 里也没有规则，');
    console.log('  说明它本来就没样式，不是被 strip-dead-css.js 误删的。）');
  }

  // 报告写到 build/（已 gitignore），避免每次跑都脏了工作区
  const outDir = path.join(THEME, 'build');
  fs.mkdirSync(outDir, { recursive: true });
  fs.writeFileSync(
    path.join(outDir, 'emitted-classes-report.json'),
    JSON.stringify({
      total: all.size,
      withRule: okCount.n,
      truncated: truncated.map(([c, , why]) => ({ cls: c, why })),
      noRule: genuinely.map(([c, l]) => ({ cls: c, at: l })),
    }, null, 2)
  );

  process.exit(strict && genuinely.length ? 1 : 0);
})();
