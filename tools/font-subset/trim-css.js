#!/usr/bin/env node
/**
 * trim-css.js —— 按「用到的图标类名」裁剪 Font Awesome 6 的 CSS
 *
 * 把 all.min.css + v4-shims.min.css 合并成一个只含所需图标定义的文件。
 *
 * 为什么安全：字体已经子集化到同一份类名清单，不在清单里的图标本来就没有字形
 * （渲染为空白），所以裁掉它们的 CSS 不会引入任何新的可见变化。
 *
 * 保留策略：
 *   - @font-face / @keyframes / 许可注释：全部保留
 *   - 不含图标类的规则（基类 .fa、工具类 .fa-1x/.fa-fw/.fa-ul/.fa-spin 等）：保留
 *   - 含图标类的规则：只要其中任一图标类在清单里就保留（否则丢弃）
 *     这条同时覆盖 v4-shims 里 `.fa.fa-clock-o{font-family:...}` 这类「字体族/字重覆盖」规则
 *
 * 用法：
 *   node trim-css.js [--classes classes.txt] [--out ../../assets/css/argon-fontawesome.css]
 */
'use strict';

const fs = require('fs');
const path = require('path');

const argv = process.argv.slice(2);
const arg = (n, d) => { const i = argv.indexOf('--' + n); return i >= 0 && argv[i + 1] ? argv[i + 1] : d; };

const HERE = __dirname;
const THEME = path.resolve(HERE, '..', '..');
// 输入：FA6 官方的 all.min.css + v4-shims.min.css。
// 这两个文件已不随主题发布（主题只加载裁剪产物 argon-fontawesome.css），
// 故移入 original/ 作为构建输入，避免 127.6KB 死重量打进发布包。
const VENDOR = path.resolve(arg('vendor', path.join(HERE, 'original')));
const CLASSES = path.resolve(arg('classes', path.join(HERE, 'classes.txt')));
const OUT = path.resolve(arg('out', path.join(THEME, 'assets', 'css', 'argon-fontawesome.css')));

// 字体 URL 需要从 assets/css/ 指回 vendor 目录
const URL_FIX = [['url(../webfonts/', 'url(../vendor/font-awesome6/webfonts/']];

const used = new Set(fs.readFileSync(CLASSES, 'utf8').split(/\r?\n/).map((s) => s.trim()).filter(Boolean));

/** 逐字符解析，保留原始文本片段（含 @media/@keyframes 整体） */
function splitTopLevel(css) {
  const out = []; // {text, sel, body, at}
  let i = 0;
  while (i < css.length) {
    // 跳过空白
    while (i < css.length && /\s/.test(css[i])) i++;
    if (i >= css.length) break;
    if (css.startsWith('/*', i)) {
      const end = css.indexOf('*/', i + 2);
      const stop = end < 0 ? css.length : end + 2;
      out.push({ text: css.slice(i, stop), comment: true });
      i = stop;
      continue;
    }
    // 读选择器直到 '{'
    const brace = css.indexOf('{', i);
    if (brace < 0) { out.push({ text: css.slice(i), comment: true }); break; }
    const sel = css.slice(i, brace).trim();
    // 找配对 '}'
    let d = 1, j = brace + 1, inStr = false, q = '';
    for (; j < css.length; j++) {
      const c = css[j];
      if (inStr) { if (c === '\\') { j++; continue; } if (c === q) inStr = false; continue; }
      if (c === '"' || c === "'") { inStr = true; q = c; continue; }
      if (c === '{') d++;
      else if (c === '}') { d--; if (d === 0) break; }
    }
    const body = css.slice(brace + 1, j);
    out.push({ text: css.slice(i, j + 1), sel, body, at: sel.startsWith('@') });
    i = j + 1;
  }
  return out;
}

const faClassesIn = (sel) => {
  const s = new Set();
  for (const m of sel.matchAll(/\.(fa[a-z]?-[\w-]+)/g)) s.add(m[1]);
  return s;
};

// ── 载入两个 vendor CSS ───────────────────────────────────────────────────
const srcs = [
  { name: 'all.min.css', css: fs.readFileSync(path.join(VENDOR, 'all.min.css'), 'utf8') },
  { name: 'v4-shims.min.css', css: fs.readFileSync(path.join(VENDOR, 'v4-shims.min.css'), 'utf8') },
];

// 先收集「所有图标类」= 任一文件里带 content 的规则所涉及的非 base 类
const iconClasses = new Set();
for (const { css } of srcs) {
  for (const r of splitTopLevel(css)) {
    if (r.comment || r.at) continue;
    if (!/content\s*:/.test(r.body || '')) continue;
    for (const c of faClassesIn(r.sel)) iconClasses.add(c);
  }
}

// ── 过滤 ──────────────────────────────────────────────────────────────────
let kept = 0, dropped = 0, keptBytes = 0, droppedBytes = 0;
const parts = [];
const droppedSamples = [];

for (const { name, css } of srcs) {
  for (const r of splitTopLevel(css)) {
    if (r.comment) { parts.push(r.text); continue; }
    if (r.at) { parts.push(r.text); kept++; keptBytes += r.text.length; continue; }
    const cls = faClassesIn(r.sel);
    const iconCls = [...cls].filter((c) => iconClasses.has(c));
    // 不含图标类 -> 保留（基类、工具类）
    // 含图标类 -> 任一被用到即保留
    const keep = iconCls.length === 0 || iconCls.some((c) => used.has(c));
    if (keep) { parts.push(r.text); kept++; keptBytes += r.text.length; }
    else {
      dropped++; droppedBytes += r.text.length;
      if (droppedSamples.length < 8) droppedSamples.push(name + '  ' + r.sel.slice(0, 70));
    }
  }
}

let outCss = parts.join('');
for (const [a, b] of URL_FIX) outCss = outCss.split(a).join(b);

// 清掉 .ttf 回退：主题的 webfonts/ 从未提供 ttf（vendor 自带的悬空引用），
// 且所有目标浏览器都支持 woff2。去掉可减少体积并避免无效请求。
outCss = outCss.replace(/,\s*url\([^)]*\.ttf\)\s*format\((["'])truetype\1\)/gi, '');

fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, outCss, 'utf8');

const origTotal = srcs.reduce((a, s) => a + s.css.length, 0);
console.log('用到的图标类:   ' + used.size);
console.log('FA6 图标类总数: ' + iconClasses.size);
console.log('');
console.log('原始 CSS 合计:  ' + (origTotal / 1024).toFixed(1) + ' KB');
console.log('裁剪后:         ' + (outCss.length / 1024).toFixed(1) + ' KB');
console.log('规则 保留/丢弃: ' + kept + ' / ' + dropped);
console.log('字节 保留/丢弃: ' + (keptBytes / 1024).toFixed(1) + ' KB / ' + (droppedBytes / 1024).toFixed(1) + ' KB');
console.log('');
console.log('丢弃样例:');
for (const s of droppedSamples) console.log('  ' + s);
console.log('');
console.log('已写入: ' + OUT);
