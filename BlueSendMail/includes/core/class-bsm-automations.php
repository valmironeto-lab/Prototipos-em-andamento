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
    private $trigger_classes = [];
    private $action_classes = [];
    private $triggers = null;
    private $actions = null;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        $this->load_components_definitions();
        add_action('init', [$this, 'register_trigger_hooks']);
    }

    private function load_components_definitions() {
        $path = BLUESENDMAIL_PLUGIN_DIR . 'includes/automations/';
        
        require_once $path . 'abstracts/abstract-bsm-trigger.php';
        require_once $path . 'abstracts/abstract-bsm-action.php';
        require_once $path . 'class-bsm-data-layer.php';

        $this->trigger_classes = [
            'contact_added_to_list'     => 'BSM_Trigger_Contact_Added_To_List',
            'user_registered'           => 'BSM_Trigger_User_Registered',
            'contact_opened_campaign'   => 'BSM_Trigger_Contact_Opened_Campaign',
            'contact_clicked_link'      => 'BSM_Trigger_Contact_Clicked_Link',
        ];
        foreach ($this->trigger_classes as $class_name) {
            $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            if (file_exists($path . 'triggers/' . $file_name)) {
                require_once $path . 'triggers/' . $file_name;
            }
        }

        $this->action_classes = [
            'send_campaign'                 => 'BSM_Action_Send_Campaign',
            'delay'                         => 'BSM_Action_Delay',
            'add_to_list'                   => 'BSM_Action_Add_To_List',
            'condition_campaign_opened'     => 'BSM_Action_Condition_Campaign_Opened',
        ];
        foreach ($this->action_classes as $class_name) {
            $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            if (file_exists($path . 'actions/' . $file_name)) {
                require_once $path . 'actions/' . $file_name;
            }
        }
    }

    public function register_trigger_hooks() {
        foreach ( $this->get_triggers() as $trigger ) {
            // CORREÇÃO: Garante que $trigger é um objeto válido antes de o usar.
            if ( is_object($trigger) && method_exists($trigger, 'register_hooks') ) {
                $trigger->register_hooks();
            }
        }
    }

    public function get_trigger( $trigger_id ) {
        if ( isset($this->triggers[$trigger_id]) ) {
            return $this->triggers[$trigger_id];
        }
        $class_name = $this->trigger_classes[$trigger_id] ?? null;
        if ( $class_name && class_exists($class_name) ) {
            $this->triggers[$trigger_id] = new $class_name();
            return $this->triggers[$trigger_id];
        }
        return null;
    }

    public function get_action( $action_id ) {
        if ( isset($this->actions[$action_id]) ) {
            return $this->actions[$action_id];
        }
        $class_name = $this->action_classes[$action_id] ?? null;
        if ( $class_name && class_exists($class_name) ) {
            $this->actions[$action_id] = new $class_name();
            return $this->actions[$action_id];
        }
        return null;
    }

    public function get_triggers() {
        if (is_null($this->triggers)) {
            $this->triggers = [];
            foreach (array_keys($this->trigger_classes) as $trigger_id) {
                $this->triggers[$trigger_id] = $this->get_trigger($trigger_id);
            }
        }
        return $this->triggers;
    }

    public function get_actions() {
        if (is_null($this->actions)) {
            $this->actions = [];
            foreach (array_keys($this->action_classes) as $action_id) {
                 $this->actions[$action_id] = $this->get_action($action_id);
            }
        }
        return $this->actions;
    }
    
    public function get_definitions($type = 'action') {
        $classes = ($type === 'trigger') ? $this->trigger_classes : $this->action_classes;
        $definitions = [];
        foreach ($classes as $id => $class_name) {
            if (class_exists($class_name)) {
                $instance = new $class_name();
                $definitions[] = [
                    'id'    => $instance->id,
                    'name'  => $instance->name,
                    'group' => $instance->group,
                ];
            }
        }
        return $definitions;
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

        $first_step = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d AND parent_step_id = 0 ORDER BY step_order ASC LIMIT 1", 
            $automation_id 
        ) );
        
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

        $action_id = $step->step_type;
        $action = $this->get_action($action_id);

        if ( $action ) {
            $step_settings = maybe_unserialize($step->step_settings);
            $action->run( $queue_item, $step_settings );
        } else {
            $this->log_item_error( $queue_item->queue_id, "Ação '{$action_id}' não encontrada para o passo #{$step->step_id}. Finalizando fluxo." );
            $this->complete_contact_journey( $queue_item->queue_id );
        }
    }

    public function schedule_next_step( $queue_item, $branch = null, $process_at_gmt = null ) {
        global $wpdb;
    
        $current_step = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE step_id = %d", $queue_item->current_step_id ) );
        if ( ! $current_step ) {
            $this->complete_contact_journey( $queue_item->queue_id );
            return;
        }
    
        $action = $this->get_action( $current_step->step_type );
    
        $search_parent_id = $current_step->parent_id;
        $search_order = $current_step->step_order;
        $search_branch = $current_step->branch;
    
        if ( $action && $action->is_condition ) {
            $search_parent_id = $current_step->step_id;
            $search_order = 0; 
            $search_branch = $branch;
        }
        
        $next_step = null;
        $attempts = 0;
        
        while (!$next_step && $attempts < 10) {
            $attempts++;
            
            $next_step = $this->get_next_step_in_db( $queue_item->automation_id, $search_parent_id, $search_order, $search_branch );
            
            if ($next_step) {
                break;
            }
            
            if ($search_parent_id == 0) {
                break;
            }
            
            $parent_step = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE step_id = %d", $search_parent_id ) );
            if (!$parent_step) {
                break;
            }
            
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
                $action = $this->get_action($step['step_type']);
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

    public function log_item_action( $queue_id, $message ) {
        $this->plugin->log_event('info', 'automation_action', $message, "Queue ID: {$queue_id}");
    }

    public function log_item_error( $queue_id, $message ) {
        $this->plugin->log_event('error', 'automation_engine', $message, "Queue ID: {$queue_id}");
    }
}

