#!/usr/bin/env node
/**
 * gen-sprite.js —— 按「主题真正消费的 emoji」重建 emoji sprite
 *
 * 为什么这么做（实测依据）：
 *   原 sprite 有 135 个符号 / 452.0 KB raw / 111.1 KB gzip。
 *   但把测试站全部 124 个页面的 HTML 抓下来扫一遍，只有 2 个符号真的出现
 *   （1f44d 👍、1f64f 🙏），且全部来自主题自带的评论表情面板；
 *   生产站 24 个页面同样只有 3 个（多一个正文里的 2b50 ⭐）。
 *   即 98% 的字节是为「从不出现的 emoji」付的。
 *
 *   更糟的是表情面板（emotions.php 的 Emoji 组共 37 个）里只有 2 个在 sprite 内，
 *   另外 35 个一直渲染成系统 emoji —— 同一个面板里风格不一致。
 *
 * 所以本脚本按「主题源码定义的消费者」重建，与站点内容无关、对所有站点成立：
 *   sprite = 表情面板的全部 emoji ∪ 显式追加的正文用到的 emoji（--extra）
 *
 * 素材取自 @twemoji/svg（Twemoji 官方 SVG 分发包），版本固定以便复现。
 * 该分发包的路径写法比主题原来的更紧凑（弧线记法 / 省略前导 0），因此同样
 * 图形下字节更少。
 *
 * 用法:
 *   node gen-sprite.js                 # 重建并写入
 *   node gen-sprite.js --report        # 只报告，不写入
 *   node gen-sprite.js --extra 2b50,1f680
 */
'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const HERE = __dirname;
const THEME = path.resolve(HERE, '..', '..');
const CACHE = path.join(HERE, 'cache');
const OUT_SVG = path.join(THEME, 'assets', 'emoji-sprite.svg');
const OUT_MAP = path.join(THEME, 'assets', 'emoji-sprite-map.json');
const EMOTIONS = path.join(THEME, 'emotions.php');

const TWEMOJI_VERSION = '15.0.0';
const CDN = (code) => `https://cdn.jsdelivr.net/npm/@twemoji/svg@${TWEMOJI_VERSION}/${code}.svg`;

/* ---- 参数 ---- */
const argv = process.argv.slice(2);
const REPORT_ONLY = argv.includes('--report');
const extraIdx = argv.indexOf('--extra');
const EXTRA = extraIdx >= 0 && argv[extraIdx + 1]
  ? argv[extraIdx + 1].split(',').map((s) => s.trim().toLowerCase()).filter(Boolean)
  : [];

/* ---- 工具 ---- */
function toCode(str) {
  let out = '';
  for (let i = 0; i < str.length;) {
    const cp = str.codePointAt(i);
    if (cp !== 0xfe0f && cp !== 0x20e3) out += cp.toString(16);
    i += cp > 0xffff ? 2 : 1;
  }
  return out;
}

/**
 * 从 emotions.php 抽出「Emoji」组的全部 text 条目。
 * 按 groupname 定位，不靠行号 —— 行号会随编辑漂移。
 */
function readEmojiGroup() {
  const src = fs.readFileSync(EMOTIONS, 'utf8');

  // 逐个 group 切开：'groupname' => '...' 到下一个 groupname 之前
  const groups = [];
  const re = /'groupname'\s*=>\s*(?:__\('([^']*)'|'([^']*)')/g;
  const marks = [];
  let m;
  while ((m = re.exec(src)) !== null) marks.push({ name: m[1] || m[2], at: m.index });
  for (let i = 0; i < marks.length; i++) {
    const end = i + 1 < marks.length ? marks[i + 1].at : src.length;
    groups.push({ name: marks[i].name, body: src.slice(marks[i].at, end) });
  }

  const emojiGroup = groups.find((g) => g.name === 'Emoji');
  if (!emojiGroup) throw new Error('emotions.php 里找不到 groupname 为 Emoji 的分组');

  const texts = [...emojiGroup.body.matchAll(/'type'\s*=>\s*'text'\s*,\s*'text'\s*=>\s*"([^"]*)"/g)]
    .map((x) => x[1]);

  return { groups: groups.map((g) => g.name), texts };
}

/** 从缓存或 CDN 取一个 Twemoji SVG，返回 <symbol> 片段 */
async function fetchSymbol(code) {
  fs.mkdirSync(CACHE, { recursive: true });
  const cached = path.join(CACHE, `${code}.svg`);
  let svg;
  if (fs.existsSync(cached)) {
    svg = fs.readFileSync(cached, 'utf8');
  } else {
    const r = await fetch(CDN(code), { headers: { 'User-Agent': 'argon-theme-sprite-builder' } });
    if (!r.ok) throw new Error(`${code}: HTTP ${r.status}`);
    svg = await r.text();
    fs.writeFileSync(cached, svg);
  }

  // <svg ... viewBox="0 0 36 36"> ... </svg>  ->  <symbol id="e{code}" viewBox="0 0 36 36"> ... </symbol>
  const vb = (svg.match(/viewBox="([^"]*)"/) || [, '0 0 36 36'])[1];
  const inner = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
  if (!inner.trim()) throw new Error(`${code}: 取到的 SVG 没有内容`);
  return `<symbol id="e${code}" viewBox="${vb}">${inner}</symbol>`;
}

async function main() {
  const { groups, texts } = readEmojiGroup();
  console.log('emotions.php 分组: ' + groups.join(' / '));
  console.log('Emoji 组条目: ' + texts.length);

  // 去重保序
  const codes = [];
  const seen = new Set();
  const notEmoji = [];
  for (const t of texts) {
    const c = toCode(t);
    // 单个 emoji 归一后是 4-5 位十六进制（1f602 / 2b50 之类）；
    // 颜文字那种多字符文本会拼成长串，据此排除。
    if (!/^[0-9a-f]{4,5}$/.test(c)) { notEmoji.push(t); continue; }
    if (seen.has(c)) continue;
    seen.add(c);
    codes.push(c);
  }
  for (const c of EXTRA) if (!seen.has(c)) { seen.add(c); codes.push(c); }

  console.log('去重后需要 sprite 符号: ' + codes.length + (EXTRA.length ? `（含 --extra ${EXTRA.length} 个）` : ''));
  if (notEmoji.length) console.log('跳过非 emoji 条目: ' + notEmoji.length);

  // 取素材
  const symbols = [];
  for (const c of codes) {
    symbols.push(await fetchSymbol(c));
  }

  const head = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none">\n';
  const svg = head + symbols.join('') + '\n</svg>';

  const map = {};
  for (const c of codes) map[c] = 'e' + c;
  const mapJson = JSON.stringify(map, null, 2);

  const gz = (s) => zlib.gzipSync(Buffer.from(s)).length;
  const br = (s) => zlib.brotliCompressSync(Buffer.from(s)).length;

  const oldSvg = fs.readFileSync(OUT_SVG, 'utf8');
  console.log('');
  console.log('                符号数        raw         gzip       brotli');
  console.log('  旧 sprite     ' + String(135).padStart(5) + '  ' +
    (oldSvg.length / 1024).toFixed(1).padStart(9) + ' KB' +
    (gz(oldSvg) / 1024).toFixed(1).padStart(9) + ' KB' +
    (br(oldSvg) / 1024).toFixed(1).padStart(9) + ' KB');
  console.log('  新 sprite     ' + String(codes.length).padStart(5) + '  ' +
    (svg.length / 1024).toFixed(1).padStart(9) + ' KB' +
    (gz(svg) / 1024).toFixed(1).padStart(9) + ' KB' +
    (br(svg) / 1024).toFixed(1).padStart(9) + ' KB');
  console.log('');
  console.log('  节省 gzip: ' + ((gz(oldSvg) - gz(svg)) / 1024).toFixed(1) + ' KB' +
    '（' + (100 * (1 - gz(svg) / gz(oldSvg))).toFixed(1) + '%）');
  console.log('  map: ' + (mapJson.length / 1024).toFixed(1) + ' KB（旧 ' +
    (fs.readFileSync(OUT_MAP).length / 1024).toFixed(1) + ' KB）');

  if (REPORT_ONLY) {
    console.log('\n--report：未写入');
    return;
  }
  fs.writeFileSync(OUT_SVG, svg);
  fs.writeFileSync(OUT_MAP, mapJson + '\n');
  console.log('\n已写入 assets/emoji-sprite.svg 与 assets/emoji-sprite-map.json');
}

main().catch((e) => { console.error('ERR', e.message); process.exit(1); });
