<?php
/**
 * Classe Abstrata para Ações de Automação.
 * Define o contrato que todas as ações devem seguir.
 *
 * @package BlueSendMail
 * @version 2.2.0
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
     * @param stdClass       $queue_item  O objeto do item da fila do banco de dados.
     * @param BSM_Data_Layer $data_layer  A camada de dados com informações do contato, etc.
     * @param array          $settings    As configurações do passo (step) salvas no banco de dados.
     */
    abstract public function run( $queue_item, $data_layer, $settings );

    /**
     * Retorna os campos de configuração para esta ação.
     *
     * @return array
     */
    public function get_fields() {
        return [];
    }
    
    /**
     * Enfileira o próximo passo na sequência da automação.
     *
     * @param stdClass $current_queue_item O item da fila que acabou de ser processado.
     * @param string|null $process_at_gmt Data/hora GMT para processar a próxima ação. Null para processamento imediato.
     */
    protected function schedule_next_step( $current_queue_item, $process_at_gmt = null ) {
        global $wpdb;

        $automation_id = $current_queue_item->automation_id;
        
        // Encontra o passo atual para obter sua ordem.
        $current_step = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE step_id = %d", $current_queue_item->step_id ) );
        
        if ( ! $current_step ) {
            $this->complete_automation( $current_queue_item );
            return;
        }

        // Procura pelo próximo passo na mesma automação com uma ordem maior.
        $next_step = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bluesendmail_automation_steps WHERE automation_id = %d AND step_order > %d ORDER BY step_order ASC LIMIT 1",
            $automation_id,
            $current_step->step_order
        ) );

        if ( $next_step ) {
            // Se encontrou um próximo passo, cria um novo item na fila para ele.
            $wpdb->insert(
                "{$wpdb->prefix}bluesendmail_automation_queue",
                [
                    'automation_id' => $automation_id,
                    'contact_id'    => $current_queue_item->contact_id,
                    'step_id'       => $next_step->step_id,
                    'status'        => 'waiting',
                    'data_layer'    => maybe_serialize( maybe_unserialize($current_queue_item->data_layer) ),
                    'process_at'    => $process_at_gmt ?? current_time( 'mysql', 1 ),
                    'created_at'    => current_time( 'mysql', 1 ),
                ]
            );
        }
        
        // Marca o passo atual como completo.
        $this->complete_automation( $current_queue_item );
    }

    /**
     * Marca um item da fila como concluído. Se não houver mais passos, a automação é finalizada para o contato.
     *
     * @param stdClass $queue_item
     */
    private function complete_automation( $queue_item ) {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}bluesendmail_automation_queue",
            [ 'status' => 'completed' ],
            [ 'queue_id' => $queue_item->queue_id ]
        );
    }
}
