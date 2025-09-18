<?php
/**
 * Ação: Adicionar um contato a uma lista.
 *
 * @package BlueSendMail
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Action_Add_To_List extends BSM_Abstract_Action {

    public function init() {
        $this->id    = 'add_to_list';
        $this->name  = __( 'Adicionar à Lista', 'bluesendmail' );
        $this->group = __( 'Contatos', 'bluesendmail' );
    }

    public function get_fields() {
        global $wpdb;
        $all_lists = $wpdb->get_results( "SELECT list_id, name FROM {$wpdb->prefix}bluesendmail_lists ORDER BY name ASC", ARRAY_A );
        $options = [];
        if ( $all_lists ) {
            foreach ( $all_lists as $list ) {
                $options[ $list['list_id'] ] = $list['name'];
            }
        }

        return [
            [
                'id' => 'list_id',
                'type' => 'select',
                'label' => __( 'Selecione a Lista de Destino', 'bluesendmail' ),
                'options' => $options
            ]
        ];
    }

    public function run( $queue_item, $step_settings ) {
        global $wpdb;
        $contact_id = $queue_item->contact_id;
        $list_id = absint( $step_settings['list_id'] ?? 0 );

        if ( ! $contact_id || ! $list_id ) {
            $this->automations_engine->log_item_error( $queue_item->queue_id, 'Ação "Adicionar à Lista" falhou: ID do contato ou da lista em falta.' );
            $this->automations_engine->schedule_next_step( $queue_item );
            return;
        }

        // Insere a relação, ignorando se já existir.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}bluesendmail_contact_lists (contact_id, list_id) VALUES (%d, %d)",
                $contact_id,
                $list_id
            )
        );

        $this->automations_engine->log_item_action( $queue_item->queue_id, "Ação: Contato #{$contact_id} adicionado à lista #{$list_id}." );
        $this->automations_engine->schedule_next_step( $queue_item );
    }
}

