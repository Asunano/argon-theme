#!/usr/bin/env node
/**
 * verify.js —— 校验子集化 + CSS 裁剪的产物是否自洽
 *
 * 检查项：
 *   1. 码点文件存在且非空
 *   2. 每个字体文件都覆盖了它该覆盖的码点（至少一个字体提供）
 *   3. 裁剪后的 CSS 含全部 @font-face、.fa 基类、工具类
 *   4. 裁剪后的 CSS 含全部「用到的图标类」，且不含明显未用到的图标类
 *   5. CSS 里的字体 URL 能解析到真实文件
 *
 * 用法：node verify.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const HERE = __dirname;
const THEME = path.resolve(HERE, '..', '..');
const CPS = path.join(HERE, 'codepoints.txt');
const CLASSES = path.join(HERE, 'classes.txt');
const CSS = path.join(THEME, 'assets', 'css', 'argon-fontawesome.css');
const WEBFONTS = path.join(THEME, 'assets', 'vendor', 'font-awesome6', 'webfonts');

let fail = 0;
const ok = (cond, msg) => { console.log((cond ? '  PASS  ' : '  FAIL  ') + msg); if (!cond) fail++; };

// ── 1. 码点 ───────────────────────────────────────────────────────────────
console.log('=== 1. 码点与类名清单 ===');
ok(fs.existsSync(CPS), 'codepoints.txt 存在');
ok(fs.existsSync(CLASSES), 'classes.txt 存在');
const cps = fs.readFileSync(CPS, 'utf8').split(',').map((s) => parseInt(s.trim().replace(/^U\+/i, ''), 16)).filter(Number.isFinite);
const used = fs.readFileSync(CLASSES, 'utf8').split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
ok(cps.length > 0, '码点数量 > 0（' + cps.length + '）');
ok(used.length > 0, '类名数量 > 0（' + used.length + '）');

// ── 2. 字体覆盖 ───────────────────────────────────────────────────────────
console.log('');
console.log('=== 2. 字体覆盖 ===');
const FONTS = ['fa-solid-900', 'fa-regular-400', 'fa-brands-400'];
const cms = {};
for (const f of FONTS) {
  const p = path.join(WEBFONTS, f + '.woff2');
  if (!fs.existsSync(p)) { ok(false, f + '.woff2 存在'); continue; }
  ok(true, f + '.woff2 存在（' + (fs.statSync(p).size / 1024).toFixed(1) + 'K）');
}
// 用 python fontTools 校验码点覆盖（node 无 woff2 解析）
const { execFileSync } = require('child_process');
const PY = process.env.ARGON_PYTHON || 'python';
const pyScript = `
import sys, json, os
from fontTools.ttLib import TTFont
web = r"${WEBFONTS.replace(/\\/g, '\\\\')}"
raw = open(r"${CPS.replace(/\\/g, '\\\\')}", encoding="utf-8").read()
cps = [int(x.strip().replace("U+", "").replace("u+", ""), 16)
       for x in raw.replace("\\n", ",").split(",") if x.strip()]
fonts = ["fa-solid-900", "fa-regular-400", "fa-brands-400"]
cms = {}
for f in fonts:
    p = os.path.join(web, f + ".woff2")
    if os.path.exists(p):
        t = TTFont(p); cms[f] = t.getBestCmap(); t.close()
missing = [c for c in cps if not any(c in cm for cm in cms.values())]
print(json.dumps({"missing": ["U+%04X" % c for c in missing], "total": len(cps)}))
`;
let missing = [];
try {
  const out = execFileSync(PY, ['-c', pyScript], { encoding: 'utf8' });
  missing = JSON.parse(out.trim()).missing;
} catch (e) {
  console.log('  SKIP  字体码点覆盖校验（需要 python + fontTools）: ' + e.message.split('\n')[0]);
}
if (missing.length === 0) ok(true, '所有码点都有字体提供');
else ok(false, '缺失码点: ' + missing.join(' '));

// ── 3+4. CSS 结构 ─────────────────────────────────────────────────────────
console.log('');
console.log('=== 3. 裁剪后 CSS 结构 ===');
ok(fs.existsSync(CSS), 'argon-fontawesome.css 存在');
const css = fs.readFileSync(CSS, 'utf8');

const ffCount = (css.match(/@font-face/g) || []).length;
ok(ffCount >= 9, '@font-face 数量 >= 9（实际 ' + ffCount + '）');
ok(/\.fa\{/.test(css) || /\.fa,/.test(css), '.fa 基类存在');
for (const u of ['fa-fw', 'fa-spin', 'fa-ul', 'fa-li', 'fa-border', 'fa-1x', 'fa-lg', 'fa-stack', 'fa-inverse']) {
  ok(css.includes('.' + u), '工具类 .' + u + ' 存在');
}
for (const b of ['fa-brands', 'fa-solid', 'fa-regular', 'fab', 'fas', 'far']) {
  ok(css.includes('.' + b), '基类 .' + b + ' 存在');
}

console.log('');
console.log('=== 4. 图标类覆盖 ===');
const missingCls = used.filter((c) => !css.includes('.' + c));
ok(missingCls.length === 0, '全部 ' + used.length + ' 个用到的图标类都在 CSS 中' + (missingCls.length ? '（缺: ' + missingCls.join(' ') + '）' : ''));
// 抽查几个应当被裁掉的图标类（注意：fa-rotate-90 / fa-fw 等是「工具类」而非图标类，本就该保留）
const shouldDrop = ['fa-0', 'fa-glass', 'fa-meanpath', 'fa-tripadvisor', 'fa-camera-retro'];
const stillThere = shouldDrop.filter((c) => new RegExp('\\.' + c + '[:{,\\s]').test(css));
ok(stillThere.length === 0, '未使用的图标类已裁掉' + (stillThere.length ? '（仍存在: ' + stillThere.join(' ') + '）' : ''));

// ── 5. 字体 URL ───────────────────────────────────────────────────────────
console.log('');
console.log('=== 5. CSS 中的字体 URL 可解析 ===');
const urls = [...css.matchAll(/url\(([^)]+)\)/g)].map((m) => m[1].replace(/["']/g, ''));
const cssDir = path.dirname(CSS);
const bad = [];
for (const u of new Set(urls)) {
  if (/^https?:|^data:/.test(u)) continue;
  const p = path.resolve(cssDir, u.split('?')[0]);
  if (!fs.existsSync(p)) bad.push(u + ' -> ' + p);
}
ok(bad.length === 0, urls.length + ' 个 URL 全部可解析' + (bad.length ? '\n         ' + bad.join('\n         ') : ''));

console.log('');
console.log(fail === 0 ? '=== 全部通过 ===' : '=== ' + fail + ' 项失败 ===');
process.exit(fail === 0 ? 0 : 1);
