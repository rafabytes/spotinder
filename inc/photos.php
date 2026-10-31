<?php
/**
 * Foto de perfil: nenhuma (padrão), foto da conta Google ou foto enviada.
 *
 * Privacidade:
 * - Opt-in: ninguém aparece com foto até escolher uma e aceitar o aviso.
 * - Foto do Google: guardamos só o endereço da imagem pública (hospedada pelo Google).
 * - Foto enviada: recortada em 640×640 e regravada (remove EXIF, inclusive localização GPS);
 *   o original é descartado. Fica fora da Biblioteca de Mídia (não aparece em /wp/v2/media),
 *   com nome aleatório, e é apagada ao trocar/remover a foto ou excluir a conta.
 * - Nenhum reconhecimento facial ou biometria.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

const SPOTINDER_PHOTO_MAX_BYTES = 5 * MB_IN_BYTES;
const SPOTINDER_PHOTO_SIZE      = 640;

/**
 * Pasta das fotos enviadas.
 *
 * @return array{dir:string,url:string}
 */
function spotinder_photo_dir() {
	$uploads = wp_upload_dir( null, false );
	$dir     = trailingslashit( $uploads['basedir'] ) . 'spotinder-photos';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		// Sem listagem de diretório.
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
	return array(
		'dir' => $dir,
		'url' => trailingslashit( $uploads['baseurl'] ) . 'spotinder-photos',
	);
}

/**
 * URL da foto exibida (ou '' quando a pessoa não escolheu foto).
 *
 * @param int $user_id Usuário.
 */
function spotinder_photo_url( $user_id ) {
	$source = get_user_meta( $user_id, 'spotinder_photo_source', true );

	if ( 'google' === $source ) {
		return esc_url_raw( (string) get_user_meta( $user_id, 'spotinder_google_photo', true ) );
	}

	// Contas antigas, de quando o login era pelo Spotify.
	if ( 'spotify' === $source ) {
		return esc_url_raw( (string) get_user_meta( $user_id, 'spotinder_spotify_photo', true ) );
	}

	if ( 'upload' === $source ) {
		return spotinder_uploaded_photo_url( $user_id );
	}

	return '';
}

/**
 * URL da foto enviada (mesmo que ainda não esteja em uso).
 *
 * @param int $user_id Usuário.
 */
function spotinder_uploaded_photo_url( $user_id ) {
	$file = (string) get_user_meta( $user_id, 'spotinder_photo_file', true );
	$base = spotinder_photo_dir();
	if ( ! $file || ! file_exists( $base['dir'] . '/' . $file ) ) {
		return '';
	}
	return $base['url'] . '/' . rawurlencode( $file );
}

/**
 * Apaga a foto enviada (se houver).
 *
 * @param int $user_id Usuário.
 */
function spotinder_delete_photo_file( $user_id ) {
	$file = (string) get_user_meta( $user_id, 'spotinder_photo_file', true );
	if ( $file && preg_match( '/^[A-Za-z0-9]+\.jpg$/', $file ) ) {
		wp_delete_file( spotinder_photo_dir()['dir'] . '/' . $file );
	}
	delete_user_meta( $user_id, 'spotinder_photo_file' );
}

/**
 * Registra/remove o aceite do aviso de foto.
 *
 * @param int  $user_id Usuário.
 * @param bool $agreed  Aceitou?
 */
function spotinder_set_photo_consent( $user_id, $agreed ) {
	$consents = get_user_meta( $user_id, 'spotinder_consents', true );
	$consents = is_array( $consents ) ? $consents : array();

	$consents['photo'] = $agreed ? current_time( 'mysql' ) : '';
	update_user_meta( $user_id, 'spotinder_consents', $consents );
}

/**
 * Define a origem da foto.
 *
 * @param int    $user_id Usuário.
 * @param string $source  none|google|upload.
 * @param bool   $consent Aceite do aviso (obrigatório para google/upload).
 * @return true|WP_Error
 */
function spotinder_set_photo_source( $user_id, $source, $consent ) {
	if ( ! in_array( $source, array( 'none', 'google', 'upload' ), true ) ) {
		return new WP_Error( 'spotinder_photo', 'Opção de foto inválida.', array( 'status' => 400 ) );
	}

	if ( 'none' === $source ) {
		// Minimização: sem foto escolhida, a enviada é apagada.
		spotinder_delete_photo_file( $user_id );
		update_user_meta( $user_id, 'spotinder_photo_source', 'none' );
		spotinder_set_photo_consent( $user_id, false );
		return true;
	}

	if ( ! $consent ) {
		return new WP_Error( 'spotinder_photo', 'Para exibir uma foto, confirme o aviso sobre a foto.', array( 'status' => 400 ) );
	}

	if ( 'google' === $source && ! get_user_meta( $user_id, 'spotinder_google_photo', true ) ) {
		return new WP_Error( 'spotinder_photo', 'Sua conta Google não tem foto. Envie uma foto ou fique só com as iniciais.', array( 'status' => 400 ) );
	}

	if ( 'upload' === $source && ! get_user_meta( $user_id, 'spotinder_photo_file', true ) ) {
		return new WP_Error( 'spotinder_photo', 'Envie uma foto primeiro.', array( 'status' => 400 ) );
	}

	if ( 'google' === $source ) {
		spotinder_delete_photo_file( $user_id );
	}

	update_user_meta( $user_id, 'spotinder_photo_source', $source );
	spotinder_set_photo_consent( $user_id, true );
	return true;
}

/**
 * Recebe, valida, recorta e grava a foto enviada.
 *
 * @param int   $user_id Usuário.
 * @param array $file    Item de $_FILES.
 * @return true|WP_Error
 */
function spotinder_save_uploaded_photo( $user_id, $file ) {
	if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'spotinder_photo', 'Não recebemos a imagem. Tente de novo.', array( 'status' => 400 ) );
	}

	if ( (int) $file['size'] > SPOTINDER_PHOTO_MAX_BYTES ) {
		return new WP_Error( 'spotinder_photo', 'A imagem deve ter no máximo 5 MB.', array( 'status' => 400 ) );
	}

	// Confere o conteúdo real do arquivo, não só a extensão.
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
	$info  = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! $check['type'] || ! $info || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return new WP_Error( 'spotinder_photo', 'Envie uma imagem JPG, PNG ou WebP.', array( 'status' => 400 ) );
	}
	if ( $info[0] < 200 || $info[1] < 200 ) {
		return new WP_Error( 'spotinder_photo', 'A imagem precisa ter pelo menos 200×200 px.', array( 'status' => 400 ) );
	}

	$editor = wp_get_image_editor( $file['tmp_name'] );
	if ( is_wp_error( $editor ) ) {
		return new WP_Error( 'spotinder_photo', 'Não foi possível processar a imagem.', array( 'status' => 400 ) );
	}

	// Corrige a rotação de fotos de celular antes de descartar os metadados.
	if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
		$editor->maybe_exif_rotate();
	}
	$editor->resize( SPOTINDER_PHOTO_SIZE, SPOTINDER_PHOTO_SIZE, true );
	$editor->set_quality( 82 );

	$base  = spotinder_photo_dir();
	$name  = wp_generate_password( 32, false ) . '.jpg';
	$saved = $editor->save( $base['dir'] . '/' . $name, 'image/jpeg' );
	wp_delete_file( $file['tmp_name'] );

	if ( is_wp_error( $saved ) ) {
		return new WP_Error( 'spotinder_photo', 'Não foi possível salvar a imagem.', array( 'status' => 500 ) );
	}

	spotinder_delete_photo_file( $user_id );
	update_user_meta( $user_id, 'spotinder_photo_file', $name );
	return true;
}

/**
 * Remove metadados (EXIF/GPS) também quando o editor for o Imagick.
 */
add_filter( 'image_strip_meta', '__return_true' );

/**
 * Exclusão de conta: apaga a foto enviada.
 */
add_action( 'delete_user', 'spotinder_delete_photo_file' );

/**
 * Moderação: remove a foto de alguém (ex.: denúncia de foto imprópria).
 *
 * @param int $user_id Usuário.
 */
function spotinder_moderate_remove_photo( $user_id ) {
	spotinder_delete_photo_file( $user_id );
	update_user_meta( $user_id, 'spotinder_photo_source', 'none' );
	spotinder_set_photo_consent( $user_id, false );
}
