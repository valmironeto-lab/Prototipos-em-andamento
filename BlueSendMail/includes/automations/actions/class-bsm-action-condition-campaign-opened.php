<?php
/**
 * Ação: Condicional para verificar se uma campanha foi aberta.
 *
 * @package BlueSendMail
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Action_Condition_Campaign_Opened extends BSM_Abstract_Action {

    public function init() {
        $this->id           = 'condition_campaign_opened';
        $this->name         = __( 'Verificar Abertura de Campanha', 'bluesendmail' );
        $this->group        = __( 'Condicionais', 'bluesendmail' );
        $this->is_condition = true;
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
                'label' => __( 'Selecione a Campanha a Verificar', 'bluesendmail' ),
                'options' => $options
            ]
        ];
    }

    public function run( $queue_item, $step_settings ) {
        global $wpdb;
        $contact_id = $queue_item->contact_id;
        $campaign_id = absint( $step_settings['campaign_id'] ?? 0 );

        if ( ! $contact_id || ! $campaign_id ) {
            $this->automations_engine->log_item_error( $queue_item->queue_id, 'Condição "Verificar Abertura" falhou: ID do contato ou da campanha em falta.' );
            $this->automations_engine->schedule_next_step( $queue_item, 'no' ); // Se falhar, segue o ramo "Não"
            return;
        }

        $has_opened = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(o.open_id) 
             FROM {$wpdb->prefix}bluesendmail_email_opens o 
             JOIN {$wpdb->prefix}bluesendmail_queue q ON o.queue_id = q.queue_id
             WHERE q.contact_id = %d AND q.campaign_id = %d",
            $contact_id,
            $campaign_id
        ) );

        $branch = $has_opened > 0 ? 'yes' : 'no';
        $this->automations_engine->log_item_action( $queue_item->queue_id, "Condição: Verificou abertura da campanha #{$campaign_id}. Resultado: " . strtoupper($branch) );
        $this->automations_engine->schedule_next_step( $queue_item, $branch );
    }
}

