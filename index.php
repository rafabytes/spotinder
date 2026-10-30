<?php
/**
 * Fallback (blog/arquivos genéricos).
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="container-site max-w-3xl py-14">
	<?php if ( is_singular() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article>
				<h1 class="mb-8 text-4xl font-semibold tracking-tight text-white"><?php the_title(); ?></h1>
				<div class="prose-site"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<h1 class="mb-10 text-4xl font-semibold tracking-tight text-white"><?php the_archive_title(); ?></h1>
		<div class="space-y-4">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article class="card p-6">
					<h2 class="text-xl font-semibold text-white"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p class="mt-2 text-white/60"><?php echo esc_html( get_the_excerpt() ); ?></p>
				</article>
			<?php endwhile; ?>
		</div>
		<div class="mt-10"><?php the_posts_pagination(); ?></div>
	<?php endif; ?>
</section>
<?php
get_footer();
