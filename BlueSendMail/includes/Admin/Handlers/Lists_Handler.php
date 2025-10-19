<?php
/**
 * Gerencia o processamento de ações de Listas.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lists_Handler extends Abstract_Handler {

    public function register_hooks() {
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function handle_actions() {
        $page = $_GET['page'] ?? '';
		if ( 'bluesendmail-lists' !== $page ) {
            return;
        }

        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['list'] ) ) $this->handle_delete_list();
        if ( ( isset( $_POST['action'] ) && 'bulk-delete' === $_POST['action'] ) || ( isset( $_POST['action2'] ) && 'bulk-delete' === $_POST['action2'] ) ) $this->handle_bulk_delete_lists();
        if ( isset( $_POST['bsm_save_list'] ) ) $this->handle_save_list();
    }

	private function handle_delete_list() {
        $list_id = absint( $_GET['list'] );
        $nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'bsm_delete_list_' . $list_id ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
        if ( ! current_user_can( 'bsm_manage_lists' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
    
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}bluesendmail_lists", array( 'list_id' => $list_id ), array( '%d' ) );
        $wpdb->delete( "{$wpdb->prefix}bluesendmail_contact_lists", array( 'list_id' => $list_id ), array( '%d' ) );
        
        set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('Lista excluída com sucesso!', 'bluesendmail')], 30);
        wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-lists' ) );
        exit;
    }

	private function handle_bulk_delete_lists() {
        if ( ! isset( $_POST['bsm_bulk_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_bulk_nonce_field'], 'bsm_bulk_action_nonce' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
        if ( ! current_user_can( 'bsm_manage_lists' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
        
        $list_ids = isset( $_POST['list'] ) ? array_map( 'absint', $_POST['list'] ) : array();
        if ( empty( $list_ids ) ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Nenhum item foi selecionado.', 'bluesendmail')], 30);
        } else {
            global $wpdb;
            $ids_placeholder = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bluesendmail_lists WHERE list_id IN ($ids_placeholder)", $list_ids ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bluesendmail_contact_lists WHERE list_id IN ($ids_placeholder)", $list_ids ) );
            set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('As listas selecionadas foram excluídas com sucesso.', 'bluesendmail')], 30);
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-lists' ) );
        exit;
    }

	private function handle_save_list() {
        if ( ! isset( $_POST['bsm_save_list_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_save_list_nonce_field'], 'bsm_save_list_nonce_action' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
        if ( ! current_user_can( 'bsm_manage_lists' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
        
        global $wpdb;
        $list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
        $data = array( 'name' => sanitize_text_field( $_POST['name'] ?? '' ), 'description' => sanitize_textarea_field( $_POST['description'] ?? '' ) );
    
        if ( empty( $data['name'] ) ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('O nome da lista não pode estar vazio.', 'bluesendmail')], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-lists&action=' . ( $list_id ? 'edit&list=' . $list_id : 'new' ) ) );
            exit;
        }
    
        if ( $list_id ) {
            $result = $wpdb->update( "{$wpdb->prefix}bluesendmail_lists", $data, array( 'list_id' => $list_id ) );
            $message = __( 'Lista atualizada com sucesso!', 'bluesendmail' );
        } else {
            $data['created_at'] = current_time( 'mysql', 1 );
            $result = $wpdb->insert( "{$wpdb->prefix}bluesendmail_lists", $data );
            $message = __( 'Lista adicionada com sucesso!', 'bluesendmail' );
        }
    
        if ( false === $result ) {
            $db_error = ! empty( $wpdb->last_error ) ? ' ' . sprintf( __( 'Erro: %s', 'bluesendmail' ), $wpdb->last_error ) : '';
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Ocorreu um erro ao salvar a lista.', 'bluesendmail') . $db_error], 30);
            $redirect_url = admin_url( 'admin.php?page=bluesendmail-lists&action=' . ( $list_id ? 'edit&list=' . $list_id : 'new' ) );
        } else {
            set_transient('bsm_admin_notice', ['type' => 'success', 'message' => $message], 30);
            $redirect_url = admin_url( 'admin.php?page=bluesendmail-lists' );
        }
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
