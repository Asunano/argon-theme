#!/usr/bin/env node
/**
 * check-editor-css.js —— 核对 gutenberg 产物与源码是否一致（不需要 npm install）
 *
 * 背景：`gutenberg/dist/blocks.editor.build.css` 是把各区块的 `editor.scss`
 * 编译拼接出来的产物。如果只改产物不改源码（或反过来），下次别人跑
 * `npm run build` 就会把改动打回去，而这一点在 PR 里很难被看出来。
 *
 * 本脚本做**结构性核对**（不做完整编译，那样要装 node_modules）：
 *   1. 每个区块的 editor.scss 首行的 @import 路径
 *   2. 产物里对应行首的 @import 路径
 *   3. 两者必须一致；且「有 @import 的区块集合」必须两边相同
 *
 * 用法: node tools/perf/check-editor-css.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const THEME = path.resolve(__dirname, '..', '..');
const SRC = path.join(THEME, 'gutenberg', 'src');
const DIST = path.join(THEME, 'gutenberg', 'dist', 'blocks.editor.build.css');

const srcImports = {};
for (const block of fs.readdirSync(SRC, { withFileTypes: true })) {
  if (!block.isDirectory()) continue;
  const scss = path.join(SRC, block.name, 'editor.scss');
  if (!fs.existsSync(scss)) continue;
  const first = fs.readFileSync(scss, 'utf8').split('\n')[0].trim();
  const m = first.match(/^@import\s+url\("([^"]+)"\)/);
  srcImports[block.name] = m ? m[1] : null;
}

const distText = fs.readFileSync(DIST, 'utf8');
const distImports = [];
for (const line of distText.split('\n')) {
  const t = line.trim();
  if (!t) continue;
  const m = t.match(/^@import\s+url\("([^"]+)"\)/);
  if (m) distImports.push(m[1]);
}

const srcWith = Object.entries(srcImports).filter(([, v]) => v);
const srcPaths = [...new Set(srcWith.map(([, v]) => v))];
const distPaths = [...new Set(distImports)];

console.log('源码 editor.scss 共 ' + Object.keys(srcImports).length + ' 个区块');
console.log('  其中带 @import 的: ' + srcWith.length + ' 个 -> ' + srcWith.map(([k]) => k).join(', '));
console.log('  @import 路径集合: ' + (srcPaths.join(', ') || '（无）'));
console.log('');
console.log('产物里带 @import 的行: ' + distImports.length + ' 行');
console.log('  @import 路径集合: ' + (distPaths.join(', ') || '（无）'));
console.log('');

let fail = 0;

// 1. 路径集合必须一致
if (srcPaths.length !== distPaths.length || srcPaths.some((p) => !distPaths.includes(p))) {
  console.log('FAIL: 源码与产物的 @import 路径集合不一致');
  console.log('  仅源码有: ' + srcPaths.filter((p) => !distPaths.includes(p)).join(', ') || '（无）');
  console.log('  仅产物有: ' + distPaths.filter((p) => !srcPaths.includes(p)).join(', ') || '（无）');
  fail++;
} else {
  console.log('OK: @import 路径集合一致（' + srcPaths.length + ' 种）');
}

// 2. 行数必须一致
if (srcWith.length !== distImports.length) {
  console.log('FAIL: 带 @import 的区块数 ' + srcWith.length + ' != 产物中带 @import 的行数 ' + distImports.length);
  fail++;
} else {
  console.log('OK: 带 @import 的区块数一致（' + srcWith.length + '）');
}

// 3. 产物里不能还引用已删除的 FA4 资源
if (/vendor\/font-awesome\/css/.test(distText)) {
  console.log('FAIL: 产物仍引用 assets/vendor/font-awesome/css/（FA4 已删除，会 404）');
  fail++;
} else {
  console.log('OK: 产物没有残留 FA4 引用');
}

// 4. 不能引用不存在的文件
for (const p of distPaths) {
  const abs = path.resolve(path.dirname(DIST), p);
  if (!fs.existsSync(abs)) {
    console.log('FAIL: 产物引用了不存在的文件: ' + p);
    fail++;
  } else {
    console.log('OK: 产物引用的 ' + p + ' 存在');
  }
}

console.log('');
console.log(fail === 0
  ? 'PASS：gutenberg 产物与源码结构一致（改动不会在下次 npm run build 时被打回）'
  : 'FAIL：共 ' + fail + ' 项不一致');
process.exit(fail === 0 ? 0 : 1);
