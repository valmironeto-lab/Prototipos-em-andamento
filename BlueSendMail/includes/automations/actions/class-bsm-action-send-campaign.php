<?php
/**
 * Ação de Automação: Enviar Campanha.
 *
 * @package BlueSendMail
 * @version 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Action_Send_Campaign extends BSM_Abstract_Action {

    public function init() {
        $this->id    = 'send_campaign';
        $this->name  = __( 'Enviar Campanha', 'bluesendmail' );
        $this->group = __( 'Ações', 'bluesendmail' );
    }
    
    public function get_fields() {
        global $wpdb;
        $campaigns = $wpdb->get_results( "SELECT campaign_id, title FROM {$wpdb->prefix}bluesendmail_campaigns WHERE status IN ('draft', 'sent') ORDER BY created_at DESC LIMIT 200", ARRAY_A );
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
                'label' => __( 'Selecione a Campanha', 'bluesendmail' ),
                'options' => $options,
                'description' => __( 'Apenas campanhas salvas como rascunho ou já enviadas podem ser usadas em automações.', 'bluesendmail' )
            ]
        ];
    }
    
    /**
     * Executa a ação de enfileirar a campanha.
     * ETAPA 2: A assinatura do método foi atualizada para corresponder à classe abstrata.
     */
    public function run( $queue_item, BSM_Data_Layer $data_layer, $settings ) {
        global $wpdb;

        $campaign_id = absint( $settings['campaign_id'] ?? 0 );
        $contact_id  = $queue_item->contact_id;
        $plugin      = BlueSendMail::get_instance();

        if ( ! $campaign_id || ! $contact_id ) {
            // Lança uma exceção para ser capturada pelo sistema de tentativas
            throw new Exception("ID da campanha ou do contato ausente. Campanha: {$campaign_id}, Contato: {$contact_id}.");
        } 
        
        // Adiciona o e-mail à fila de envio principal.
        $wpdb->insert( "{$wpdb->prefix}bluesendmail_queue", array(
            'campaign_id' => $campaign_id,
            'contact_id'  => $contact_id,
            'status'      => 'pending',
            'added_at'    => current_time( 'mysql', 1 )
        ));

        $plugin->log_event( 'info', 'automation_action', "Ação: Campanha #{$campaign_id} enfileirada para o contato #{$contact_id} pela automação #{$queue_item->automation_id}." );

        // ETAPA 2: Agenda o próximo passo, passando o data_layer adiante sem modificação.
        $this->schedule_next_step( $queue_item, null, $data_layer );
    }
}
