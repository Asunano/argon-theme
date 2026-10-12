#!/usr/bin/env node
/**
 * strip-dead-css.js —— 从 argon.min.css 与 argon_css_merged.css 中剔除可证死 CSS 块
 *
 * 设计要点：
 *   1. 按【选择器粒度】剔除：形如 `.card-profile,.btn-facebook{...}` 的规则只删掉
 *      匹配的那一项，保留其余；选择器列表被删空时才移除整条规则。
 *      这样不会因为某条规则里混了一个无关选择器就误伤别的样式。
 *   2. 只做【字节级剪切】，其余内容原样保留，不做重新序列化 —— 避免格式化差异。
 *   3. 递归进入 @media / @supports；@font-face / @keyframes 不参与匹配。
 *   4. 幂等：已剔除过的文件再跑不会继续变短。
 *
 * 用法:
 *   node strip-dead-css.js --report     # 只统计，不写入
 *   node strip-dead-css.js              # 写入
 */
'use strict';

const fs = require('fs');
const path = require('path');

const THEME = path.resolve(__dirname, '..', '..');
const TARGETS = [
  path.join(THEME, 'assets', 'css', 'argon.min.css'),
  path.join(THEME, 'assets', 'argon_css_merged.css'),
];

// 已核实「主题源码 0 引用 + 线上 5 种页面 HTML 0 出现」的死 CSS 选择器前缀。
// 每一项都必须先跑 tools/perf/consumers.js 确认无消费方，再往这里加。
const DEAD = [
  // Bootstrap Popover：仅被 Argon 演示 JS 引用，但主题不产出 [data-toggle="popover"]，
  // 且打包的 bootstrap.min.js 未导出 Popover 插件，永远不会初始化
  'popover', 'bs-popover',
  // Bootstrap Carousel：演示 JS 完全未引用，bundle 也未导出
  'carousel',
  // bootstrap-datepicker：主题不产出 .datepicker，插件也未打包
  'datepicker',
  // Argon 设计系统的社交登录按钮配色（.btn-facebook 等）
  'btn-facebook', 'btn-twitter', 'btn-google-plus', 'btn-instagram', 'btn-pinterest',
  'btn-youtube', 'btn-slack', 'btn-dribbble', 'btn-github', 'btn-vimeo', 'btn-tumblr',
  // Argon 设计系统官网的展示型区块，主题从未使用
  'device-ill', 'floating-cards', 'section-components', 'stars-and-coded',
  'profile-page', 'card-profile', 'timeline-ill', 'blurable',
  'section-hero', 'section-profile-cover', 'section-nucleo-icons',
];

// 明确不能碰的（即使命中前缀也要保留）
const KEEP = ['icon-shape', 'icon'];

const deadRe = DEAD.map((d) => new RegExp('^[.#]?' + d.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?![\\w-])'));
const keepRe = KEEP.map((k) => new RegExp('^[.#]?' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?![\\w-])'));

/** 该选择器是否属于要剔除的块 */
function isDead(sel) {
  const s = sel.trim();
  if (keepRe.some((r) => r.test(s))) return false;
  return deadRe.some((r) => r.test(s));
}

/** 把选择器列表按顶层逗号切开（忽略括号/引号内的逗号） */
function splitSelectors(sel) {
  const out = [];
  let cur = '', depth = 0, q = null;
  for (let i = 0; i < sel.length; i++) {
    const c = sel[i];
    if (q) { cur += c; if (c === '\\') { cur += sel[++i] || ''; continue; } if (c === q) q = null; continue; }
    if (c === '"' || c === "'") { q = c; cur += c; continue; }
    if (c === '(' || c === '[') depth++;
    else if (c === ')' || c === ']') depth--;
    if (c === ',' && depth === 0) { out.push(cur); cur = ''; continue; }
    cur += c;
  }
  if (cur.trim()) out.push(cur);
  return out;
}

/** 递归处理一段 CSS，返回处理后的文本与统计 */
function processCss(css, stats, depth) {
  let out = '';
  let i = 0;
  while (i < css.length) {
    // 注释原样保留
    if (css.startsWith('/*', i)) {
      const e = css.indexOf('*/', i + 2);
      const stop = e < 0 ? css.length : e + 2;
      out += css.slice(i, stop);
      i = stop;
      continue;
    }
    const brace = css.indexOf('{', i);
    if (brace < 0) { out += css.slice(i); break; }
    const sel = css.slice(i, brace);
    // 找配对 '}'
    let d = 1, j = brace + 1, q = null;
    for (; j < css.length; j++) {
      const c = css[j];
      if (q) { if (c === '\\') { j++; continue; } if (c === q) q = null; continue; }
      if (c === '"' || c === "'") { q = c; continue; }
      if (c === '{') d++;
      else if (c === '}') { d--; if (d === 0) break; }
    }
    const body = css.slice(brace + 1, j);
    const trimmed = sel.trim();
    const isAt = trimmed.startsWith('@');

    if (isAt && /^@(media|supports|document|layer|container)\b/i.test(trimmed)) {
      // 递归进入条件规则组
      const before = stats.bytes;
      const inner = processCss(body, stats, depth + 1);
      if (inner.trim() === '') {
        stats.rulesDropped++;
        stats.bytes += sel.length + 2;
      } else {
        out += sel + '{' + inner + '}';
      }
    } else if (isAt) {
      out += sel + '{' + body + '}';          // @font-face / @keyframes 等原样保留
    } else {
      const parts = splitSelectors(sel);
      const kept = parts.filter((p) => !isDead(p));
      if (kept.length === 0) {
        stats.rulesDropped++;
        stats.bytes += sel.length + body.length + 2;
      } else if (kept.length < parts.length) {
        stats.selectorsDropped += parts.length - kept.length;
        stats.bytes += parts.filter(isDead).join(',').length;
        // 保留缩进风格：用逗号拼接，去掉被删项
        out += kept.map((k) => k.trim()).join(',') + '{' + body + '}';
      } else {
        out += sel + '{' + body + '}';
      }
    }
    i = j + 1;
  }
  return out;
}

function run(file, report) {
  const orig = fs.readFileSync(file, 'utf8');
  const stats = { rulesDropped: 0, selectorsDropped: 0, bytes: 0 };
  const out = processCss(orig, stats, 0);
  const saved = Buffer.byteLength(orig, 'utf8') - Buffer.byteLength(out, 'utf8');
  console.log('  ' + path.relative(THEME, file));
  console.log('    ' + (Buffer.byteLength(orig, 'utf8') / 1024).toFixed(1) + ' KB -> ' +
    (Buffer.byteLength(out, 'utf8') / 1024).toFixed(1) + ' KB   省 ' + (saved / 1024).toFixed(1) + ' KB');
  console.log('    移除规则 ' + stats.rulesDropped + ' 条，移除选择器 ' + stats.selectorsDropped + ' 项');
  if (!report && saved !== 0) fs.writeFileSync(file, out, 'utf8');
  return saved;
}

const report = process.argv.includes('--report');
console.log('剔除可证死 CSS' + (report ? '（仅统计）' : '') + ':');
let total = 0;
for (const f of TARGETS) total += run(f, report);
console.log('');
console.log('合计节省: ' + (total / 1024).toFixed(1) + ' KB');
if (report) console.log('（--report 模式，未写入）');
