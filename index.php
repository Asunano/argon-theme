<?php get_header(); ?>

<div class="page-information-card-container"></div>

<?php get_sidebar(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main article-list article-list-home" role="main">
	<?php if ( have_posts() ) : ?>
		<?php
			while ( have_posts() ) :
				the_post();
				if (get_post_type() == 'shuoshuo'){
					get_template_part( 'template-parts/content-shuoshuo-preview' );
				}else{
					$layout = get_option('argon_article_list_layout', '1');
					if (!in_array($layout, ['1', '2', '3'])){ $layout = '1'; }
					get_template_part( 'template-parts/content-preview', $layout);
				}
			endwhile;
		?>
		<?php
			echo get_argon_formatted_paginate_links_for_all_platforms();
		?>
		<?php
	endif;
	?>

<?php get_footer(); ?>
