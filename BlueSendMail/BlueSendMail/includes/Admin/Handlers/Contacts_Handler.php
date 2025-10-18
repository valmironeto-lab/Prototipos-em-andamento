<?php
/**
 * Gerencia o processamento de ações de Contatos.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Contacts_Handler extends Abstract_Handler {

    public function register_hooks() {
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function handle_actions() {
        $page = $_GET['page'] ?? '';
		if ( 'bluesendmail-contacts' !== $page ) {
            return;
        }

        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['contact'] ) ) $this->handle_delete_contact();
        if ( ( isset( $_POST['action'] ) && 'bulk-delete' === $_POST['action'] ) || ( isset( $_POST['action2'] ) && 'bulk-delete' === $_POST['action2'] ) ) $this->handle_bulk_delete_contacts();
        if ( isset( $_POST['bsm_save_contact'] ) ) $this->handle_save_contact();
    }

    private function handle_save_contact() {
		if ( ! isset( $_POST['bsm_save_contact_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_save_contact_nonce_field'], 'bsm_save_contact_nonce_action' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
		if ( ! current_user_can( 'bsm_manage_contacts' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
		
        global $wpdb;
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$data = array('email' => sanitize_email( $_POST['email'] ),'first_name' => sanitize_text_field( $_POST['first_name'] ),'last_name' => sanitize_text_field( $_POST['last_name'] ),'company' => sanitize_text_field( $_POST['company'] ),'job_title' => sanitize_text_field( $_POST['job_title'] ),'segment' => sanitize_text_field( $_POST['segment'] ),'status' => sanitize_key( $_POST['status'] ));
		if ( empty( $data['email'] ) ) { 
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('O e-mail é obrigatório.', 'bluesendmail')], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-contacts&action=new' ) ); 
            exit; 
        }
		
        $is_new_contact = ! $contact_id;
        $original_lists = [];
        if( ! $is_new_contact ){
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT list_id FROM {$wpdb->prefix}bluesendmail_contact_lists WHERE contact_id = %d", $contact_id ), ARRAY_A );
			if ( $results ) { $original_lists = wp_list_pluck( $results, 'list_id' ); }
        }

        if ( $contact_id ) {
			$result = $wpdb->update( "{$wpdb->prefix}bluesendmail_contacts", $data, array( 'contact_id' => $contact_id ) );
		} else {
			$result = $wpdb->insert( "{$wpdb->prefix}bluesendmail_contacts", $data );
			if ( $result ) { $contact_id = $wpdb->insert_id; }
		}

		if ( false === $result || ! $contact_id ) { 
            $db_error = ! empty( $wpdb->last_error ) ? ' ' . sprintf( __( 'Erro: %s', 'bluesendmail' ), $wpdb->last_error ) : '';
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Falha ao salvar o contato.', 'bluesendmail') . $db_error], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-contacts&action=' . ( $contact_id ? 'edit&contact=' . $contact_id : 'new' ) ) ); 
            exit;
        }

        set_transient('bsm_admin_notice', ['type' => 'success', 'message' => $is_new_contact ? __('Contato adicionado com sucesso!', 'bluesendmail') : __('Contato atualizado com sucesso!', 'bluesendmail')], 30);

		$submitted_lists = isset( $_POST['lists'] ) ? array_map( 'absint', $_POST['lists'] ) : array();
		$wpdb->delete( "{$wpdb->prefix}bluesendmail_contact_lists", array( 'contact_id' => $contact_id ), array( '%d' ) );
		
        if ( ! empty( $submitted_lists ) ) { 
            foreach ( $submitted_lists as $list_id ) {
                $wpdb->insert( "{$wpdb->prefix}bluesendmail_contact_lists", array( 'contact_id' => $contact_id, 'list_id' => $list_id ), array( '%d', '%d' ) );
                
                if( !in_array($list_id, $original_lists) ){
                    $this->plugin->log_event( 'debug', 'automation_dispatch', sprintf('Disparando ação bsm_contact_added_to_list para contato #%d e lista #%d.', $contact_id, $list_id) );
                    do_action('bsm_contact_added_to_list', $contact_id, $list_id);
                }
            }
        }
		wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-contacts' ) );
		exit;
	}

    private function handle_delete_contact() {
		$contact_id = absint( $_GET['contact'] );
		$nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'bsm_delete_contact_' . $contact_id ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
		if ( ! current_user_can( 'bsm_manage_contacts' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }

		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}bluesendmail_contacts", array( 'contact_id' => $contact_id ), array( '%d' ) );
		$wpdb->delete( "{$wpdb->prefix}bluesendmail_contact_lists", array( 'contact_id' => $contact_id ), array( '%d' ) );
		
        set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('Contato excluído com sucesso!', 'bluesendmail')], 30);
        wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-contacts' ) );
		exit;
	}

	private function handle_bulk_delete_contacts() {
		if ( ! isset( $_POST['bsm_bulk_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_bulk_nonce_field'], 'bsm_bulk_action_nonce' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
		if ( ! current_user_can( 'bsm_manage_contacts' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
		
        $contact_ids = isset( $_POST['contact'] ) ? array_map( 'absint', $_POST['contact'] ) : array();
		if ( empty( $contact_ids ) ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Nenhum item foi selecionado.', 'bluesendmail')], 30);
        } else {
            global $wpdb;
            $ids_placeholder = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bluesendmail_contacts WHERE contact_id IN ($ids_placeholder)", $contact_ids ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bluesendmail_contact_lists WHERE contact_id IN ($ids_placeholder)", $contact_ids ) );
            set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('Os contatos selecionados foram excluídos com sucesso.', 'bluesendmail')], 30);
        }
		wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-contacts' ) );
		exit;
	}
}
