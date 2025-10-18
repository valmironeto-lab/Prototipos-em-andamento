<?php
/**
 * Gerencia o processamento de ações de Templates.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Templates_Handler extends Abstract_Handler {

    public function register_hooks() {
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function handle_actions() {
        $page = $_GET['page'] ?? '';
		if ( 'bluesendmail-templates' !== $page ) {
            return;
        }

        if ( isset( $_POST['bsm_save_template'] ) ) $this->handle_save_template();
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['template'] ) ) $this->handle_delete_template();
    }

    private function handle_save_template() {
        if ( ! isset( $_POST['bsm_save_template_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_save_template_nonce_field'], 'bsm_save_template_nonce_action' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
        if ( ! current_user_can( 'bsm_manage_campaigns' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
        
        global $wpdb;
        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $content = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );

        if ( empty( $name ) ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('O nome não pode estar vazio.', 'bluesendmail')], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-templates&action=' . ( $template_id ? 'edit&template=' . $template_id : 'new' ) ) );
            exit;
        }

        $data = array( 'name' => $name, 'content' => $content );

        if ( $template_id ) {
            $result = $wpdb->update( "{$wpdb->prefix}bluesendmail_templates", $data, array( 'template_id' => $template_id ) );
            $message = __( 'Template atualizado com sucesso!', 'bluesendmail' );
        } else {
            $data['created_at'] = current_time( 'mysql', 1 );
            $result = $wpdb->insert( "{$wpdb->prefix}bluesendmail_templates", $data );
            $message = __( 'Template adicionado com sucesso!', 'bluesendmail' );
        }

        if ( false === $result ) {
            $db_error = ! empty( $wpdb->last_error ) ? ' ' . sprintf( __( 'Erro: %s', 'bluesendmail' ), $wpdb->last_error ) : '';
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Ocorreu um erro ao salvar na base de dados.', 'bluesendmail') . $db_error], 30);
            $redirect_url = admin_url( 'admin.php?page=bluesendmail-templates&action=' . ( $template_id ? 'edit&template=' . $template_id : 'new' ) );
        } else {
            set_transient('bsm_admin_notice', ['type' => 'success', 'message' => $message], 30);
            $redirect_url = admin_url( 'admin.php?page=bluesendmail-templates' );
        }
        wp_safe_redirect( $redirect_url );
        exit;
    }
    
    private function handle_delete_template() {
		$template_id = absint( $_GET['template'] );
		$nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'bsm_delete_template_' . $template_id ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
		if ( ! current_user_can( 'bsm_manage_campaigns' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }

		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}bluesendmail_templates", array( 'template_id' => $template_id ), array( '%d' ) );
		
        set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('Template excluído com sucesso!', 'bluesendmail')], 30);
        wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-templates' ) );
		exit;
	}
}
