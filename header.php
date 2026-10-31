<?php
/**
 * Cabeçalho.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_member = spotinder_is_member();
$spotinder_logo   = spotinder_opt( 'logo' );
?>
<!doctype html>
<html <?php language_attributes(); ?> class="bg-ink">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#0b0a10">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bg-ink text-white/90 font-sans antialiased min-h-dvh flex flex-col selection:bg-brand selection:text-white' ); ?>>
<?php wp_body_open(); ?>
<a class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-ink" href="#swup"><?php esc_html_e( 'Pular para o conteúdo', 'spotinder' ); ?></a>

<header id="site-header" class="sticky top-0 z-40 border-b border-white/5 bg-ink/80 backdrop-blur-xl">
	<div class="container-site flex h-16 items-center gap-6">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex shrink-0 items-center gap-2 font-semibold tracking-tight text-white" aria-label="<?php echo esc_attr( spotinder_brand() ); ?> — início">
			<?php if ( $spotinder_logo ) : ?>
				<img src="<?php echo esc_url( $spotinder_logo ); ?>" alt="<?php echo esc_attr( spotinder_brand() ); ?>" class="h-8 w-auto">
			<?php else : ?>
				<?php echo spotinder_logo_mark( 'size-9 drop-shadow-[0_6px_16px_rgb(var(--sp-primary-rgb)/0.35)]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="text-lg"><?php echo esc_html( spotinder_brand() ); ?></span>
			<?php endif; ?>
		</a>

		<nav class="nav-primary ml-2 hidden md:block" aria-label="<?php esc_attr_e( 'Principal', 'spotinder' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'fallback_cb'    => 'spotinder_fallback_menu',
					'depth'          => 1,
				)
			);
			?>
		</nav>

		<div class="ml-auto flex items-center gap-2">
			<?php if ( $spotinder_member ) : ?>
				<a href="<?php echo esc_url( spotinder_page_url( 'discover' ) ); ?>" class="btn-ghost hidden sm:inline-flex"><?php echo spotinder_icon( 'sparkle', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Descobrir', 'spotinder' ); ?></a>
				<a href="<?php echo esc_url( spotinder_page_url( 'matches' ) ); ?>" class="btn-ghost relative hidden sm:inline-flex">
					<?php echo spotinder_icon( 'heart', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Matches', 'spotinder' ); ?>
					<span data-island="MatchBadge" class="contents"></span>
				</a>
				<?php $spotinder_photo = spotinder_photo_url( get_current_user_id() ); ?>
				<a href="<?php echo esc_url( spotinder_page_url( 'profile' ) ); ?>" class="grid size-9 place-items-center overflow-hidden rounded-full text-sm font-semibold text-white ring-2 ring-white/10" style="background: <?php echo esc_attr( spotinder_gradient( wp_get_current_user()->user_login ) ); ?>" aria-label="<?php esc_attr_e( 'Meu perfil', 'spotinder' ); ?>">
					<?php if ( $spotinder_photo ) : ?>
						<img src="<?php echo esc_url( $spotinder_photo ); ?>" alt="" class="size-full object-cover">
					<?php else : ?>
						<?php echo esc_html( spotinder_initials( wp_get_current_user()->display_name ) ); ?>
					<?php endif; ?>
				</a>
			<?php elseif ( is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( admin_url() ); ?>" class="btn-ghost" data-no-swup><?php esc_html_e( 'Painel', 'spotinder' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( spotinder_login_url() ); ?>" class="btn-primary" data-no-swup>
					<?php echo spotinder_icon( 'google', 'size-5' ); // phpcs:ignore ?>
					<span class="hidden sm:inline"><?php echo esc_html( spotinder_opt( 'login_label' ) ); ?></span>
					<span class="sm:hidden"><?php esc_html_e( 'Entrar', 'spotinder' ); ?></span>
				</a>
			<?php endif; ?>

			<button type="button" class="btn-icon md:hidden" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu" aria-label="<?php esc_attr_e( 'Abrir menu', 'spotinder' ); ?>">
				<?php echo spotinder_icon( 'menu' ); // phpcs:ignore ?>
			</button>
		</div>
	</div>

	<div id="mobile-menu" class="nav-mobile hidden border-t border-white/5 md:hidden">
		<div class="container-site py-4">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'fallback_cb'    => 'spotinder_fallback_menu',
					'depth'          => 1,
				)
			);
			?>
		</div>
	</div>
</header>

<main id="swup" class="transition-fade flex-1">
	<?php get_template_part( 'template-parts/notice' ); ?>
