<?php
/**
 * Admin: ferramentas, moderação de denúncias e colunas de usuários.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		$parent = function_exists( 'acf_add_options_page' ) ? 'spotinder-settings' : 'tools.php';
		add_submenu_page( $parent, 'Spotinder — Ferramentas', 'Ferramentas e moderação', 'manage_options', 'spotinder-tools', 'spotinder_render_tools_page' );
	},
	100
);

/**
 * Ações da página de ferramentas.
 */
add_action(
	'admin_post_spotinder_tool',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'spotinder_tool' );

		$tool = isset( $_POST['tool'] ) ? sanitize_key( $_POST['tool'] ) : '';
		$msg  = '';

		switch ( $tool ) {
			case 'seed_content':
				spotinder_install_tables();
				spotinder_seed_pages();
				spotinder_seed_options();
				spotinder_seed_stories();
				spotinder_seed_events();
				spotinder_seed_menus();
				spotinder_seed_site_icon();
				spotinder_seed_hero_cards();
				flush_rewrite_rules();
				$msg = 'Páginas, histórias, eventos, menus e ícone do site verificados/criados.';
				break;
			case 'reset_texts':
				spotinder_seed_options( true );
				$msg = 'Textos e opções restaurados para o padrão do tema (credenciais do Spotify mantidas).';
				break;
			case 'seed_demo':
				$msg = sprintf( '%d usuários de teste criados/atualizados.', spotinder_seed_demo_users() );
				break;
			case 'remove_demo':
				$msg = sprintf( '%d usuários de teste removidos.', spotinder_remove_demo_users() );
				break;
			case 'enrich_demo':
				$result = spotinder_enrich_demo_catalog();
				$msg    = is_wp_error( $result ) ? 'Erro: ' . $result->get_error_message() : sprintf( 'Catálogo atualizado: %d itens encontrados no Spotify, %d não encontrados.', $result['ok'], $result['fail'] );
				break;
			case 'retention':
				$msg = sprintf( '%d contas inativas apagadas.', spotinder_run_retention() );
				break;
		}

		wp_safe_redirect( add_query_arg( 'sp_msg', rawurlencode( $msg ), admin_url( 'admin.php?page=spotinder-tools' ) ) );
		exit;
	}
);

/**
 * Ações de moderação.
 */
add_action(
	'admin_post_spotinder_moderate',
	function () {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'spotinder_moderate' );

		$report_id = isset( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;
		$action    = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$report = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . spotinder_table( 'reports' ) . ' WHERE id = %d', $report_id ) );

		if ( $report ) {
			if ( 'ban' === $action ) {
				update_user_meta( (int) $report->target_id, 'spotinder_banned', current_time( 'mysql' ) );
				update_user_meta( (int) $report->target_id, 'spotinder_visible', 0 );
				WP_Session_Tokens::get_instance( (int) $report->target_id )->destroy_all();
			}
			if ( 'unban' === $action ) {
				delete_user_meta( (int) $report->target_id, 'spotinder_banned' );
			}
			if ( 'remove_photo' === $action ) {
				spotinder_moderate_remove_photo( (int) $report->target_id );
			}
			$status = array(
				'dismiss'      => 'dismissed',
				'ban'          => 'actioned',
				'remove_photo' => 'actioned',
			);
			$wpdb->update( spotinder_table( 'reports' ), array( 'status' => $status[ $action ] ?? 'open' ), array( 'id' => $report_id ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=spotinder-tools#reports' ) );
		exit;
	}
);

/**
 * Página de ferramentas.
 */
function spotinder_render_tools_page() {
	global $wpdb;

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	$stats = array(
		'Membros reais'           => count( get_users( array( 'meta_key' => 'spotinder_spotify_id', 'meta_compare' => 'EXISTS', 'fields' => 'ID', 'number' => -1 ) ) ) - count( get_users( array( 'meta_key' => 'spotinder_is_demo', 'fields' => 'ID', 'number' => -1 ) ) ),
		'Usuários de teste'       => count( get_users( array( 'meta_key' => 'spotinder_is_demo', 'fields' => 'ID', 'number' => -1 ) ) ),
		'Matches'                 => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . spotinder_table( 'matches' ) ),
		'Convites'                => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . spotinder_table( 'invites' ) ),
		'Denúncias abertas'       => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . spotinder_table( 'reports' ) . " WHERE status = 'open'" ),
	);
	$reports = $wpdb->get_results( 'SELECT * FROM ' . spotinder_table( 'reports' ) . " ORDER BY status = 'open' DESC, created_at DESC LIMIT 50" );
	// phpcs:enable

	$reasons = spotinder_report_reasons();
	$msg     = isset( $_GET['sp_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$button = function ( $tool, $label, $class = 'button', $confirm = '' ) {
		printf(
			'<form method="post" action="%s" style="display:inline-block;margin:0 8px 8px 0">%s<input type="hidden" name="action" value="spotinder_tool"><input type="hidden" name="tool" value="%s"><button class="%s" %s>%s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'spotinder_tool', '_wpnonce', true, false ),
			esc_attr( $tool ),
			esc_attr( $class ),
			$confirm ? 'onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '',
			esc_html( $label )
		);
	};
	?>
	<div class="wrap">
		<h1>Spotinder — Ferramentas e moderação</h1>
		<?php if ( $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! spotinder_spotify_configured() ) : ?>
			<div class="notice notice-warning"><p>O login com Spotify ainda não está configurado. Preencha <strong>Client ID</strong> e <strong>Client Secret</strong> em Spotinder &gt; Spotify API. Redirect URI: <code><?php echo esc_html( spotinder_redirect_uri() ); ?></code></p></div>
		<?php endif; ?>

		<h2>Visão geral</h2>
		<table class="widefat striped" style="max-width:520px">
			<?php foreach ( $stats as $label => $value ) : ?>
				<tr><td><?php echo esc_html( $label ); ?></td><td><strong><?php echo esc_html( $value ); ?></strong></td></tr>
			<?php endforeach; ?>
		</table>

		<h2>Conteúdo</h2>
		<p>Cria o que estiver faltando: páginas do app, páginas legais, histórias da campanha, eventos de exemplo e menus. Nada existente é sobrescrito.</p>
		<?php
		$button( 'seed_content', 'Criar conteúdo que estiver faltando', 'button button-primary' );
		$button( 'reset_texts', 'Restaurar textos padrão', 'button', 'Isso sobrescreve todos os textos editados nas páginas de opções. Continuar?' );
		?>

		<h2>Usuários de teste</h2>
		<p>14 perfis fictícios com artistas e músicas reais (marcados como "perfil de teste" no app). Com as credenciais do Spotify configuradas, o botão "Completar com o Spotify" busca imagens e links oficiais dos artistas e faixas.</p>
		<?php
		$button( 'seed_demo', 'Criar/atualizar usuários de teste', 'button button-primary' );
		$button( 'enrich_demo', 'Completar com dados do Spotify' );
		$button( 'remove_demo', 'Remover usuários de teste', 'button button-link-delete', 'Remover todos os usuários de teste e seus matches?' );
		?>

		<h2>Retenção</h2>
		<p>Contas sem acesso há mais de <?php echo (int) spotinder_opt( 'inactive_delete_days' ); ?> dias são apagadas diariamente (exceto usuários de teste).</p>
		<?php $button( 'retention', 'Rodar limpeza agora' ); ?>

		<h2 id="reports">Denúncias</h2>
		<table class="widefat striped">
			<thead><tr><th>Data</th><th>Denunciado</th><th>Motivo</th><th>Detalhes</th><th>Status</th><th>Ações</th></tr></thead>
			<tbody>
			<?php if ( ! $reports ) : ?>
				<tr><td colspan="6">Nenhuma denúncia.</td></tr>
			<?php endif; ?>
			<?php
			foreach ( $reports as $r ) :
				$target = get_userdata( (int) $r->target_id );
				$link   = fn( $do ) => wp_nonce_url( admin_url( 'admin-post.php?action=spotinder_moderate&report=' . (int) $r->id . '&do=' . $do ), 'spotinder_moderate' );
				$banned = $target && get_user_meta( $target->ID, 'spotinder_banned', true );
				?>
				<tr>
					<td><?php echo esc_html( spotinder_date( $r->created_at, 'd/m/Y H:i' ) ); ?></td>
					<td>
						<?php $photo = $target ? spotinder_photo_url( $target->ID ) : ''; ?>
						<?php if ( $photo ) : ?>
							<img src="<?php echo esc_url( $photo ); ?>" alt="" width="40" height="40" style="border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:6px">
						<?php endif; ?>
						<?php echo $target ? esc_html( $target->display_name . ' (' . $target->user_login . ')' ) : '<em>conta apagada</em>'; ?><?php echo $banned ? ' <strong style="color:#b32d2e">suspenso</strong>' : ''; ?>
					</td>
					<td><?php echo esc_html( $reasons[ $r->reason ] ?? $r->reason ); ?></td>
					<td><?php echo esc_html( $r->details ); ?></td>
					<td><?php echo esc_html( $r->status ); ?></td>
					<td>
						<?php if ( $target ) : ?>
							<?php if ( $banned ) : ?>
								<a href="<?php echo esc_url( $link( 'unban' ) ); ?>">Reativar</a> ·
							<?php else : ?>
								<a href="<?php echo esc_url( $link( 'ban' ) ); ?>" style="color:#b32d2e" onclick="return confirm('Suspender esta conta?')">Suspender</a> ·
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( $photo ) : ?>
							<a href="<?php echo esc_url( $link( 'remove_photo' ) ); ?>" onclick="return confirm('Remover a foto deste perfil?')">Remover foto</a> ·
						<?php endif; ?>
						<a href="<?php echo esc_url( $link( 'dismiss' ) ); ?>">Arquivar</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Colunas extras na lista de usuários.
 */
add_filter(
	'manage_users_columns',
	function ( $cols ) {
		$cols['spotinder'] = 'Spotinder';
		return $cols;
	}
);

add_filter(
	'manage_users_custom_column',
	function ( $output, $col, $user_id ) {
		if ( 'spotinder' !== $col || ! spotinder_is_member( $user_id ) ) {
			return $output;
		}
		$parts = array();
		if ( get_user_meta( $user_id, 'spotinder_is_demo', true ) ) {
			$parts[] = '🧪 teste';
		}
		if ( get_user_meta( $user_id, 'spotinder_banned', true ) ) {
			$parts[] = '⛔ suspenso';
		}
		$parts[] = count( spotinder_get_artists( $user_id ) ) . ' artistas';
		$last    = (int) get_user_meta( $user_id, 'spotinder_last_active', true );
		$parts[] = $last ? 'ativo ' . human_time_diff( $last ) . ' atrás' : '';
		return esc_html( implode( ' · ', array_filter( $parts ) ) );
	},
	10,
	3
);
