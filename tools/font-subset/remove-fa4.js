#!/usr/bin/env node
/**
 * remove-fa4.js —— 从合并 CSS 中剥离 Font Awesome 4 段
 *
 * argon_css_merged.css 的结构是「来源标记行 + 该来源的压缩 CSS」依次拼接：
 *   行 1  /* assets/css/argon.min.css * /
 *   行 2  <argon.min.css 压缩内容>
 *   行 3  /* assets/vendor/font-awesome/css/font-awesome-min.css * /
 *   行 4+ <FA4 压缩内容>
 *
 * FA4 段的作用已被 Font Awesome 6 完全取代（FA6 的 all.min.css 后加载，
 * 同特异性规则后者胜出；实测 FA4 独有且 FA6 未覆盖的类名只有 fa-meanpath /
 * fa-tripadvisor 两个废弃品牌图标，当前 0 使用）。故整段删除。
 *
 * 幂等：已经剥离过则不做任何改动。
 *
 * 用法：node remove-fa4.js [--dry-run]
 */
'use strict';

const fs = require('fs');
const path = require('path');

const THEME = path.resolve(__dirname, '..', '..');
const MERGED = path.join(THEME, 'assets', 'argon_css_merged.css');
const DROP_MARKER = 'assets/vendor/font-awesome/';

const dry = process.argv.includes('--dry-run');

const raw = fs.readFileSync(MERGED, 'utf8');
const before = Buffer.byteLength(raw, 'utf8');

// 按「来源标记行」切段：标记行形如 /* path */
const lines = raw.split('\n');
const segments = []; // {marker, startLine, endLine}
for (let i = 0; i < lines.length; i++) {
  const m = lines[i].match(/^\/\*\s*(.+?)\s*\*\/\s*$/);
  if (m) segments.push({ marker: m[1], start: i, end: lines.length - 1 });
}
for (let i = 0; i < segments.length - 1; i++) segments[i].end = segments[i + 1].start - 1;

const target = segments.find((s) => s.marker.includes(DROP_MARKER));
if (!target) {
  console.log('未找到 FA4 段（可能已剥离）。当前段:');
  for (const s of segments) console.log('  ' + s.marker + '  (行 ' + (s.start + 1) + '-' + (s.end + 1) + ')');
  process.exit(0);
}

const keep = [];
for (let i = 0; i < lines.length; i++) {
  if (i >= target.start && i <= target.end) continue;
  keep.push(lines[i]);
}
const out = keep.join('\n');
const after = Buffer.byteLength(out, 'utf8');

console.log('合并 CSS 段:');
for (const s of segments) {
  const dropped = s === target;
  console.log('  ' + (dropped ? 'DROP' : 'KEEP') + '  ' + s.marker + '  (行 ' + (s.start + 1) + '-' + (s.end + 1) + ')');
}
console.log('');
console.log('剥离段: ' + target.marker);
console.log('体积: ' + (before / 1024).toFixed(1) + ' KB -> ' + (after / 1024).toFixed(1) + ' KB  (省 ' + ((before - after) / 1024).toFixed(1) + ' KB)');

if (dry) {
  console.log('\n--dry-run，未写入。');
} else {
  fs.writeFileSync(MERGED, out, 'utf8');
  console.log('\n已写入: ' + MERGED);
}
