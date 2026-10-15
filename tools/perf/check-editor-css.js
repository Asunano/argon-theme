#!/usr/bin/env node
/**
 * check-editor-css.js —— 核对 gutenberg 产物与源码是否对得上（不需要 npm install）
 *
 * 背景：`gutenberg/dist/` 是构建产物，源码在 `gutenberg/src/`。
 * 只改源码不重新构建（或反过来），产物就会与源码脱节 —— 这在 PR 里很难看出来，
 * 历史上也真的踩过：旧构建链（cgb-scripts，2017 年的 webpack3 + node-sass）
 * 本地与 CI 都装不上，CI 那步又写着 continue-on-error，于是构建静默失败、
 * 产物一直是 2020 年那版。
 *
 * 本脚本做**结构性核对**（不做完整编译 —— 完整构建由 CI 与
 * tools/gutenberg/build.ps1 负责，构建后还会检查 git 里有没有未提交的产物改动）：
 *
 *   1. 产物文件都在：index.js / index.css / index.asset.php
 *   2. src/index.js 里 import 了每个区块，且区块目录与源文件都存在
 *   3. 产物 index.js 里能找到每个区块的注册名（argon/xxx）
 *   4. index.asset.php 的依赖列表合理
 *   5. 源码里不该再有 JSX 的 class= 与字符串 style（React 会忽略 / 直接抛异常）
 *
 * 用法: node tools/perf/check-editor-css.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const THEME = path.resolve(__dirname, '..', '..');
const SRC = path.join(THEME, 'gutenberg', 'src');
const DIST = path.join(THEME, 'gutenberg', 'dist');

const BLOCKS = [
	'alert',
	'admonition',
	'collapse',
	'github',
	'progressbar',
	'tabpanel',
	'timeline',
	'todolist',
];

let fail = 0;
function chk(label, ok, note) {
	console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${label}${ok ? '' : '  ' + (note || '')}`);
	if (!ok) {
		fail++;
	}
}

console.log('=== gutenberg 产物核对 ===');

// 1. 产物文件
for (const f of ['index.js', 'index.css', 'index.asset.php']) {
	chk(`产物存在：dist/${f}`, fs.existsSync(path.join(DIST, f)));
}
if (fail) {
	console.log('');
	console.log('产物缺失 —— 请先跑 `cd gutenberg && npm run build`（或 tools/gutenberg/build.ps1）');
	process.exit(1);
}

// 2. 入口与区块源文件
const entryPath = path.join(SRC, 'index.js');
chk('入口存在：src/index.js', fs.existsSync(entryPath));
const entry = fs.existsSync(entryPath) ? fs.readFileSync(entryPath, 'utf8') : '';

for (const b of BLOCKS) {
	chk(`区块源文件存在：src/${b}/${b}.js`, fs.existsSync(path.join(SRC, b, `${b}.js`)));
	chk(
		`入口 import 了 ${b}`,
		new RegExp(`import\\s+['"]\\./${b}/${b}\\.js['"]`).test(entry)
	);
}

// 3. 产物里的注册名
const distJs = fs.readFileSync(path.join(DIST, 'index.js'), 'utf8');
for (const b of BLOCKS) {
	chk(`产物里有注册名 argon/${b}`, distJs.includes(`argon/${b}`));
}

// 4. asset.php
const assetRaw = fs.readFileSync(path.join(DIST, 'index.asset.php'), 'utf8');
const depMatch = assetRaw.match(/'dependencies'\s*=>\s*array\(([^)]*)\)/);
const deps = depMatch
	? depMatch[1]
			.split(',')
			.map((s) => s.trim().replace(/^'|'$/g, ''))
			.filter(Boolean)
	: [];
chk('asset.php 有依赖列表', deps.length > 0);
chk('依赖里有 wp-block-editor', deps.includes('wp-block-editor'), deps.join(', '));
chk('依赖里有 wp-components', deps.includes('wp-components'), deps.join(', '));
chk('依赖里没有已废弃的 wp-editor', !deps.includes('wp-editor'), deps.join(', '));
chk('asset.php 有 version', /'version'\s*=>/.test(assetRaw));

// 5. 源码里的 JSX 写法
// 先剥掉注释再检查 —— 否则「短代码用的是 <div class='alert-primary'>」这类
// 说明性注释会被当成真的 JSX class= 误报。
function stripComments(text) {
	return text
		.replace(/\/\*[\s\S]*?\*\//g, '') // 块注释
		.replace(/(^|[^:])\/\/[^\n]*/g, '$1'); // 行注释（避免误伤 https://）
}

let srcBad = 0;
for (const b of BLOCKS) {
	const file = path.join(SRC, b, `${b}.js`);
	if (!fs.existsSync(file)) {
		continue;
	}
	const text = stripComments(fs.readFileSync(file, 'utf8'));
	if (/(?<![a-zA-Z-])class=/.test(text)) {
		console.log(`  FAIL  src/${b}/${b}.js 里还有 JSX 的 class=`);
		fail++;
		srcBad++;
	}
	if (/style="/.test(text)) {
		console.log(`  FAIL  src/${b}/${b}.js 里还有字符串 style="…"（React 会抛异常）`);
		fail++;
		srcBad++;
	}
}
if (srcBad === 0) {
	chk('源码里没有 JSX class= 与字符串 style', true);
}

console.log('');
console.log(fail === 0 ? '=== 全部通过 ===' : `=== ${fail} 项失败 ===`);
process.exit(fail === 0 ? 0 : 1);
