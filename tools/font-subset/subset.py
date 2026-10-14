#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""subset.py —— 按码点集对 FA6 字体做子集化

用法：
    python subset.py [--source DIR] [--dest DIR] [--cps FILE] [--check]

  --source  完整版 FA6 字体目录（默认 tools/font-subset/original/）
  --dest    输出目录（默认 <theme>/assets/vendor/font-awesome6/webfonts/）
  --cps     码点文件（默认 tools/font-subset/codepoints.txt）
  --check   只校验当前已装字体的码点覆盖，不做子集化

依赖：pip install fonttools brotli
"""
import argparse
import os
import re
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
THEME = os.path.abspath(os.path.join(HERE, "..", ".."))
FONTS = ["fa-solid-900", "fa-regular-400", "fa-brands-400"]

PYFTSUBSET_ARGS = [
    "--flavor=woff2",
    "--layout-features=",       # 图标字体不需要 OpenType 特性
    "--no-hinting",             # 图标字体不需要 hinting
    "--desubroutinize",
    "--drop-tables+=DSIG",
    "--name-IDs=*",
    "--recalc-bounds",
]

# 扫描主题自有 CSS 时跳过的目录
CSS_SKIP = {"vendor", "node_modules", ".git", "build", "dist", "tools"}


def scan_css_content(theme):
    """扫描主题自有 CSS 里硬编码的 content:"\\fXXX" 码点。

    有些图标不是通过 class 使用的，而是直接在 CSS 里写死，例如
    style.css 的 `pre.hljs-codeblock... .hljs-control-fullscreen > i:before{content:"\\f066"}`。
    这些码点不会被「图标类名」扫描覆盖，必须单独收集。
    """
    found = {}
    for root, dirs, files in os.walk(theme):
        dirs[:] = [d for d in dirs if d not in CSS_SKIP]
        for fn in files:
            if not fn.endswith(".css"):
                continue
            p = os.path.join(root, fn)
            try:
                txt = open(p, encoding="utf-8", errors="ignore").read()
            except OSError:
                continue
            for m in re.finditer(r"content\s*:\s*[\"']\\([0-9a-fA-F]{4,6})[\"']", txt):
                cp = int(m.group(1), 16)
                found.setdefault(cp, set()).add(os.path.relpath(p, theme).replace("\\", "/"))
    return found


def font_cmaps(directory):
    from fontTools.ttLib import TTFont
    cms = {}
    for n in FONTS:
        p = os.path.join(directory, n + ".woff2")
        if os.path.exists(p):
            f = TTFont(p)
            cms[n] = f.getBestCmap()
            f.close()
    return cms


def parse_cps(text):
    out = set()
    for tok in text.replace("\n", ",").split(","):
        tok = tok.strip()
        if not tok:
            continue
        if tok.upper().startswith("U+"):
            tok = tok[2:]
        out.add(int(tok, 16))
    return sorted(out)


def kb(n):
    return "%.1fK" % (n / 1024.0)


def check(dest, cps):
    from fontTools.ttLib import TTFont
    print("=== 覆盖率校验 ===")
    ok = True
    for name in FONTS:
        p = os.path.join(dest, name + ".woff2")
        if not os.path.exists(p):
            print("  %-18s 缺失" % name)
            ok = False
            continue
        f = TTFont(p)
        cm = f.getBestCmap()
        have = [c for c in cps if c in cm]
        print("  %-18s %6s  码点 %3d/%d  字形 %d" % (
            name, kb(os.path.getsize(p)), len(have), len(cps), len(f.getGlyphOrder())))
        f.close()
    # 每个码点至少要有一个字体提供
    allcm = {}
    for name in FONTS:
        p = os.path.join(dest, name + ".woff2")
        if os.path.exists(p):
            f = TTFont(p)
            allcm[name] = f.getBestCmap()
            f.close()
    missing = [c for c in cps if not any(c in cm for cm in allcm.values())]
    if missing:
        ok = False
        print("  !! 无任何字体提供的码点: " + " ".join("U+%04X" % c for c in missing))
    print("  结果: " + ("PASS" if ok else "FAIL"))
    return ok


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--source", default=os.path.join(HERE, "original"))
    ap.add_argument("--dest", default=os.path.join(THEME, "assets", "vendor", "font-awesome6", "webfonts"))
    ap.add_argument("--cps", default=os.path.join(HERE, "codepoints.txt"))
    ap.add_argument("--check", action="store_true")
    a = ap.parse_args()

    if not os.path.exists(a.cps):
        print("码点文件不存在: %s\n请先运行 node gen-codepoints.js --site <站点>" % a.cps)
        return 1
    cps = set(parse_cps(open(a.cps, encoding="utf-8").read()))
    from_classes = len(cps)

    # 并入「主题 CSS 里硬编码的 content 码点」，并用原字体 cmap 过滤：
    # 过滤能自动排除别的图标字体（如 nucleo 的 \ea26 / \ea53 —— 它们不在 FA 字体里）。
    ref = a.source if os.path.isdir(a.source) else a.dest
    css_cps = scan_css_content(THEME)
    added = []
    if css_cps and os.path.isdir(ref):
        try:
            cms = font_cmaps(ref)
        except Exception as e:  # noqa: BLE001
            cms = {}
            print("  [warn] 读取参考字体失败，跳过 CSS content 扫描: %s" % e)
        for cp in sorted(css_cps):
            if cp in cps:
                continue
            if any(cp in cm for cm in cms.values()):
                cps.add(cp)
                added.append((cp, sorted(css_cps[cp])[:2]))
    cps = sorted(cps)

    print("来自图标类名: %d 个码点" % from_classes)
    if added:
        print("来自 CSS 硬编码 content: +%d 个" % len(added))
        for cp, files in added:
            print("    U+%04X  %s" % (cp, ", ".join(files)))
    elif css_cps:
        print("来自 CSS 硬编码 content: 无需新增（%d 个候选，均已被图标类名覆盖或不属于 FA 字体）" % len(css_cps))
    print("合计码点: %d 个（PUA %d + ASCII %d）" % (
        len(cps), sum(1 for c in cps if c >= 0xE000), sum(1 for c in cps if c < 0xE000)))

    # 落盘最终码点表：供 verify.js / check-css-content.js 比对
    final_path = os.path.join(HERE, "codepoints-final.txt")
    with open(final_path, "w", encoding="utf-8") as fh:
        fh.write(",".join("U+%04X" % c for c in cps) + "\n")
    print("最终码点表: %s" % final_path)
    print("")

    if a.check:
        return 0 if check(a.dest, cps) else 1

    missing_src = [n for n in FONTS if not os.path.exists(os.path.join(a.source, n + ".woff2"))]
    if missing_src:
        print("源字体缺失: %s" % ", ".join(missing_src))
        print("请把完整版 FA6 字体放入: %s" % a.source)
        print("来源：https://github.com/FortAwesome/Font-Awesome/releases  (webfonts/*.woff2)")
        return 1

    uni = ",".join("U+%04X" % c for c in cps)
    print("%-18s %10s %10s %8s %8s" % ("font", "orig", "subset", "ratio", "glyphs"))
    print("-" * 60)
    tot_o = tot_s = 0
    os.makedirs(a.dest, exist_ok=True)
    for name in FONTS:
        src = os.path.join(a.source, name + ".woff2")
        dst = os.path.join(a.dest, name + ".woff2")
        r = subprocess.run([sys.executable, "-m", "fontTools.subset", src,
                            "--unicodes=" + uni, "--output-file=" + dst] + PYFTSUBSET_ARGS,
                           capture_output=True, text=True)
        if r.returncode != 0:
            print("%-18s 子集化失败: %s" % (name, r.stderr[-400:]))
            return 1
        o, s = os.path.getsize(src), os.path.getsize(dst)
        tot_o += o
        tot_s += s
        from fontTools.ttLib import TTFont
        f = TTFont(dst)
        ng = len(f.getGlyphOrder())
        f.close()
        print("%-18s %9s %9s %7.1f%% %8d" % (name, kb(o), kb(s), 100.0 * s / o, ng))
    print("-" * 60)
    print("%-18s %9s %9s %7.1f%%" % ("TOTAL", kb(tot_o), kb(tot_s), 100.0 * tot_s / tot_o))
    print("")

    return 0 if check(a.dest, cps) else 1


if __name__ == "__main__":
    sys.exit(main())
