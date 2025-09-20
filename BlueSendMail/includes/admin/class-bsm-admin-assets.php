<?php
/**
 * Gerencia o enfileiramento de scripts e estilos (CSS, JS).
 *
 * @package BlueSendMail
 * @version 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Admin_Assets {

    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets( $hook ) {
        $screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'bluesendmail' ) === false ) {
            return;
        }

		wp_enqueue_style( 'bluesendmail-admin-styles', plugins_url( '../../assets/css/admin.css', __FILE__ ), array(), BLUESENDMAIL_VERSION );
		
        $page = $_GET['page'] ?? '';
		$action = $_GET['action'] ?? '';

		$is_automation_editor = ('bluesendmail-automations' === $page && in_array($action, ['new', 'edit']));
		
		$main_script_dependencies = array( 'jquery', 'wp-util' );

		wp_enqueue_script( 'bluesendmail-admin-script', BLUESENDMAIL_PLUGIN_URL . 'assets/js/admin.js', $main_script_dependencies, BLUESENDMAIL_VERSION, true );
		
		if ( $is_automation_editor ) {
			wp_enqueue_script( 'bsm-automation-builder', BLUESENDMAIL_PLUGIN_URL . 'assets/js/automation-builder.js', array('jquery', 'jquery-ui-sortable', 'wp-util'), BLUESENDMAIL_VERSION, true );
            
            $automation_id = isset($_GET['automation']) ? absint($_GET['automation']) : 0;
            $automation = $automation_id ? $this->plugin->db->get_automation_by_id($automation_id) : null;

            $definitions = [
                'triggers' => [],
                'actions' => []
            ];

            // Busca e agrupa os gatilhos por categoria
            $triggers = BSM_Triggers_Registry::get_all();
            foreach ($triggers as $trigger) {
                if(is_object($trigger)) {
                    $group = $trigger->group ?? __('Geral', 'bluesendmail');
                    $definitions['triggers'][$group][] = ['id' => $trigger->id, 'name' => $trigger->name];
                }
            }

            // Busca e agrupa as ações por categoria
            $actions = BSM_Actions_Registry::get_all();
            foreach ($actions as $action) {
                if(is_object($action)) {
                    $group = $action->group ?? __('Geral', 'bluesendmail');
                    $definitions['actions'][$group][] = ['id' => $action->id, 'name' => $action->name];
                }
            }

            $script_data = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bsm_automation_nonce'),
                'definitions' => $definitions,
                'saved_automation' => $automation, // Envia o objeto de automação completo
            ];
            
            wp_localize_script( 'bsm-automation-builder', 'bsmBuilderData', $script_data );
		}
		
		$general_script_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsm-template-nonce')
        ];
		wp_localize_script( 'bluesendmail-admin-script', 'bsm_admin_data', $general_script_data );
    }
}

