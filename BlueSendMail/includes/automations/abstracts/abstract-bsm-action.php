<?php
/**
 * Classe Abstrata para Ações de Automação.
 * Define o contrato que todas as ações devem seguir.
 *
 * @package BlueSendMail
 * @version 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BSM_Abstract_Action {

    /**
     * O ID único da ação (slug).
     * @var string
     */
    public $id;

    /**
     * O nome da ação para exibição.
     * @var string
     */
    public $name;

    /**
     * O grupo ao qual a ação pertence.
     * @var string
     */
    public $group;

    public function __construct() {
        $this->init();
    }

    /**
     * Método de inicialização para definir as propriedades da ação.
     */
    abstract public function init();

    /**
     * Executa a lógica principal da ação.
     *
     * @param BlueSendMail   $plugin      A instância principal do plugin.
     * @param stdClass       $queue_item  O objeto do item da fila do banco de dados.
     * @param BSM_Data_Layer $data_layer  A camada de dados com informações do contato, etc.
     * @param array          $settings    As configurações do passo (step) salvas no banco de dados.
     * @return void
     */
    abstract public function run( $plugin, $queue_item, $data_layer, $settings );

    /**
     * Retorna os campos de configuração para esta ação.
     *
     * @return array
     */
    public function get_fields() {
        return [];
    }

    /**
     * Enfileira a próxima ação na sequência da automação.
     *
     * @param stdClass $queue_item O item da fila atual.
     * @param string|null $process_at Data/hora para processar a próxima ação (formato 'Y-m-d H:i:s'). Null para processamento imediato.
     */
    protected function schedule_next_action( $queue_item, $process_at = null ) {
        global $wpdb;

        $automation_id    = $queue_item->automation_id;
        $next_step_index  = (int) $queue_item->next_step_index + 1;

        $total_steps = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(step_id) FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d",
            $automation_id
        ) );

        if ( $next_step_index >= $total_steps ) {
            // Fim da automação, marca como concluída.
            $wpdb->update(
                "{$wpdb->prefix}bluesendmail_automation_queue",
                array( 'status' => 'completed' ),
                array( 'queue_id' => $queue_item->queue_id )
            );
        } else {
            $wpdb->update(
                "{$wpdb->prefix}bluesendmail_automation_queue",
                array(
                    'next_step_index' => $next_step_index,
                    'status'          => 'waiting',
                    'process_at'      => $process_at ?? current_time( 'mysql', 1 ),
                ),
                array( 'queue_id' => $queue_item->queue_id )
            );
        }
    }
}
