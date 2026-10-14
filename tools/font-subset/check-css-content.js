// 检查主题自有 CSS 里硬编码的 content:"\XXXX" 码点是否都在字体子集内。
//
// 有两类码点会在这里出现：
//   1. 属于 Font Awesome 的（如 style.css 的 \f066 全屏退出图标）—— 必须在子集内
//   2. 属于别的图标字体的（如 argon.min.css 的 \ea26/\ea53，font-family:NucleoIcons）—— 与 FA 无关
// 区分方法：看它是否存在于最终码点表（subset.py 已用原字体 cmap 过滤过）。
const fs = require('fs');
const path = require('path');

const HERE = __dirname;
const THEME = path.resolve(HERE, '..', '..');
const SKIP = new Set(['vendor', 'node_modules', '.git', 'build', 'dist', 'tools']);

function readCps(p) {
  if (!fs.existsSync(p)) return null;
  return new Set(fs.readFileSync(p, 'utf8').split(',').map((s) => parseInt(s.trim().replace(/^U\+/i, ''), 16)).filter(Number.isFinite));
}

const finalPath = path.join(HERE, 'codepoints-final.txt');
const classPath = path.join(HERE, 'codepoints.txt');
const have = readCps(finalPath) || readCps(classPath);
const usingFinal = fs.existsSync(finalPath);

if (!have) {
  console.error('找不到码点表，请先运行 gen-codepoints.js 与 subset.py');
  process.exit(1);
}

const files = [];
(function walk(d) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) { if (!SKIP.has(e.name)) walk(p); }
    else if (e.name.endsWith('.css')) files.push(p);
  }
})(THEME);

const all = new Map();
for (const f of files) {
  const rel = path.relative(THEME, f);
  const lines = fs.readFileSync(f, 'utf8').split(/\r?\n/);
  lines.forEach((line, i) => {
    for (const m of line.matchAll(/content\s*:\s*["']\\([0-9a-fA-F]{4,6})["']/g)) {
      const cp = parseInt(m[1], 16);
      if (!all.has(cp)) all.set(cp, []);
      all.get(cp).push(rel + ':' + (i + 1));
    }
  });
}

const missing = [];
const foreign = [];
for (const [cp, where] of [...all.entries()].sort((a, b) => a[0] - b[0])) {
  if (have.has(cp)) continue;
  // 不在最终码点表里 —— 若它也不在「类名派生表」里，说明它不是 FA 的图标（别的字体）
  const isFaCandidate = !usingFinal || (readCps(classPath) || new Set()).has(cp);
  (isFaCandidate ? missing : foreign).push([cp, where]);
}

console.log('码点表来源: ' + (usingFinal ? 'codepoints-final.txt（含 CSS 并入项）' : 'codepoints.txt（仅类名派生）'));
console.log('扫描 CSS 文件: ' + files.length + ' 个，硬编码 content 码点: ' + all.size + ' 个');
console.log('');

if (foreign.length) {
  console.log('以下码点不属于 Font Awesome（别的图标字体），已正确排除：');
  for (const [cp, where] of foreign) {
    console.log('  U+' + cp.toString(16).toUpperCase().padStart(4, '0') + '  ' + where.slice(0, 2).join('  '));
  }
  console.log('');
}

if (missing.length) {
  console.log('!! 缺失 ' + missing.length + ' 个 Font Awesome 码点，这些图标会渲染为空白：');
  for (const [cp, where] of missing) {
    console.log('  U+' + cp.toString(16).toUpperCase().padStart(4, '0') + '  ' + where.slice(0, 2).join('  '));
  }
  process.exit(1);
}

console.log('全部 Font Awesome 硬编码码点都在子集内。');
