<?php
/**
 * Gerencia o processamento de todas as ações e formulários do admin.
 *
 * @package BlueSendMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Admin_Handler {

    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_action( 'wp_ajax_bsm_get_trigger_fields', array( $this, 'ajax_get_trigger_fields' ) );
    }

    public function ajax_get_trigger_fields() {
        check_ajax_referer( 'bsm_automation_nonce', 'nonce' );
        if ( ! current_user_can('bsm_manage_campaigns') ) wp_send_json_error();

        $trigger_id = sanitize_key($_POST['trigger_id']);
        $trigger = $this->plugin->automations->get_trigger($trigger_id);

        if (!$trigger) {
            wp_send_json_error(['message' => 'Trigger not found.']);
        }

        $fields = $trigger->get_fields();
        wp_send_json_success(['html' => $this->render_fields_html($fields, 'trigger_settings')]);
    }
    
    private function render_fields_html($fields, $name_prefix) {
        $html = '';
        foreach($fields as $field) {
            $name = "{$name_prefix}[{$field['id']}]";
            $input_html = '';

            switch($field['type']) {
                case 'select':
                    $options_html = '';
                    if (!empty($field['options'])) {
                        foreach($field['options'] as $val => $label) {
                            $options_html .= "<option value='" . esc_attr($val) . "'>" . esc_html($label) . "</option>";
                        }
                    }
                    $input_html = "<select name='{$name}'>{$options_html}</select>";
                    break;
                default:
                     $input_html = "<input type='{$field['type']}' name='{$name}' class='regular-text'>";
                    break;
            }
            $html .= "<div class='bsm-field-group'><label>" . esc_html($field['label']) . "</label><br>{$input_html}</div>";
        }
        return $html;
    }

    public function handle_actions() {
        $page = $_GET['page'] ?? '';

        if ( 'bluesendmail-automations' === $page ) {
            if ( isset( $_POST['bsm_save_automation'] ) ) $this->handle_save_automation();
            if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] ) $this->handle_delete_automation();
        }
    }

    public function handle_save_automation() {
        if ( ! isset( $_POST['bsm_save_automation_nonce_field'] ) || ! wp_verify_nonce( $_POST['bsm_save_automation_nonce_field'], 'bsm_save_automation_nonce_action' ) ) {
            wp_die( esc_html__( 'A verificação de segurança falhou.', 'bluesendmail' ) );
        }
        if ( ! current_user_can( 'bsm_manage_campaigns' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) );
        }
    
        global $wpdb;
        $automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;
        $name = sanitize_text_field( $_POST['name'] ?? '' );
        $status = sanitize_key( $_POST['status'] ?? 'inactive' );
        $trigger_id = sanitize_key($_POST['trigger_id'] ?? '');
        $trigger_settings = isset($_POST['trigger_settings']) ? (array) $_POST['trigger_settings'] : [];
        $steps_data = isset( $_POST['steps'] ) && is_array( $_POST['steps'] ) ? $_POST['steps'] : array();
    
        if ( empty($name) || empty($trigger_id) ) {
            set_transient('bsm_admin_notice', ['type' => 'error', 'message' => __('Nome e Gatilho da Automação são obrigatórios.', 'bluesendmail')], 30);
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-automations&action=' . ( $automation_id ? 'edit&automation=' . $automation_id : 'new' ) ) );
            exit;
        }
    
        $data = array(
            'name' => $name,
            'status' => $status,
            'trigger_id' => $trigger_id,
            'trigger_settings' => maybe_serialize( $trigger_settings ),
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
        
        if ( ! empty( $steps_data ) ) {
            $order = 0;
            foreach ( $steps_data as $step_data ) {
                if ( ! isset( $step_data['type'] ) || empty($step_data['type']) ) continue;

                $type = sanitize_key( $step_data['type'] );
                $settings = [];

                if ( 'send_campaign' === $type ) {
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

	public function handle_delete_automation() {
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

