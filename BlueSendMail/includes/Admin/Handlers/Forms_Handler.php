<?php
/**
 * Gerencia o processamento de ações de Formulários.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Forms_Handler extends Abstract_Handler {

    public function register_hooks() {
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function handle_actions() {
        $page = $_GET['page'] ?? '';
		if ( 'bluesendmail-forms' !== $page ) {
            return;
        }

        if ( isset( $_POST['bsm_save_form'] ) ) $this->handle_save_form();
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['form'] ) ) $this->handle_delete_form();
        if ( ( isset( $_POST['action'] ) && 'bulk-delete' === $_POST['action'] ) || ( isset( $_POST['action2'] ) && 'bulk-delete' === $_POST['action2'] ) ) $this->handle_bulk_delete_forms();
    }

    private function handle_save_form() {
        if ( ! isset( $_POST['bsm_save_form_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_save_form_nonce_field'], 'bsm_save_form_nonce_action' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
        if ( ! current_user_can( 'bsm_manage_lists' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }

        global $wpdb;
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        
        $data = array(
            'title'           => sanitize_text_field( $_POST['title'] ),
            'list_id'         => absint( $_POST['list_id'] ),
            'fields'          => isset( $_POST['bsm_form_fields'] ) ? maybe_serialize( array_map( 'sanitize_key', (array) $_POST['bsm_form_fields'] ) ) : null,
            'content'         => sanitize_textarea_field( $_POST['content'] ),
            'button_text'     => sanitize_text_field( $_POST['button_text'] ),
            'button_color'    => sanitize_hex_color( $_POST['button_color'] ),
            'success_message' => sanitize_text_field( $_POST['success_message'] ),
            'error_message'   => sanitize_text_field( $_POST['error_message'] ),
        );

        if ( empty( $data['title'] ) || empty( $data['list_id'] ) ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Título e Lista são obrigatórios.', 'bluesendmail')], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-forms&action=' . ( $form_id ? 'edit&form=' . $form_id : 'new' ) ) );
            exit;
        }

        if ( $form_id ) {
            $wpdb->update( "{$wpdb->prefix}bluesendmail_forms", $data, array( 'form_id' => $form_id ) );
        } else {
            $data['created_at'] = current_time( 'mysql', 1 );
            $wpdb->insert( "{$wpdb->prefix}bluesendmail_forms", $data );
        }

        set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('Formulário salvo com sucesso!', 'bluesendmail')], 30);
        wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-forms' ) );
        exit;
    }

    private function handle_delete_form() {
        $form_id = absint( $_GET['form'] );
        $nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'bsm_delete_form_' . $form_id ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
        if ( ! current_user_can( 'bsm_manage_lists' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }

        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}bluesendmail_forms", array( 'form_id' => $form_id ), array( '%d' ) );

        set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('Formulário excluído com sucesso!', 'bluesendmail')], 30);
        wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-forms' ) );
        exit;
    }

    private function handle_bulk_delete_forms() {
		if ( ! isset( $_POST['bsm_bulk_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_bulk_nonce_field'], 'bsm_bulk_action_nonce' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
		if ( ! current_user_can( 'bsm_manage_lists' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
		
        $form_ids = isset( $_POST['form'] ) ? array_map( 'absint', $_POST['form'] ) : array();
		if ( ! empty( $form_ids ) ) {
            global $wpdb;
            $ids_placeholder = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bluesendmail_forms WHERE form_id IN ($ids_placeholder)", $form_ids ) );
            set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('Os formulários selecionados foram excluídos com sucesso.', 'bluesendmail')], 30);
        }
		wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-forms' ) );
		exit;
	}
}
