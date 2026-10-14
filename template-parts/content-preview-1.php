<article class="post card bg-white shadow-sm border-0 post-preview-layout-1 <?php if (get_option('argon_enable_into_article_animation') == 'true'){echo 'post-preview';} ?>" id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="post-header text-center<?php if (argon_has_post_thumbnail()){echo " post-header-with-thumbnail";}?>">
		<?php
			if (argon_has_post_thumbnail()){
				$thumbnail_url = argon_get_post_thumbnail();
				argon_render_post_thumbnail($thumbnail_url, get_option('argon_enable_lazyload') != 'false');
			}
		?>
		<?php /* 列表标题用 h2 包裹：<a> 必须保留（它是整条列表的可点链接），
		         且列表页不能出现多个 h1。 */ ?>
		<h2 class="post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="post-meta">
			<?php
				$metaList = explode('|', get_option('argon_article_meta', 'time|views|comments|categories'));
				if (get_option('argon_enable_post_like', 'true') == 'true' && get_post_type() == 'post' && !in_array('like', $metaList)){
					$metaList[] = 'like';
				}
				if (is_sticky() && is_home() && ! is_paged()){
					array_unshift($metaList, "sticky");
				}
				if (post_password_required()){
					array_unshift($metaList, "needpassword");
				}
				for ($i = 0; $i < count($metaList); $i++){
					if ($i > 0){
						echo ' <div class="post-meta-devide">|</div> ';
					}
					echo get_article_meta($metaList[$i]);
				}
			?>
			<?php
				global $post;
				$post_content_full = apply_filters('the_content', preg_replace( '<!--more(.*?)-->', '', $post -> post_content));
				$post_content_with_more = apply_filters('the_content', $post -> post_content);
			?>
			<?php if (!post_password_required() && get_option("argon_show_readingtime") != "false" && is_readingtime_meta_hidden() == False) {
				echo get_article_reading_time_meta($post_content_full);
			} ?>
		</div>
	</header>

	<?php
		$trim_words_count = get_option('argon_trim_words_count', 175);
	?>
	<?php if ($trim_words_count > 0){ ?>
		<div class="post-content">
			<?php
				if (get_option("argon_hide_shortcode_in_preview") == 'true'){
					$preview = wp_trim_words(do_shortcode(get_the_content('...')), $trim_words_count);
				}else{
					$preview = wp_trim_words(get_the_content('...'), $trim_words_count);
				}
				if (post_password_required()){
					$preview = __("这篇文章受密码保护，输入密码才能阅读", 'argon');
				}
				if ($preview == ""){
					$preview = __("这篇文章没有摘要", 'argon');
				}
				if ($post -> post_excerpt){
					$preview = $post -> post_excerpt;
				}
				echo $preview;
			?>
		</div>
	<?php
		}
	?>

	<?php if (has_tag()) { ?>
		<div class="post-tags">
			<i class="fa fa-tags" aria-hidden="true"></i>
			<?php
				$tags = get_the_tags();
				foreach ($tags as $tag) {
					echo "<a href='" . esc_url(get_tag_link($tag -> term_id)) . "' target='_blank' class='tag badge badge-secondary post-meta-detail-tag'>" . esc_html($tag -> name) . "</a>";
				}
			?>
		</div>
	<?php } ?>
</article>