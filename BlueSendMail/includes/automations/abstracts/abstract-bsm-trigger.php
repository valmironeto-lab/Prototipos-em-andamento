<?php
/**
 * Classe Abstrata para Gatilhos de Automação.
 * Define a estrutura que todos os gatilhos devem seguir.
 *
 * @package BlueSendMail
 * @since 2.3.0
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
     * O grupo ao qual o gatilho pertence (para organização na UI).
     * @var string
     */
    public $group;

    public function __construct() {
        $this->init();
    }

    /**
     * Método de inicialização para definir as propriedades do gatilho.
     * Deve ser implementado por cada classe de gatilho.
     */
    abstract public function init();

    /**
     * Registra os ganchos (actions/filters) do WordPress que este gatilho escuta.
     * Deve ser implementado por cada classe de gatilho.
     */
    abstract public function register_hooks();

    /**
     * Retorna os campos de configuração adicionais para este gatilho.
     *
     * @return array
     */
    public function get_fields() {
        return [];
    }

    /**
     * Valida as opções salvas para um gatilho específico contra os dados do evento.
     * Ex: Verifica se um contato foi adicionado à lista específica configurada no gatilho.
     *
     * @param array $trigger_settings As configurações salvas para a automação.
     * @param BSM_Data_Layer $data_layer A camada de dados do evento.
     * @return bool
     */
    public function validate_options( $trigger_settings, $data_layer ) {
        // Por padrão, se não houver validação específica, o gatilho passa.
        return true;
    }
}

