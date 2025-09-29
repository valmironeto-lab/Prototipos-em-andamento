<?php
/**
 * Classe Abstrata para Ações de Automação.
 * Define o contrato que todas as ações devem seguir.
 *
 * @package BlueSendMail
 * @version 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BSM_Abstract_Action {

    public $id;
    public $name;
    public $group;
    
    public function __construct() {
        $this->init();
    }

    abstract public function init();

    /**
     * Executa a lógica principal da ação.
     * Pode opcionalmente retornar um Data Layer atualizado para ser usado nos próximos passos.
     *
     * @param stdClass $queue_item O item da fila sendo processado.
     * @param BSM_Data_Layer $data_layer Os dados atuais do fluxo.
     * @param array $settings As configurações específicas deste passo.
     * @return BSM_Data_Layer|void
     */
    abstract public function run( $queue_item, BSM_Data_Layer $data_layer, $settings );

    public function get_fields() {
        return [];
    }
    
    /**
     * Enfileira o próximo passo na sequência da automação.
     *
     * @param stdClass $current_queue_item O item da fila que acabou de ser processado.
     * @param string|null $process_at_gmt Data/hora GMT para processar a próxima ação. Null para processamento imediato.
     * @param BSM_Data_Layer|null $data_layer_to_persist Opcional. O Data Layer atualizado para persistir para o próximo passo.
     */
    protected function schedule_next_step( $current_queue_item, $process_at_gmt = null, $data_layer_to_persist = null ) {
        global $wpdb;

        $automation_id = $current_queue_item->automation_id;
        
        // Encontra o passo atual para obter sua ordem.
        $current_step = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE step_id = %d", $current_queue_item->step_id ) );
        
        if ( ! $current_step ) {
            $this->complete_automation( $current_queue_item, "Passo atual #{$current_queue_item->step_id} não encontrado." );
            return;
        }

        // Procura pelo próximo passo na mesma automação com uma ordem maior.
        $next_step = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d AND step_order > %d ORDER BY step_order ASC LIMIT 1",
            $automation_id,
            $current_step->step_order
        ) );

        if ( $next_step ) {
            // Se encontrou um próximo passo, ATUALIZA o item da fila existente.
            $update_data = [
                'step_id'    => $next_step->step_id,
                'status'     => 'waiting',
                'process_at' => $process_at_gmt ?? current_time( 'mysql', 1 ),
                'attempts'   => 0, // Reseta as tentativas para o novo passo
            ];

            // ETAPA 2: Persiste o Data Layer atualizado, se fornecido
            if ( $data_layer_to_persist instanceof BSM_Data_Layer ) {
                $update_data['data_layer'] = maybe_serialize( $data_layer_to_persist->get_all_data() );
            }

            $wpdb->update(
                "{$wpdb->prefix}bluesendmail_automation_queue",
                $update_data,
                [ 'queue_id' => $current_queue_item->queue_id ]
            );
             BlueSendMail::get_instance()->log_event('info', 'automation_engine', "Próximo passo (#{$next_step->step_id}) agendado para o contato #{$current_queue_item->contact_id}.");

        } else {
            // Se não houver próximo passo, finaliza a automação.
            $this->complete_automation( $current_queue_item, "Fim do fluxo." );
        }
    }

    /**
     * Marca um item da fila como concluído.
     */
    private function complete_automation( $queue_item, $reason = '' ) {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}bluesendmail_automation_queue",
            [ 'status' => 'completed' ],
            [ 'queue_id' => $queue_item->queue_id ]
        );
        BlueSendMail::get_instance()->log_event('info', 'automation_engine', "Automação #{$queue_item->automation_id} concluída para o contato #{$queue_item->contact_id}. Razão: {$reason}");
    }
}
