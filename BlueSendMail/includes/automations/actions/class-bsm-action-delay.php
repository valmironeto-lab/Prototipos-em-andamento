<?php
/**
 * Ação: Esperar por um período de tempo.
 *
 * @package BlueSendMail
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Action_Delay extends BSM_Abstract_Action {

    public function init() {
        $this->id    = 'delay';
        $this->name  = __( 'Esperar', 'bluesendmail' );
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
                    'week'   => __( 'Semana(s)', 'bluesendmail' ),
                ],
                'default' => 'day'
            ]
        ];
    }

    public function run( $queue_item, $step_settings ) {
        $value = absint( $step_settings['value'] ?? 1 );
        $unit = sanitize_key( $step_settings['unit'] ?? 'day' );

        $delay_in_seconds = 0;
        switch ($unit) {
            case 'minute':
                $delay_in_seconds = $value * MINUTE_IN_SECONDS;
                break;
            case 'hour':
                $delay_in_seconds = $value * HOUR_IN_SECONDS;
                break;
            case 'day':
                $delay_in_seconds = $value * DAY_IN_SECONDS;
                break;
            case 'week':
                $delay_in_seconds = $value * WEEK_IN_SECONDS;
                break;
        }

        if ($delay_in_seconds > 0) {
            $process_at_gmt = time() + $delay_in_seconds;
            $this->automations_engine->log_item_action( $queue_item->queue_id, "Delay: Contato #{$queue_item->contact_id} pausado. Próximo passo em " . date('Y-m-d H:i:s', $process_at_gmt) . " UTC." );
            $this->automations_engine->schedule_next_step( $queue_item, null, $process_at_gmt );
        } else {
            // Se o delay for inválido, apenas avança para o próximo passo.
            $this->automations_engine->schedule_next_step( $queue_item );
        }
    }
}

