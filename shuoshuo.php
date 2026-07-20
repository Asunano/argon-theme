<?php 
/*
Template Name: 说说
*/
$paged = max( 1, get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' ) );
$shuoshuo_query = new WP_Query( array(
	'post_type'      => 'shuoshuo',
	'post_status'    => 'publish',
	'posts_per_page' => 50,
	'paged'          => $paged,
) );
// 用自定义查询替换主查询，使全局循环与分页正常工作（query_posts 的推荐替代写法）
global $wp_query;
$wp_query = $shuoshuo_query;
?>

<?php get_header(); ?>

<div class="page-information-card-container">
	<div class="page-information-card card bg-gradient-secondary shadow-lg border-0">
		<div class="card-body">
			<h3 class="text-black"><?php _e('说说', 'argon');?></h3>
			<?php $argon_archive_description = get_the_archive_description(); if ($argon_archive_description != ''){ ?>
				<p class="text-black mt-3">
					<?php echo $argon_archive_description; ?>
				</p>
			<?php } ?>
			<p class="text-black mt-3 mb-0 opacity-8">
				<i class="fa fa-quote-left mr-1"></i>
				<?php echo wp_count_posts('shuoshuo','') -> publish; ?> <?php _e('条说说', 'argon');?>
			</p>
		</div>
	</div>
</div>

<?php get_sidebar(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
	<?php if ( have_posts() ) : ?>
		<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'shuoshuo' );
			endwhile;
		?>
		<?php
			echo get_argon_formatted_paginate_links_for_all_platforms();
		?>
		<?php
	else :
		get_template_part( 'template-parts/content', 'none-tag' );
	endif;
		wp_reset_query();
	?>

<?php get_footer(); ?>
