<?php
/**
 * Registo de todas as Ações de Automação disponíveis.
 *
 * @package BlueSendMail
 * @version 2.2.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Actions_Registry extends BSM_Registry {

    /**
     * @var array Armazena as definições de classe das ações. Redeclarado para garantir escopo único.
     */
    protected static $includes = [];

    /**
     * @var array Armazena as instâncias já carregadas das ações. Redeclarado para garantir escopo único.
     */
    protected static $loaded = [];

    /**
     * Carrega o array de ações disponíveis.
     * Para adicionar uma nova ação, basta criar a classe e adicioná-la a este array.
     */
    protected static function load_includes() {
        self::$includes = [
            'send_campaign' => 'BSM_Action_Send_Campaign',
            'delay'         => 'BSM_Action_Delay',
        ];

        // Inclui os ficheiros das classes de ação
        foreach ( self::$includes as $id => $class_name ) {
            $file = BLUESENDMAIL_PLUGIN_DIR . 'includes/automations/actions/class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
}

