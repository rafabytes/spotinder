<?php
/**
 * Tipos de post: Eventos e Histórias (campanha).
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'spotinder_register_post_types' );

/**
 * Registra CPTs e taxonomias.
 */
function spotinder_register_post_types() {
	register_post_type(
		'spot_event',
		array(
			'labels'       => array(
				'name'          => __( 'Eventos', 'spotinder' ),
				'singular_name' => __( 'Evento', 'spotinder' ),
				'add_new_item'  => __( 'Adicionar evento', 'spotinder' ),
				'edit_item'     => __( 'Editar evento', 'spotinder' ),
				'all_items'     => __( 'Todos os eventos', 'spotinder' ),
				'menu_name'     => __( 'Eventos', 'spotinder' ),
			),
			'public'       => true,
			'has_archive'  => 'eventos',
			'rewrite'      => array(
				'slug'       => 'eventos',
				'with_front' => false,
			),
			'menu_icon'    => 'dashicons-tickets-alt',
			'menu_position'=> 21,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'show_in_rest' => true,
		)
	);

	register_taxonomy(
		'spot_event_type',
		'spot_event',
		array(
			'labels'            => array(
				'name'          => __( 'Tipos de evento', 'spotinder' ),
				'singular_name' => __( 'Tipo de evento', 'spotinder' ),
			),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'eventos/tipo' ),
		)
	);

	register_post_type(
		'spot_story',
		array(
			'labels'       => array(
				'name'          => __( 'Histórias', 'spotinder' ),
				'singular_name' => __( 'História', 'spotinder' ),
				'add_new_item'  => __( 'Adicionar história', 'spotinder' ),
				'edit_item'     => __( 'Editar história', 'spotinder' ),
				'all_items'     => __( 'Todas as histórias', 'spotinder' ),
				'menu_name'     => __( 'Histórias (campanha)', 'spotinder' ),
			),
			'public'       => true,
			'has_archive'  => 'historias',
			'rewrite'      => array(
				'slug'       => 'historias',
				'with_front' => false,
			),
			'menu_icon'    => 'dashicons-format-quote',
			'menu_position'=> 22,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'show_in_rest' => true,
		)
	);
}

/**
 * Arquivo de eventos: só os próximos, do mais próximo ao mais distante.
 */
add_action(
	'pre_get_posts',
	function ( WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( 'spot_event' ) || $query->is_tax( 'spot_event_type' ) ) {
			$query->set( 'meta_key', 'event_start' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'ASC' );
			$query->set( 'posts_per_page', 24 );
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => 'event_start',
						'value'   => current_time( 'mysql' ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				)
			);
		}
	}
);

/**
 * Coluna de data na listagem de eventos do admin.
 */
add_filter(
	'manage_spot_event_posts_columns',
	function ( $cols ) {
		$cols['event_start'] = __( 'Data', 'spotinder' );
		$cols['event_rsvp']  = __( 'Presenças', 'spotinder' );
		return $cols;
	}
);

add_action(
	'manage_spot_event_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'event_start' === $col ) {
			echo esc_html( spotinder_date( get_post_meta( $post_id, 'event_start', true ), 'd/m/Y H:i' ) );
		}
		if ( 'event_rsvp' === $col ) {
			$counts = spotinder_event_rsvp_counts( $post_id );
			printf( '%d vão · %d interessados', (int) $counts['going'], (int) $counts['interested'] );
		}
	},
	10,
	2
);

/**
 * As histórias da campanha estão no ar? (Spotinder > Página inicial > Campanha.)
 */
function spotinder_stories_enabled() {
	return (bool) spotinder_opt( 'campaign_enabled' );
}

/**
 * Histórias desligadas: arquivo e páginas voltam para a home (a equipe ainda pode
 * pré-visualizar), saem dos menus e dos sitemaps (WordPress e Rank Math).
 */
add_action(
	'template_redirect',
	function () {
		if ( spotinder_stories_enabled() || current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( is_post_type_archive( 'spot_story' ) || is_singular( 'spot_story' ) ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}
);

add_filter(
	'wp_nav_menu_objects',
	function ( $items ) {
		if ( spotinder_stories_enabled() ) {
			return $items;
		}
		return array_filter( $items, fn( $item ) => 'spot_story' !== $item->object );
	}
);

add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		if ( ! spotinder_stories_enabled() ) {
			unset( $types['spot_story'] );
		}
		return $types;
	}
);

add_filter(
	'rank_math/sitemap/exclude_post_type',
	fn( $exclude, $type ) => 'spot_story' === $type && ! spotinder_stories_enabled() ? true : $exclude,
	10,
	2
);