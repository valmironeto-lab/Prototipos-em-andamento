<?php
/**
 * Gerencia o processamento de ações de Automações.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Automations_Handler extends Abstract_Handler {

    public function register_hooks() {
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function handle_actions() {
        $page = $_GET['page'] ?? '';
		if ( 'bluesendmail-automations' !== $page ) {
            return;
        }

        if ( isset( $_POST['bsm_save_automation'] ) ) $this->handle_save_automation();
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['automation'] ) ) $this->handle_delete_automation();
    }

    private function handle_save_automation() {
		if ( ! isset( $_POST['bsm_save_automation_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_save_automation_nonce_field'], 'bsm_save_automation_nonce_action' ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
		if ( ! current_user_can( 'bsm_manage_campaigns' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }
	
		global $wpdb;
		$automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;
		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$status = sanitize_key( $_POST['status'] ?? 'inactive' );
		$trigger_list_id = absint( $_POST['trigger_list_id'] ?? 0 );
		$steps = isset( $_POST['steps'] ) && is_array( $_POST['steps'] ) ? $_POST['steps'] : array();
	
		if ( empty($name) || empty($trigger_list_id) ) {
			set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Nome e Gatilho da Automação são obrigatórios.', 'bluesendmail')], 30);
			wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-automations&action=' . ( $automation_id ? 'edit&automation=' . $automation_id : 'new' ) ) );
			exit;
		}
	
		$data = array(
			'name' => $name,
			'status' => $status,
			'trigger_type' => 'contact_added_to_list',
			'trigger_settings' => maybe_serialize( array( 'list_id' => $trigger_list_id ) ),
		);
	
		if ( $automation_id ) {
			$wpdb->update( "{$wpdb->prefix}bluesendmail_automations", $data, array( 'automation_id' => $automation_id ) );
		} else {
			$data['created_at'] = current_time( 'mysql', 1 );
			$wpdb->insert( "{$wpdb->prefix}bluesendmail_automations", $data );
			$automation_id = $wpdb->insert_id;
		}
	
		if ( ! $automation_id ) {
			set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Ocorreu um erro ao salvar a automação.', 'bluesendmail')], 30);
			wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-automations&action=new' ) );
			exit;
		}
	
		$wpdb->delete( "{$wpdb->prefix}bluesendmail_automation_steps", array( 'automation_id' => $automation_id ), array( '%d' ) );
        
        if ( ! empty( $steps ) ) {
            $order = 0;
            foreach ( $steps as $step_data ) {
                if ( ! isset( $step_data['type'] ) ) continue;

                $settings = [];
                $type = sanitize_key( $step_data['type'] );

                if ( 'action' === $type ) {
                    $settings['campaign_id'] = absint( $step_data['campaign_id'] ?? 0 );
                } elseif ( 'delay' === $type ) {
                    $settings['value'] = absint( $step_data['value'] ?? 1 );
                    $settings['unit'] = sanitize_key( $step_data['unit'] ?? 'day' );
                }

                $wpdb->insert(
                    "{$wpdb->prefix}bluesendmail_automation_steps",
                    array(
                        'automation_id' => $automation_id,
                        'step_order'    => $order,
                        'step_type'     => $type,
                        'step_settings' => maybe_serialize( $settings ),
                    )
                );
                $order++;
            }
        }
	
		set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __( 'Automação salva com sucesso!', 'bluesendmail' )], 30);
		wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-automations' ) );
		exit;
	}

	private function handle_delete_automation() {
		$automation_id = absint( $_GET['automation'] );
		$nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'bsm_delete_automation_' . $automation_id ) ) { wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) ); }
		if ( ! current_user_can( 'bsm_manage_campaigns' ) ) { wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) ); }

		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}bluesendmail_automations", array( 'automation_id' => $automation_id ), array( '%d' ) );
		$wpdb->delete( "{$wpdb->prefix}bluesendmail_automation_steps", array( 'automation_id' => $automation_id ), array( '%d' ) );
		$wpdb->delete( "{$wpdb->prefix}bluesendmail_automation_queue", array( 'automation_id' => $automation_id ), array( '%d' ) );
		
        set_transient('bsm_admin_notice', ['type' => 'success', 'message' => __('Automação excluída com sucesso!', 'bluesendmail')], 30);
        wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-automations' ) );
		exit;
	}
}
