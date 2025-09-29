<?php
/**
 * Ação de Automação: Esperar (Delay).
 *
 * @package BlueSendMail
 * @version 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Action_Delay extends BSM_Abstract_Action {

    public function init() {
        $this->id    = 'delay';
        $this->name  = __( 'Esperar (Delay)', 'bluesendmail' );
        $this->group = __( 'Controle de Fluxo', 'bluesendmail' );
    }

    public function get_fields() {
         return [
            [
                'id' => 'value',
                'type' => 'number',
                'label' => __( 'Duração', 'bluesendmail' ),
                'default' => 1
            ],
            [
                'id' => 'unit',
                'type' => 'select',
                'label' => __( 'Unidade', 'bluesendmail' ),
                'options' => [
                    'minute' => __( 'Minuto(s)', 'bluesendmail' ),
                    'hour'   => __( 'Hora(s)', 'bluesendmail' ),
                    'day'    => __( 'Dia(s)', 'bluesendmail' ),
                ],
                'default' => 'day'
            ]
        ];
    }

    /**
     * Executa a ação de esperar, agendando o próximo passo para o futuro.
     * ETAPA 2: A assinatura do método foi atualizada para corresponder à classe abstrata.
     */
    public function run( $queue_item, BSM_Data_Layer $data_layer, $settings ) {
        $value  = absint( $settings['value'] ?? 1 );
        $unit   = sanitize_key( $settings['unit'] ?? 'day' );
        $plugin = BlueSendMail::get_instance();

        $interval_map = [
            'minute' => 'PT' . $value . 'M',
            'hour'   => 'PT' . $value . 'H',
            'day'    => 'P' . $value . 'D',
        ];

        if ( ! isset( $interval_map[ $unit ] ) ) {
            $plugin->log_event( 'error', 'automation_action', "Unidade de tempo inválida ('{$unit}') para a ação de Espera na automação #{$queue_item->automation_id}." );
            // Se a unidade for inválida, agendamos a próxima ação imediatamente para não travar o fluxo.
            $this->schedule_next_step( $queue_item, null, $data_layer );
            return;
        }

        try {
            $process_at_dt = new DateTime( 'now', new DateTimeZone('UTC') );
            $process_at_dt->add( new DateInterval( $interval_map[ $unit ] ) );
            $process_at_gmt = $process_at_dt->format( 'Y-m-d H:i:s' );
            
            $plugin->log_event( 'info', 'automation_action', "Delay: Contato #{$queue_item->contact_id} pausado na automação #{$queue_item->automation_id}. Próximo passo em {$process_at_gmt} UTC." );

            // ETAPA 2: Agenda a próxima ação para a data calculada, passando o data_layer adiante.
            $this->schedule_next_step( $queue_item, $process_at_gmt, $data_layer );

        } catch ( Exception $e ) {
            // Lança a exceção para ser tratada pelo sistema de retry do cron
            throw new Exception("Erro ao calcular o delay para o item da fila #{$queue_item->queue_id}: " . $e->getMessage());
        }
    }
}
