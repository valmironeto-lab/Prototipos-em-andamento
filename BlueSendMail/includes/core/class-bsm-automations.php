<?php
/**
 * Gerencia a lógica principal das automações.
 *
 * @package BlueSendMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Automations {

    private $plugin;
    private $triggers = [];
    private $actions = [];

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        $this->load_components();
        $this->register_trigger_hooks();
    }

    private function load_components() {
        $path = BLUESENDMAIL_PLUGIN_DIR . 'includes/automations/';
        
        // --- Carregamento Explícito de Componentes ---

        // Classes Abstratas (Fundação)
        require_once $path . 'abstracts/abstract-bsm-trigger.php';
        require_once $path . 'abstracts/abstract-bsm-action.php';
        require_once $path . 'class-bsm-data-layer.php';

        // Gatilhos
        $trigger_classes = [
            'BSM_Trigger_Contact_Added_To_List',
            'BSM_Trigger_User_Registered',
            'BSM_Trigger_Contact_Opened_Campaign',
            'BSM_Trigger_Contact_Clicked_Link',
        ];
        foreach ($trigger_classes as $class_name) {
            $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            if (file_exists($path . 'triggers/' . $file_name)) {
                require_once $path . 'triggers/' . $file_name;
                if (class_exists($class_name)) {
                    $trigger = new $class_name();
                    $this->triggers[$trigger->id] = $trigger;
                }
            }
        }

        // Ações
        $action_classes = [
            'BSM_Action_Send_Campaign',
            'BSM_Action_Delay',
            'BSM_Action_Add_To_List',
            'BSM_Action_Condition_Campaign_Opened',
        ];
        foreach ($action_classes as $class_name) {
            $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            if (file_exists($path . 'actions/' . $file_name)) {
                require_once $path . 'actions/' . $file_name;
                if (class_exists($class_name)) {
                    $action = new $class_name();
                    $this->actions[$action->id] = $action;
                }
            }
        }
    }

    private function register_trigger_hooks() {
        foreach ( $this->triggers as $trigger ) {
            if (method_exists($trigger, 'register_hooks')) {
                $trigger->register_hooks();
            }
        }
    }

    public function get_triggers() {
        return $this->triggers;
    }

    public function get_actions() {
        return $this->actions;
    }

    public function maybe_trigger_automations( $trigger_instance, $data_layer ) {
        global $wpdb;
        
        $automations = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bluesendmail_automations WHERE status = 'active' AND trigger_id = %s",
            $trigger_instance->id
        ) );

        foreach ( $automations as $automation ) {
            $trigger_settings = maybe_unserialize( $automation->trigger_settings );
            if ( $trigger_instance->validate_options( $trigger_settings, $data_layer ) ) {
                $this->enqueue_contact_for_automation( $automation->automation_id, $data_layer->get_item('contact_id') );
            }
        }
    }

    public function enqueue_contact_for_automation( $automation_id, $contact_id ) {
        global $wpdb;

        $first_step = $this->get_next_step_in_db( $automation_id, 0, -1, null );
        
        if ( ! $first_step ) {
            $this->plugin->log_event('warning', 'automation_trigger', "Automação #{$automation_id} não possui passos configurados.");
            return;
        }

        $wpdb->insert(
            "{$wpdb->prefix}bluesendmail_automation_queue",
            [
                'automation_id'   => $automation_id,
                'contact_id'      => $contact_id,
                'status'          => 'waiting',
                'current_step_id' => $first_step->step_id,
                'process_at'      => current_time( 'mysql', 1 ),
                'created_at'      => current_time( 'mysql', 1 ),
            ]
        );
        $this->plugin->log_event( 'info', 'automation_trigger', "Contato #{$contact_id} enfileirado para o início da automação #{$automation_id}." );
    }

    public function process_queue_item( $queue_item ) {
        global $wpdb;
        $step = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE step_id = %d", $queue_item->current_step_id ) );

        if ( ! $step ) {
            $this->log_item_error( $queue_item->queue_id, "Passo #{$queue_item->current_step_id} não encontrado. Finalizando fluxo." );
            $this->complete_contact_journey( $queue_item->queue_id );
            return;
        }

        $action_id = $step->action_id;
        if ( isset($this->actions[$action_id]) ) {
            $action = $this->actions[$action_id];
            $step_settings = maybe_unserialize($step->step_settings);
            $action->run( $queue_item, $step_settings );
        } else {
            $this->log_item_error( $queue_item->queue_id, "Ação '{$action_id}' não encontrada para o passo #{$step->step_id}. Finalizando fluxo." );
            $this->complete_contact_journey( $queue_item->queue_id );
        }
    }

    /**
     * Encontra o próximo passo na automação de forma iterativa e segura.
     * Substitui a lógica recursiva anterior para prevenir loops infinitos.
     */
    public function schedule_next_step( $queue_item, $branch = null, $process_at_gmt = null ) {
        global $wpdb;
    
        $current_step = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE step_id = %d", $queue_item->current_step_id ) );
        if ( ! $current_step ) {
            $this->complete_contact_journey( $queue_item->queue_id );
            return;
        }
    
        $action = $this->actions[ $current_step->action_id ] ?? null;
    
        // Define o ponto de partida para a busca do próximo passo
        $search_parent_id = $current_step->parent_id;
        $search_order = $current_step->step_order;
        $search_branch = $current_step->branch;
    
        // Se a ação atual é uma condição, a busca começa DENTRO do ramo escolhido.
        if ( $action && $action->is_condition ) {
            $search_parent_id = $current_step->step_id;
            $search_order = -1; // Começa do início do ramo
            $search_branch = $branch;
        }
        
        $next_step = null;
        $attempts = 0; // Prevenção extra contra loops inesperados
        
        while (!$next_step && $attempts < 10) {
            $attempts++;
            
            $next_step = $this->get_next_step_in_db( $queue_item->automation_id, $search_parent_id, $search_order, $search_branch );
            
            if ($next_step) {
                // Encontramos o próximo passo, saímos do loop.
                break;
            }
            
            // Se não encontramos e estamos na raiz, a automação terminou.
            if ($search_parent_id == 0) {
                break;
            }
            
            // Se não, subimos um nível na árvore para procurar o "tio" (irmão do pai).
            $parent_step = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE step_id = %d", $search_parent_id ) );
            if (!$parent_step) {
                // Pai não existe, terminamos para evitar erros.
                break;
            }
            
            // Prepara a busca para o próximo nível.
            $search_parent_id = $parent_step->parent_id;
            $search_order = $parent_step->step_order;
            $search_branch = $parent_step->branch;
        }
    
        if ( $next_step ) {
            $this->update_contact_journey(
                $queue_item->queue_id,
                $next_step->step_id,
                'waiting',
                $process_at_gmt ? gmdate( 'Y-m-d H:i:s', $process_at_gmt ) : current_time( 'mysql', 1 )
            );
        } else {
            // Se saímos do loop sem encontrar um próximo passo, a jornada terminou.
            $this->complete_contact_journey( $queue_item->queue_id );
        }
    }

    private function get_next_step_in_db( $automation_id, $parent_id, $current_order, $branch = null ) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d AND parent_id = %d AND step_order > %d",
            $automation_id, $parent_id, $current_order
        );
        
        if ($branch) {
            $sql .= $wpdb->prepare(" AND branch = %s", $branch);
        } else {
            $sql .= " AND branch IS NULL";
        }
        
        $sql .= " ORDER BY step_order ASC LIMIT 1";

        return $wpdb->get_row($sql);
    }

    private function update_contact_journey( $queue_id, $next_step_id, $status = 'waiting', $process_at = null ) {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}bluesendmail_automation_queue",
            [ 
                'current_step_id' => $next_step_id, 
                'status' => $status, 
                'process_at' => $process_at ?? current_time( 'mysql', 1 ) 
            ],
            [ 'queue_id' => $queue_id ]
        );
    }

    private function complete_contact_journey( $queue_id ) {
        global $wpdb;
        $wpdb->update( "{$wpdb->prefix}bluesendmail_automation_queue", [ 'status' => 'completed' ], [ 'queue_id' => $queue_id ] );
        $this->log_item_action( $queue_id, "Automação concluída." );
    }
    
    public function get_automation_steps_tree( $automation_id ) {
        global $wpdb;
        $all_steps_flat = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d ORDER BY parent_id, branch, step_order ASC", $automation_id ), ARRAY_A );
    
        if ( empty( $all_steps_flat ) ) {
            return [];
        }
    
        $steps_by_parent_and_branch = [];
        foreach ($all_steps_flat as $step) {
            $parent_id = $step['parent_id'];
            $branch = $step['branch'] ?? 'main';
            $steps_by_parent_and_branch[$parent_id][$branch][] = $step;
        }
    
        $build_tree_func = function( $parent_id, $branch ) use ( &$steps_by_parent_and_branch, &$build_tree_func ) {
            if (empty($steps_by_parent_and_branch[$parent_id][$branch])) {
                return [];
            }
            
            $result = [];
            foreach ( $steps_by_parent_and_branch[$parent_id][$branch] as $step ) {
                $step['step_settings'] = maybe_unserialize($step['step_settings']);
                $action = $this->actions[$step['action_id']] ?? null;
                if ( $action && $action->is_condition ) {
                    $step['yes_branch'] = $build_tree_func($step['step_id'], 'yes');
                    $step['no_branch'] = $build_tree_func($step['step_id'], 'no');
                }
                $result[] = $step;
            }
            return $result;
        };
    
        return $build_tree_func( 0, 'main' );
    }

    // Funções de logging
    public function log_item_action( $queue_id, $message ) {
        $this->plugin->log_event('info', 'automation_action', $message, "Queue ID: {$queue_id}");
    }

    public function log_item_error( $queue_id, $message ) {
        $this->plugin->log_event('error', 'automation_engine', $message, "Queue ID: {$queue_id}");
    }
}

