<?php
/**
 * Classe Abstrata para Ações de Automação.
 * Define a estrutura que todas as ações devem seguir.
 *
 * @package BlueSendMail
 * @since 2.3.0
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

    /**
     * Define se esta ação é uma condicional (cria ramos Sim/Não).
     * @var bool
     */
    public $is_condition = false;

    /**
     * Referência ao motor de automação.
     * @var BSM_Automations
     */
    protected $automations_engine;


    public function __construct() {
        $this->automations_engine = BlueSendMail::get_instance()->automations;
        $this->init();
    }

    /**
     * Método de inicialização para definir as propriedades da ação.
     */
    abstract public function init();

    /**
     * Executa a lógica principal da ação.
     *
     * @param stdClass $queue_item O item da fila de automação.
     * @param array $step_settings As configurações para este passo específico.
     * @return void
     */
    abstract public function run( $queue_item, $step_settings );

    /**
     * Retorna os campos de configuração para esta ação.
     *
     * @return array
     */
    public function get_fields() {
        return [];
    }
}

