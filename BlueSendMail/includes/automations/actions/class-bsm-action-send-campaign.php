<?php
/**
 * Ação: Enviar uma campanha de e-mail para um contato.
 *
 * @package BlueSendMail
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Action_Send_Campaign extends BSM_Abstract_Action {

    public function init() {
        $this->id    = 'send_campaign';
        $this->name  = __( 'Enviar Campanha', 'bluesendmail' );
        $this->group = __( 'E-mail', 'bluesendmail' );
    }

    public function get_fields() {
        global $wpdb;
        $campaigns = $wpdb->get_results( "SELECT campaign_id, title FROM {$wpdb->prefix}bluesendmail_campaigns ORDER BY title ASC", ARRAY_A );
        $options = [];
        if ( $campaigns ) {
            foreach ( $campaigns as $campaign ) {
                $options[ $campaign['campaign_id'] ] = $campaign['title'];
            }
        }

        return [
            [
                'id' => 'campaign_id',
                'type' => 'select',
                'label' => __( 'Selecione a Campanha a Enviar', 'bluesendmail' ),
                'options' => $options
            ]
        ];
    }

    public function run( $queue_item, $step_settings ) {
        global $wpdb;

        $contact_id = $queue_item->contact_id;
        $campaign_id = absint( $step_settings['campaign_id'] ?? 0 );

        if ( ! $contact_id || ! $campaign_id ) {
            $this->automations_engine->log_item_error( $queue_item->queue_id, 'Ação "Enviar Campanha" falhou: ID do contato ou da campanha em falta.' );
            $this->automations_engine->schedule_next_step( $queue_item ); // Continua o fluxo mesmo em caso de erro
            return;
        }

        // Adiciona o e-mail à fila de envio principal
        $wpdb->insert(
            "{$wpdb->prefix}bluesendmail_queue",
            [
                'campaign_id' => $campaign_id,
                'contact_id'  => $contact_id,
                'status'      => 'pending',
                'added_at'    => current_time( 'mysql', 1 )
            ]
        );

        $this->automations_engine->log_item_action( $queue_item->queue_id, "Ação: Campanha #{$campaign_id} enfileirada para o contato #{$contact_id}." );
        $this->automations_engine->schedule_next_step( $queue_item );
    }
}

