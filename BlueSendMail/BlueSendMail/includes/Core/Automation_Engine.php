<?php
/**
 * Gerencia a lógica principal das automações (Motor de Automação V2.2+).
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Core;

// Imports
use BlueSendMail\Plugin;
use BlueSendMail\Automations\Data_Layer;
use BlueSendMail\Automations\Triggers_Registry;
use BlueSendMail\Automations\Actions_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Automation_Engine {

    private $plugin;

    public function __construct( Plugin $plugin ) {
        $this->plugin = $plugin;
        $this->register_hooks();
		Triggers_Registry::init();
		Actions_Registry::init();
    }

    /**
     * Registra os ganchos para os gatilhos.
     */
    private function register_hooks() {
		$triggers = Triggers_Registry::get_all();
		foreach( $triggers as $trigger ) {
			$trigger->register_hooks( $this );
		}
    }

	/**
     * Chamado por um gatilho quando um evento ocorre.
     * Encontra automações correspondentes e as coloca na fila.
     *
     * @param string $trigger_id O ID do gatilho que foi acionado.
     * @param Data_Layer $data_layer Os dados associados ao evento.
     */
	public function trigger_event( $trigger_id, Data_Layer $data_layer ) {
		global $wpdb;
        $automations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}bluesendmail_automations WHERE status = 'active' AND trigger_type = %s",
			$trigger_id
		));

		$trigger = Triggers_Registry::get( $trigger_id );
		if ( ! $trigger ) {
			return;
		}

        foreach ( $automations as $automation ) {
            $trigger_settings = maybe_unserialize( $automation->trigger_settings );
            
			// Valida se as opções específicas da automação correspondem ao evento.
			if ( $trigger->validate_options( $trigger_settings, $data_layer ) ) {
				$contact = $data_layer->get_item('contact');
				if ( $contact && isset($contact->contact_id) ) {
					$this->start_automation_for_contact( $contact->contact_id, $automation->automation_id, $data_layer );
				}
            }
        }
	}

    /**
     * Coloca um contato na fila para iniciar uma automação.
     */
    public function start_automation_for_contact( $contact_id, $automation_id, Data_Layer $data_layer ) {
        global $wpdb;

        $wpdb->insert(
            "{$wpdb->prefix}bluesendmail_automation_queue",
            array(
                'automation_id'   => $automation_id,
                'contact_id'      => $contact_id,
                'status'          => 'waiting',
                'next_step_index' => 0,
                'data_layer'      => maybe_serialize( $data_layer->get_all_data() ),
                'process_at'      => current_time( 'mysql', 1 ),
                'created_at'      => current_time( 'mysql', 1 ),
            )
        );
        $this->plugin->log_event( 'info', 'automation_trigger', "Contato #{$contact_id} enfileirado para o início da automação #{$automation_id}." );
    }

    /**
     * Processa um item da fila de automação.
	 * @param stdClass $item O item da fila do banco de dados.
     */
    public function process_queue_item( $item ) {
        global $wpdb;

        $step = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d ORDER BY step_order ASC LIMIT 1 OFFSET %d",
            $item->automation_id,
            $item->next_step_index
        ) );

        if ( ! $step ) {
            $wpdb->update( "{$wpdb->prefix}bluesendmail_automation_queue", [ 'status' => 'completed' ], [ 'queue_id' => $item->queue_id ] );
            $this->plugin->log_event( 'info', 'automation_engine', "Automação #{$item->automation_id} concluída para o contato #{$item->contact_id} (sem mais passos)." );
            return;
        }

        $data_layer = new Data_Layer( maybe_unserialize( $item->data_layer ) );
        $settings   = maybe_unserialize( $step->step_settings );
        
		// Usa o Actions_Registry para obter a classe de Ação correta.
		$action = Actions_Registry::get( $step->step_type, $this->plugin, $item, $data_layer, $settings );

        if ( $action ) {
            $action->run();
        } else {
            $this->plugin->log_event( 'error', 'automation_engine', "Classe de ação para o tipo '{$step->step_type}' não encontrada no passo #{$step->step_id}. O fluxo para o contato #{$item->contact_id} foi interrompido." );
            $wpdb->update( "{$wpdb->prefix}bluesendmail_automation_queue", [ 'status' => 'failed' ], [ 'queue_id' => $item->queue_id ] );
        }
    }
}

