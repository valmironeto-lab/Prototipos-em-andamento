<?php
/**
 * Classe Abstrata para Gatilhos de Automação.
 * Define a estrutura que todos os gatilhos devem seguir.
 *
 * @package BlueSendMail
 * @version 2.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BSM_Abstract_Trigger {

    /**
     * O ID único do gatilho (slug).
     * @var string
     */
    public $id;

    /**
     * O nome do gatilho para exibição.
     * @var string
     */
    public $name;

    /**
     * O grupo ao qual o gatilho pertence.
     * @var string
     */
    public $group;

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
     * Inicializa as propriedades do gatilho (id, name, group).
     */
    abstract public function init();

    /**
     * Registra os ganchos (actions/filters) do WordPress que este gatilho escuta.
     */
    abstract public function register_hooks();

    /**
     * Retorna os campos de configuração para este gatilho.
     * @return array
     */
    public function get_fields() {
        return [];
    }
}

