import './alert/alert.js';
import './admonition/admonition.js';
import './collapse/collapse.js';
import './github/github.js';
import './timeline/timeline.js';
import './progressbar/progressbar.js';
import './todolist/todolist.js';
import './tabpanel/tabpanel.js';
/* 补齐短代码里有、区块里缺的四个功能（2026-10）：
   label 徽章 / hidden 隐藏文本 / checkbox 单个复选框 / friendlinks 友链列表。
   friendlinks 是动态区块 —— save 返回 null，前台 HTML 由 PHP 的 render_callback 生成。 */
import './label/label.js';
import './hidden/hidden.js';
import './checkbox/checkbox.js';
import './friendlinks/friendlinks.js';
import './ref/ref.js';
/* FAQ / HowTo：输出可见列表 + FAQPage / HowTo 结构化数据（JSON-LD 由 PHP 侧合并输出） */
import './faq/faq.js';
import './howto/howto.js';
/* 快赢包（2026-11）：kbd 键盘按键 / mark 高亮标记 / btn 按钮。
   前台样式在主题 style.css（与短代码同源），编辑器预览镜像在 shortcode-preview.scss。 */
import './kbd/kbd.js';
import './mark/mark.js';
import './btn/btn.js';
import './shortcode-preview.scss';
import './shortcode-editor.scss';
/* 媒体包（2026-11）：外链媒体动态区块 —— 渲染走 PHP（B 站信息卡要服务端拉平台 API） */
import './download/download.js';
import './bilibili/bilibili.js';
import './netease/netease.js';
/* mermaid 图表（静态区块）：前端 mermaid.js 懒加载渲染，加载/渲染失败保留源码 */
import './mermaid/mermaid.js';
/* 播放器（2026-11）：动态区块，后台开关决定 APlayer/ArtPlayer 或原生降级；
   库随主题分发在 assets/vendor/，前端按需注入 */
import './aplayer/aplayer.js';
import './artplayer/artplayer.js';
/* 特色包（2026-11）：内容门控（InnerBlocks，未授权时服务端丢弃内层 HTML）、外链卡片 */
import './gate/gate.js';
import './link-card/link-card.js';
/* 文章引用卡 / 文章列表（2026-11）：动态区块，反向引用由 PHP 在保存时维护 */
import './post-card/post-card.js';
/* 评分卡（2026-11）：可见部分 + Review JSON-LD 都由 PHP 生成，与短代码共用 */
import './review/review.js';
/* 轻量组件（2026-11）：圆形进度 / 倒计时 / 文内目录 / 展开更多 */
import './circle-countdown/circle-countdown.js';
import './toc-show-more/toc-show-more.js';
/* copy 与 friend-moments 区块（2026-11）：补齐最后两个仅短代码的组件 */
import './copy/copy.js';
import './friend-moments/friend-moments.js';
