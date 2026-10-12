#!/usr/bin/env node
/**
 * gen-codepoints.js —— 生成 Font Awesome 子集化所需的码点集
 *
 * 用法：
 *   node gen-codepoints.js [--site https://blog.losn.cc] [--out codepoints.txt] [--theme ..]
 *
 * 数据来源（四路并集，缺一不可）：
 *   1. 主题源码           *.php / template-parts/**.php / *.js（排除 vendor 与已合并产物）
 *   2. 源码字符串字面量   覆盖 `$htmlclasses .= 'xxx '` 这类 PHP 拼接，避免漏判
 *   3. WordPress REST API 全部文章 + 页面正文（content.rendered）—— 正文里的 <i class="fa fa-xxx">
 *   4. 主题自带的 FA4 兼容层（v4-shims）中所有会被用到的别名
 *
 * 为什么必须联网取正文：仅扫描主题源码会漏掉只在文章正文里出现的图标。
 * 实测生产站 22 篇文章里有 16 个图标类名是源码中不存在的，漏掉会让这些图标变空白。
 */
'use strict';

const fs = require('fs');
const path = require('path');

// ── 参数 ──────────────────────────────────────────────────────────────────
const argv = process.argv.slice(2);
const arg = (name, def) => {
  const i = argv.indexOf('--' + name);
  return i >= 0 && argv[i + 1] ? argv[i + 1] : def;
};
const THEME = path.resolve(arg('theme', path.join(__dirname, '..', '..')));
const OUT = path.resolve(arg('out', path.join(__dirname, 'codepoints.txt')));
const SITE = arg('site', '');
const UA = 'Mozilla/5.0 (compatible; argon-font-subset/1.0)';

// 这些类名 FA6 用 ASCII 字符渲染（如 fa-exclamation -> "\21"），
// 但主题历史上用 FA4 的 PUA 字形。为了让删除 FA4 后外观完全不变，
// 我们在 style.css 里显式覆盖回 PUA 码点，这里把码点一并纳入子集。
const PUA_OVERRIDES = {
  'fa-exclamation': 0xf12a,
};

// ── 工具 ──────────────────────────────────────────────────────────────────
function walk(dir, exts, skipDirs) {
  const out = [];
  (function rec(d) {
    let entries;
    try { entries = fs.readdirSync(d, { withFileTypes: true }); } catch (e) { return; }
    for (const e of entries) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) {
        if (skipDirs.has(e.name)) continue;
        rec(p);
      } else if (exts.has(path.extname(e.name).toLowerCase())) {
        out.push(p);
      }
    }
  })(dir);
  return out;
}

/** 逐字符解析 CSS，只取顶层规则（跳过 @media / @keyframes 内部） */
function parseCssRules(css) {
  const rules = [];
  let depth = 0, buf = '', inStr = false, quote = '';
  for (let i = 0; i < css.length; i++) {
    const ch = css[i];
    if (inStr) {
      buf += ch;
      if (ch === '\\') { buf += css[++i] || ''; continue; }
      if (ch === quote) inStr = false;
      continue;
    }
    if (ch === '"' || ch === "'") { inStr = true; quote = ch; buf += ch; continue; }
    if (ch === '{') {
      if (depth === 0) {
        const sel = buf.trim();
        // 找到配对的 }
        let d = 1, j = i + 1, body = '';
        let s = false, q = '';
        for (; j < css.length; j++) {
          const c = css[j];
          if (s) { body += c; if (c === '\\') { body += css[++j] || ''; continue; } if (c === q) s = false; continue; }
          if (c === '"' || c === "'") { s = true; q = c; body += c; continue; }
          if (c === '{') d++;
          else if (c === '}') { d--; if (d === 0) break; }
          body += c;
        }
        if (sel && !sel.startsWith('@')) rules.push([sel, body]);
        i = j;
        buf = '';
        continue;
      }
    }
    if (ch === '}') { buf = ''; continue; }
    buf += ch;
  }
  return rules;
}

/** 从 CSS 文件抽取 class -> 码点数组 */
function classMap(cssPath) {
  const map = new Map();
  const css = fs.readFileSync(cssPath, 'utf8');
  for (const [sel, body] of parseCssRules(css)) {
    const m = body.match(/content\s*:\s*("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*')/);
    if (!m) continue;
    const raw = m[1].slice(1, -1);
    // 还原 CSS 转义：\f002 / \21 / \f002 
    const cps = [];
    for (let i = 0; i < raw.length; i++) {
      if (raw[i] !== '\\') { cps.push(raw.codePointAt(i)); continue; }
      const hex = raw.slice(i + 1).match(/^[0-9a-fA-F]{1,6}/);
      if (hex) { cps.push(parseInt(hex[0], 16)); i += hex[0].length; }
      else { cps.push(raw.codePointAt(i + 1)); i++; }
    }
    if (!cps.length) continue;
    for (const mm of sel.matchAll(/\.(-?[_a-zA-Z][\w-]*)/g)) {
      if (!map.has(mm[1])) map.set(mm[1], cps);
    }
  }
  return map;
}

// ── 1+2. 主题源码扫描 ─────────────────────────────────────────────────────
const SKIP = new Set(['vendor', 'node_modules', '.git', 'build', 'dist', 'tools', 'theme-update-checker']);
const srcFiles = walk(THEME, new Set(['.php', '.js', '.html', '.htm']), SKIP)
  .filter((f) => !/argon_js_merged|argon\.min\.js|\.min\.js$/.test(path.basename(f)));

const used = new Map(); // class -> Set(来源)
const add = (c, src) => {
  if (!c) return;
  if (!used.has(c)) used.set(c, new Set());
  used.get(c).add(src);
};

for (const f of srcFiles) {
  const rel = path.relative(THEME, f);
  let t;
  try { t = fs.readFileSync(f, 'utf8'); } catch (e) { continue; }
  // class="..." 属性
  for (const m of t.matchAll(/class\s*=\s*["']([^"']*)["']/g)) {
    for (const c of m[1].split(/\s+/)) if (/^fa[a-z]?-/.test(c) || /^fa$/.test(c)) add(c, 'src:' + rel);
  }
  // 字符串字面量里的 fa-* （覆盖 PHP 拼接与 JS 动态拼类名）
  for (const m of t.matchAll(/["']([a-z][\w-]*)["']/g)) {
    if (/^fa[a-z]?-/.test(m[1])) add(m[1], 'src-literal:' + rel);
  }
  for (const m of t.matchAll(/\bfa-[a-z0-9-]+/g)) add(m[0], 'src-token:' + rel);
}

// ── 3. WordPress REST API：全部文章 + 页面正文 ────────────────────────────
async function fetchWpContent(site) {
  const base = site.replace(/\/+$/, '');
  const got = [];
  for (const type of ['posts', 'pages']) {
    let page = 1, totalPages = 1;
    do {
      const url = `${base}/wp-json/wp/v2/${type}?per_page=100&page=${page}&_fields=link,content.rendered`;
      let res;
      try {
        res = await fetch(url, { headers: { 'User-Agent': UA } });
      } catch (e) {
        console.warn(`  [warn] ${type} 第 ${page} 页请求失败: ${e.message}`);
        break;
      }
      if (!res.ok) { console.warn(`  [warn] ${type} 第 ${page} 页 HTTP ${res.status}`); break; }
      const tp = parseInt(res.headers.get('x-wp-totalpages') || '1', 10);
      totalPages = Number.isFinite(tp) && tp > 0 ? tp : 1;
      const items = await res.json();
      if (!Array.isArray(items) || !items.length) break;
      for (const it of items) {
        const html = (it.content && it.content.rendered) || '';
        got.push({ link: it.link, html });
      }
      page++;
    } while (page <= totalPages);
  }
  return got;
}

(async () => {
  console.log('主题目录: ' + THEME);
  console.log('源码文件: ' + srcFiles.length + ' 个');

  let wpItems = [];
  if (SITE) {
    console.log('拉取正文: ' + SITE + ' ...');
    wpItems = await fetchWpContent(SITE);
    let bytes = 0;
    for (const it of wpItems) {
      bytes += it.html.length;
      for (const m of it.html.matchAll(/class\s*=\s*["']([^"']*)["']/g)) {
        for (const c of m[1].split(/\s+/)) if (/^fa[a-z]?-/.test(c) || c === 'fa') add(c, 'wp-content');
      }
      for (const m of it.html.matchAll(/\bfa-[a-z0-9-]+/g)) add(m[0], 'wp-content');
    }
    console.log('  文章+页面: ' + wpItems.length + ' 篇，正文 ' + (bytes / 1024).toFixed(0) + ' KB');
  } else {
    console.log('未指定 --site，跳过正文扫描（会漏掉只在文章里出现的图标）');
  }

  // ── 解析 FA6 CSS ────────────────────────────────────────────────────────
  const fa6Dir = path.join(THEME, 'assets', 'vendor', 'font-awesome6', 'css');
  const allMap = classMap(path.join(fa6Dir, 'all.min.css'));
  const shimMap = classMap(path.join(fa6Dir, 'v4-shims.min.css'));

  const resolved = new Map();   // class -> {cps:[], src}
  const unresolved = [];
  for (const [c, sources] of used) {
    if (c === 'fa' || c === 'fa-' || /^fa-(fw|spin|pulse|rotate|flip|border|lg|[0-9]x|stack|inverse|sr-only)$/.test(c)) continue;
    if (!/^fa[a-z]?-/.test(c)) continue;
    const a = allMap.get(c);
    const s = shimMap.get(c);
    if (a || s) {
      // 两处都定义时必须取【并集】而不是取其一：
      // v4-shims 的 `.fa.fa-xxx:before` 特异性 (0,2,1) 高于 all.min.css 的 `.fa-xxx:before` (0,1,1)，
      // 实际渲染的是 v4-shims 的码点。例如 fa-code-fork：
      //   all.min.css -> \e13b      v4-shims -> \f126   ← 真正生效的是 \f126
      // 取并集后两个码点都在子集里，无论哪条规则胜出都不会缺字。
      const cps = [...new Set([...(a || []), ...(s || [])])];
      resolved.set(c, { cps, src: a && s ? 'fa6+v4-shims' : (a ? 'fa6' : 'v4-shims') });
    } else {
      unresolved.push([c, [...sources].join(',')]);
    }
  }

  // ── 组装码点集 ──────────────────────────────────────────────────────────
  // 只纳入「被用到的图标类」自身的 content 码点：
  //   - 绝大多数是 PUA（U+F000+ / U+E000+）
  //   - 少数 FA6 用 ASCII 字符渲染（如 fa-exclamation -> "\21"），其码点也要纳入
  // 不做 Basic Latin 全量兜底 —— 那会多带 95 个无用码点（实测 +9.9KB）且部分字体本就没有。
  const cps = new Set();
  const asciiCps = new Set();
  for (const [c, info] of resolved) {
    for (const cp of info.cps) {
      cps.add(cp);
      if (cp < 0xe000) asciiCps.add(c + ':U+' + cp.toString(16).toUpperCase().padStart(4, '0'));
    }
  }
  // 覆盖项（如 fa-exclamation -> f12a）
  const overrideNotes = [];
  for (const [c, cp] of Object.entries(PUA_OVERRIDES)) {
    if (used.has(c)) {
      cps.add(cp);
      overrideNotes.push(`${c} -> U+${cp.toString(16).toUpperCase()}`);
    }
  }
  // 兜底：Basic Latin 中确实被 content 引用的码点已在上面纳入，无需额外处理

  const list = [...cps].sort((a, b) => a - b);
  const uni = list.map((c) => 'U+' + c.toString(16).toUpperCase().padStart(4, '0')).join(',');

  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, uni + '\n', 'utf8');

  // 同时输出用到的图标类名，供 trim-css.js 裁剪 FA6 CSS 使用
  const classesOut = path.join(path.dirname(OUT), 'classes.txt');
  const classList = [...resolved.keys()].sort();
  fs.writeFileSync(classesOut, classList.join('\n') + '\n', 'utf8');

  // 报告
  const puaCount = list.filter((c) => c >= 0xe000).length;
  console.log('');
  console.log('=== 结果 ===');
  console.log('用到的图标类名:   ' + [...used.keys()].filter((c) => /^fa[a-z]?-/.test(c)).length);
  console.log('可解析到码点:     ' + resolved.size);
  console.log('无法解析:         ' + unresolved.length);
  if (unresolved.length) {
    for (const [c, s] of unresolved.slice(0, 30)) console.log('    ' + c + '   (' + s + ')');
    if (unresolved.length > 30) console.log('    ... 还有 ' + (unresolved.length - 30) + ' 个');
  }
  const bySrc = {};
  for (const [, v] of resolved) bySrc[v.src] = (bySrc[v.src] || 0) + 1;
  console.log('来源分布:         ' + JSON.stringify(bySrc));
  if (overrideNotes.length) console.log('PUA 覆盖:         ' + overrideNotes.join(', '));
  if (asciiCps.size) console.log('ASCII content:    ' + [...asciiCps].join(', '));
  console.log('唯一码点:         ' + list.length + '（PUA ' + puaCount + ' + ASCII ' + (list.length - puaCount) + '）');
  console.log('已写入:           ' + OUT);
  console.log('');
  console.log(uni);
})().catch((e) => { console.error('FAILED:', e); process.exit(1); });
