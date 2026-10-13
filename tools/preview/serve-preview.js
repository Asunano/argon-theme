/**
 * serve-preview.js —— 本地预览服务器（纯服务，不依赖 puppeteer）
 *
 * 托管 local-preview.php 生成的 preview.html，并把主题的真实资源
 * （style.css / argontheme.js / 字体 / 图片）按 /wp-content/themes/<dir>/ 的路径映射过去，
 * 这样预览页与线上结构一致，CSS/JS 行为可以直接对照。
 *
 * 图片会【人为延迟】，用来模拟「文字已出、图还没下完」那段窗口 ——
 * 正是 LQIP 要填补的时间。
 *
 * 用法:
 *   php tools/preview/local-preview.php        # 先生成 preview.html
 *   node tools/preview/serve-preview.js        # 起服务
 *   PREVIEW_IMG_DELAY=0 node tools/preview/serve-preview.js   # 不延迟图片
 */
'use strict';
const fs = require('fs');
const path = require('path');
const http = require('http');

const THEME = path.resolve(__dirname, '..', '..');
const PREVIEW = __dirname;
const PORT = Number(process.env.PREVIEW_PORT || 8877);
const IMG_DELAY = Number(process.env.PREVIEW_IMG_DELAY || 3000);

const MIME = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8', '.json': 'application/json',
  '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.webp': 'image/webp', '.gif': 'image/gif', '.woff': 'font/woff', '.woff2': 'font/woff2',
  '.ttf': 'font/ttf', '.eot': 'application/vnd.ms-fontobject',
};

const isImage = (p) => /\.(jpe?g|png|webp|gif)$/i.test(p);

function createServer() {
  return http.createServer((req, res) => {
    const p = decodeURIComponent(new URL(req.url, 'http://x').pathname);
    const m = p.match(/^\/wp-content\/themes\/[^/]+\/(.*)$/);
    let file;
    if (m) { file = path.join(THEME, m[1]); }
    else if (p === '/' || p === '/index.html') { file = path.join(PREVIEW, 'preview.html'); }
    else { file = path.join(PREVIEW, p.replace(/^\/+/, '')); }

    if (!file.startsWith(THEME) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) {
      res.writeHead(404); res.end('not found: ' + p); return;
    }
    const type = MIME[path.extname(file).toLowerCase()] || 'application/octet-stream';
    const send = () => {
      res.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'no-store' });
      res.end(fs.readFileSync(file));
    };
    if (isImage(file) && IMG_DELAY > 0) { setTimeout(send, IMG_DELAY); } else { send(); }
  });
}

module.exports = { createServer, PORT, IMG_DELAY, MIME, THEME, PREVIEW };

if (require.main === module) {
  const s = createServer();
  s.listen(PORT, () => {
    console.log('  预览地址: http://127.0.0.1:' + PORT + '/');
    console.log('  图片人为延迟: ' + IMG_DELAY + 'ms（PREVIEW_IMG_DELAY=0 可关掉）');
    console.log('  主题目录: ' + THEME);
    console.log('');
    console.log('  按 Ctrl+C 退出。');
  });
}
