/*!
 * Argon 主题 · Emoji 本地 Sprite 渲染
 *
 * 背景：WordPress 默认通过 wp-emoji-loader 动态导入 wp-emoji-release.min.js，
 * 在客户端把正文 emoji 字符替换为 <img src="https://s.w.org/.../xxx.svg">，
 * 导致每篇文章产生数十个跨域请求（本站在 archives/873 实测 70 个 / 90.4 KB）。
 *
 * 本脚本在 PHP 侧已移除 WP 的 emoji 链路（见 functions.php 的 argon_disable_wp_emoji），
 * 因此不会有任何跨域请求；改由这里把 emoji 字符渲染为本地 sprite 的 <use> 引用，
 * 整个站点只请求 1 个 emoji-sprite.svg（同源，可长缓存）。
 *
 * sprite 必须与页面同源：SVG <use> 不允许跨源引用，跨域会静默失败。
 */
(function () {
  "use strict";

  var CONFIG = null;      // { url, map, targets }
  var REPLACED = false;

  /* Emoji 序列匹配。分支顺序至关重要：
   *   1) 区域指示符对（🇯🇵）必须最先匹配，否则会被后面的单码点分支截断
   *   2) Extended_Pictographic（含肤色修饰与 VS16，以及可重复的 ZWJ 链）
   *      必须排在 \p{Emoji} 之前 —— \p{Emoji} 会先命中单码点，
   *      导致 👨‍👩‍👧‍👦 只匹配到第一个 👨 而截断
   *   3) \p{Emoji} + 可选 VS16 + 可选键帽符（1️⃣）
   *   4) BMP 杂项符号区（箭头 / 几何图形 / 杂项符号）
   * 用 Unicode 属性转义构造，避免手写码点表出错。
   */
  var EMOJI_RE = new RegExp(
    '(?:\\p{Regional_Indicator}{2}' +
    '|\\p{Extended_Pictographic}(?:\\p{Emoji_Modifier})?\\uFE0F?' +
    '(?:\\u200D\\p{Extended_Pictographic}(?:\\p{Emoji_Modifier})?\\uFE0F?)*' +
    '|\\p{Emoji}\\uFE0F?\\u20E3?' +
    '|[\\u2190-\\u21FF\\u2300-\\u27BF\\u2B00-\\u2BFF\\u2B50-\\u2B55]\\uFE0F?)',
    'gu'
  );

  function readConfig() {
    if (CONFIG) return CONFIG;
    var el = document.getElementById("argon-emoji-sprite-data");
    if (!el) return null;
    try {
      var data = JSON.parse(el.textContent || "{}");
      if (!data.url || !data.map || !Object.keys(data.map).length) return null;
      CONFIG = {
        url: data.url,
        map: data.map,
        targets: data.targets && data.targets.length ? data.targets : ["#primary", "#comments", "#secondary"]
      };
    } catch (e) {
      return null;
    }
    return CONFIG;
  }

  /* 字符 → 小写十六进制 codepoint 串（与 sprite 的 id 后缀一致）
   需剥离 U+FE0F（变体选择符）与 U+20E3（键帽符号）—— WP 生成 URL 时同样会剥离，
   实测 ⚠️(U+26A0 U+FE0F) 对应的图片地址是 .../26a0.svg 而非 .../26a0fe0f.svg。 */
  function toCode(str) {
    var out = "";
    for (var i = 0; i < str.length; ) {
      var cp = str.codePointAt(i);
      if (cp !== 0xfe0f && cp !== 0x20e3) {
        out += cp.toString(16);
      }
      /* 码点在 U+10000 以上占 2 个 UTF-16 码元，其余占 1 个 */
      i += cp > 0xffff ? 2 : 1;
    }
    return out;
  }

  function buildSvg(code) {
    var ns = "http://www.w3.org/2000/svg";
    var svg = document.createElementNS(ns, "svg");
    svg.setAttribute("class", "wp-emoji wp-emoji-argon");
    svg.setAttribute("role", "img");
    var use = document.createElementNS(ns, "use");
    /* getAttribute('href') 兼容旧浏览器；WP 支持的现代浏览器均可用 setAttribute */
    use.setAttribute("href", CONFIG.url + "#" + CONFIG.map[code]);
    svg.appendChild(use);
    return svg;
  }

  function replaceIn(root) {
    if (!CONFIG) return 0;
    var replaced = 0;

    /* 跳过脚本、样式、输入框、以及已处理过的节点 */
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        var p = node.parentNode;
        if (!p) return NodeFilter.FILTER_REJECT;
        var tag = p.nodeName;
        if (tag === "SCRIPT" || tag === "STYLE" || tag === "TEXTAREA" || tag === "INPUT") {
          return NodeFilter.FILTER_REJECT;
        }
        if (p.closest && p.closest("svg, code, pre")) {
          return NodeFilter.FILTER_REJECT;   /* 代码块内保留原字符，不转 sprite */
        }
        return node.nodeValue && EMOJI_RE.test(node.nodeValue)
          ? NodeFilter.FILTER_ACCEPT
          : NodeFilter.FILTER_REJECT;
      }
    });

    var textNodes = [];
    while (walker.nextNode()) textNodes.push(walker.currentNode);

    textNodes.forEach(function (node) {
      var text = node.nodeValue;
      var frag = document.createDocumentFragment();
      var last = 0;
      var hits = 0;
      var m;
      EMOJI_RE.lastIndex = 0;
      while ((m = EMOJI_RE.exec(text)) !== null) {
        var raw = m[0];
        var code = toCode(raw);
        if (!CONFIG.map[code]) {
          /* 映射表未收录 -> 保留原字符，避免出现空白图标 */
          continue;
        }
        if (m.index > last) {
          frag.appendChild(document.createTextNode(text.slice(last, m.index)));
        }
        frag.appendChild(buildSvg(code));
        last = m.index + raw.length;
        hits++;
        replaced++;
      }
      if (hits > 0) {
        if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
        node.parentNode.replaceChild(frag, node);
      }
    });

    return replaced;
  }

  function run() {
    if (REPLACED) return;
    if (!readConfig()) return;
    REPLACED = true;
    CONFIG.targets.forEach(function (sel) {
      var el = document.querySelector(sel);
      if (el) replaceIn(el);
    });
    document.dispatchEvent(new CustomEvent("argon-emoji-ready"));
  }

  /* 三种时机都要覆盖：
   * 1) DOMContentLoaded —— 常规整页加载
   * 2) pjax:end         —— 站内 Pjax 换页后新内容需重新处理
   * 3) argon-emoji-ready 由外部派发（评论分页等局部更新）
   */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run);
  } else {
    run();
  }

  document.addEventListener("argon-emoji-refresh", function () {
    REPLACED = false;
    run();
  });

  window.argonEmojiSprite = { run: run, replaceIn: replaceIn, config: function () { return CONFIG; } };
})();