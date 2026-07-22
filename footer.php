					<footer id="footer" class="site-footer card shadow-sm border-0">
						<?php
							echo get_option('argon_footer_html');
						?>
						<?php
							$argon_show_footer_author = (get_option('argon_hide_footer_author') != 'true');
						?>
						<div>Theme <a href="https://github.com/solstice23/argon-theme" target="_blank"><strong>Argon</strong></a><?php
							if ($argon_show_footer_author){ echo ' By solstice23'; }
							echo ($argon_show_footer_author ? ' | ' : ' ') . 'Enhanced By <a href="https://github.com/Asunano/argon-theme" target="_blank"><strong>Asunano</strong></a>';
						?></div>
						<?php if (get_option('argon_enable_runtime') == 'true' || get_option('argon_enable_runtime') == '') { ?>
						<div class="argon-runtime"><strong><span id="showsectime" style="color:#FFFFFF;"></span></strong></div>
						<?php } ?>
					</footer>
				</main>
			</div>
		</div>
		<?php
			$argon_live_search_config = array(
				'enabled'   => (get_option('argon_enable_live_search') != 'false'),
				'ajaxUrl'   => admin_url('admin-ajax.php'),
				'searchUrl' => home_url('/'),
				'minChars'  => 1,
				'maxResults'=> 8,
				'i18n'      => array(
					'loading' => __('搜索中…', 'argon'),
					'empty'   => __('没有找到相关结果', 'argon'),
					'all'     => __('查看全部搜索结果', 'argon'),
				),
			);
		?>
		<script>window.argonLiveSearchConfig = <?php echo wp_json_encode($argon_live_search_config, JSON_UNESCAPED_UNICODE); ?>;</script>
		<?php
			$argon_scroll_blur_config = array(
				'enabled'         => (get_option('argon_enable_scroll_blur') == 'true' || get_option('argon_enable_scroll_blur') == ''),
				'isHome'          => is_home(),
				'homeThreshold'   => 0.8,
				'otherThreshold'  => 0.2,
			);
			$argon_runtime_config = array(
				'enabled'   => (get_option('argon_enable_runtime') == 'true' || get_option('argon_enable_runtime') == ''),
				'startDate' => get_option('argon_runtime_start_date', '2020-10-31'),
			);
		?>
		<script>window.argonScrollBlurConfig = <?php echo wp_json_encode($argon_scroll_blur_config, JSON_UNESCAPED_UNICODE); ?>;</script>
		<script>window.argonRuntimeConfig = <?php echo wp_json_encode($argon_runtime_config, JSON_UNESCAPED_UNICODE); ?>;</script>
		<?php
			$argon_lightbox_config = array(
				'enabled' => true,
			);
		?>
		<script>window.argonLightboxConfig = <?php echo wp_json_encode($argon_lightbox_config, JSON_UNESCAPED_UNICODE); ?>;</script>
		<script defer src="<?php echo $GLOBALS['assets_path']; ?>/argontheme.js?v<?php echo $GLOBALS['theme_version']; ?>"></script>
		<?php if (get_option('argon_math_render') == 'mathjax3') { /*Mathjax V3*/?>
			<script>
				window.MathJax = {
					tex: {
						inlineMath: [["$", "$"], ["\\\\(", "\\\\)"]],
						displayMath: [['$$','$$']],
						processEscapes: true,
						packages: {'[+]': ['noerrors']}
					},
					options: {
						skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
						ignoreHtmlClass: 'tex2jax_ignore',
						processHtmlClass: 'tex2jax_process'
					},
					loader: {
						load: ['[tex]/noerrors']
					}
				};
			</script>
			<script src="<?php echo get_option('argon_mathjax_cdn_url') == '' ? '//cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml-full.js' : get_option('argon_mathjax_cdn_url'); ?>" id="MathJax-script" async></script>
		<?php }?>
		<?php if (get_option('argon_math_render') == 'mathjax2') { /*Mathjax V2*/?>
			<script type="text/x-mathjax-config" id="mathjax_v2_script">
				MathJax.Hub.Config({
					messageStyle: "none",
					tex2jax: {
						inlineMath: [["$", "$"], ["\\\\(", "\\\\)"]],
						displayMath: [['$$','$$']],
						processEscapes: true,
						skipTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
					},
					menuSettings: {
						zoom: "Hover",
						zscale: "200%"
					},
					"HTML-CSS": {
						showMathMenu: "false"
					}
				});
			</script>
			<script src="<?php echo get_option('argon_mathjax_v2_cdn_url') == '' ? '//cdn.jsdelivr.net/npm/mathjax@2.7.5/MathJax.js?config=TeX-AMS_HTML' : get_option('argon_mathjax_v2_cdn_url'); ?>"></script>
		<?php }?>
		<?php if (get_option('argon_math_render') == 'katex') { /*Katex*/?>
			<link rel="stylesheet" href="<?php echo get_option('argon_katex_cdn_url') == '' ? '//cdn.jsdelivr.net/npm/katex@0.11.1/dist/' : get_option('argon_katex_cdn_url'); ?>katex.min.css">
			<script src="<?php echo get_option('argon_katex_cdn_url') == '' ? '//cdn.jsdelivr.net/npm/katex@0.11.1/dist/' : get_option('argon_katex_cdn_url'); ?>katex.min.js"></script>
			<script src="<?php echo get_option('argon_katex_cdn_url') == '' ? '//cdn.jsdelivr.net/npm/katex@0.11.1/dist/' : get_option('argon_katex_cdn_url'); ?>contrib/auto-render.min.js"></script>
			<script>
				document.addEventListener("DOMContentLoaded", function() {
					renderMathInElement(document.body,{
						delimiters: [
							{left: "$$", right: "$$", display: true},
							{left: "$", right: "$", display: false},
							{left: "\\(", right: "\\)", display: false}
						]
					});
				});
			</script>
		<?php }?>

		<?php if (get_option('argon_enable_code_highlight') == 'true') { /*Highlight.js*/?>
			<link rel="stylesheet" href="<?php echo $GLOBALS['assets_path']; ?>/assets/vendor/highlight/styles/<?php echo get_option('argon_code_theme') == '' ? 'vs2015' : get_option('argon_code_theme'); ?>.css">
		<?php }?>

	</div>
</div>
<?php 
	wp_enqueue_script("argonjs", $GLOBALS['assets_path'] . "/assets/js/argon.min.js", null, $GLOBALS['theme_version'], true);
?>
<?php wp_footer(); ?>
</body>

<?php echo get_option('argon_custom_html_foot'); ?>

</html>
