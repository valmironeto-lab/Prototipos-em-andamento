<?php
/**
 * Motor de Automação (Refatorado).
 * Gerencia o carregamento de componentes e o disparo de eventos.
 *
 * @package BlueSendMail
 * @version 2.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Automations {

    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        
        // Inicializa os registros para que eles saibam quais componentes existem.
        BSM_Triggers_Registry::init();
        BSM_Actions_Registry::init();
        
        // Adiciona a ação para registrar os ganchos dos gatilhos no momento certo (apenas uma vez).
        add_action( 'init', [ $this, 'register_all_trigger_hooks' ], 10 );
    }

    /**
     * Itera sobre todos os gatilhos disponíveis e registra seus ganchos no WordPress.
     * Isso garante que os gatilhos estejam "ouvindo" os eventos.
     */
    public function register_all_trigger_hooks() {
        foreach ( BSM_Triggers_Registry::get_all() as $trigger ) {
            if ( is_object( $trigger ) && method_exists( $trigger, 'register_hooks' ) ) {
                $trigger->register_hooks();
            }
        }
    }

    /**
     * Ponto de entrada chamado por um gatilho quando um evento ocorre.
     *
     * @param string         $trigger_id O ID do gatilho que foi acionado.
     * @param BSM_Data_Layer $data_layer Os dados do evento.
     */
    public function trigger_event( $trigger_id, $data_layer ) {
        global $wpdb;
        
        $automations = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bluesendmail_automations WHERE status = 'active' AND trigger_id = %s",
            $trigger_id
        ) );

        if ( empty( $automations ) ) {
            return;
        }

        $trigger_instance = BSM_Triggers_Registry::get( $trigger_id );
        if ( ! $trigger_instance ) {
            return;
        }
        
        $contact = $data_layer->get_item('contact');
        if ( ! $contact || ! isset( $contact->contact_id ) ) {
             $this->plugin->log_event('warning', 'automation_trigger', "Evento de gatilho '{$trigger_id}' disparado sem um contact_id válido no Data Layer.");
            return;
        }
        $contact_id = $contact->contact_id;

        foreach ( $automations as $automation ) {
            $trigger_settings = maybe_unserialize( $automation->trigger_settings );
            
            if ( $trigger_instance->validate_options( $trigger_settings, $data_layer ) ) {
                $this->enqueue_contact_for_automation( $automation->automation_id, $contact_id, $data_layer );
            }
        }
    }

    /**
     * Coloca um contato na fila para iniciar uma automação.
     *
     * @param int            $automation_id
     * @param int            $contact_id
     * @param BSM_Data_Layer $data_layer
     */
    public function enqueue_contact_for_automation( $automation_id, $contact_id, $data_layer ) {
        global $wpdb;
        
        $first_step = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d ORDER BY step_order ASC LIMIT 1", 
            $automation_id 
        ) );
        
        if ( ! $first_step ) {
            $this->plugin->log_event('warning', 'automation_trigger', "Automação #{$automation_id} foi acionada para o contato #{$contact_id}, mas não possui passos configurados.");
            return;
        }

        $wpdb->insert(
            "{$wpdb->prefix}bluesendmail_automation_queue",
            [
                'automation_id'   => $automation_id,
                'contact_id'      => $contact_id,
                'step_id'         => $first_step->step_id,
                'status'          => 'waiting',
                'data_layer'      => maybe_serialize( $data_layer->get_all_data() ),
                'process_at'      => current_time( 'mysql', 1 ),
                'created_at'      => current_time( 'mysql', 1 ),
            ]
        );
        $this->plugin->log_event( 'info', 'automation_trigger', "Contato #{$contact_id} enfileirado para o início da automação #{$automation_id}." );
    }
}

