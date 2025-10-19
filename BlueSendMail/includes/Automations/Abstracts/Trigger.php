<?php
/**
 * Classe Abstrata para Gatilhos de Automação.
 * Define a estrutura que todos os gatilhos devem seguir.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Automations\Abstracts;

use BlueSendMail\Core\Automation_Engine;
use BlueSendMail\Automations\Data_Layer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Trigger {

    public string $id;
    public string $name;
    public string $group;

    public function __construct() {
        $this->init();
    }

    /**
     * Inicializa as propriedades do gatilho (id, name, group).
     */
    abstract public function init();

    /**
     * Registra os ganchos (actions/filters) do WordPress que este gatilho escuta.
     * @param Automation_Engine $engine A instância do motor de automação.
     */
    abstract public function register_hooks( Automation_Engine $engine );

    /**
     * Valida se as opções da automação específica foram atendidas.
     *
     * @param array $trigger_settings Configurações salvas para a automação.
     * @param Data_Layer $data_layer Dados do evento.
     * @return bool
     */
    abstract public function validate_options( array $trigger_settings, Data_Layer $data_layer );

    /**
     * Retorna os campos de configuração para este gatilho para o admin.
     * @return array
     */
    public function get_fields() : array {
        return [];
    }
}

