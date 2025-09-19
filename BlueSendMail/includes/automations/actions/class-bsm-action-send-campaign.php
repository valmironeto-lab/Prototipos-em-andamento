<?php
/**
 * Ação de Automação: Enviar Campanha.
 *
 * @package BlueSendMail
 * @version 2.1.1
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
        
        // CORREÇÃO: Otimização para evitar esgotamento de memória.
        // 1. Limita a busca para as 200 campanhas mais recentes que são rascunhos ou já foram enviadas.
        // 2. O ideal para sites muito grandes seria usar uma busca AJAX, mas esta é uma solução robusta e imediata.
        $campaigns = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT campaign_id, title FROM {$wpdb->prefix}bluesendmail_campaigns 
                 WHERE status IN ('draft', 'sent') 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                200 
            ),
            ARRAY_A 
        );

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
                'description' => __( 'Mostrando as 200 campanhas mais recentes. Se não encontrar a campanha desejada, certifique-se de que ela está salva como rascunho.', 'bluesendmail' )
            ]
        ];
    }

    /**
     * Executa a ação de enviar a campanha.
     */
    public function run( $plugin, $queue_item, $data_layer, $settings ) {
        global $wpdb;

        $campaign_id = absint( $settings['campaign_id'] ?? 0 );
        $contact_id = $queue_item->contact_id;

        if ( ! $campaign_id || ! $contact_id ) {
            $plugin->log_event( 'error', 'automation_action', "Ação 'Enviar Campanha' falhou: ID da campanha ou do contato ausente para o item da fila #{$queue_item->queue_id}." );
            // Mesmo com erro, tentamos continuar o fluxo.
            $this->schedule_next_action($queue_item);
            return;
        }

        // Adiciona o e-mail à fila de envio principal.
        $wpdb->insert( "{$wpdb->prefix}bluesendmail_queue", array(
            'campaign_id' => $campaign_id,
            'contact_id'  => $contact_id,
            'status'      => 'pending',
            'added_at'    => current_time( 'mysql', 1 )
        ));

        $plugin->log_event( 'info', 'automation_action', "Ação: Campanha #{$campaign_id} enfileirada para o contato #{$contact_id} pela automação #{$queue_item->automation_id}." );

        // Agenda a próxima ação para ser executada imediatamente.
        $this->schedule_next_action($queue_item);
    }
}

