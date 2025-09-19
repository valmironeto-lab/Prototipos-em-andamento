<?php
/**
 * Registo de todos os Gatilhos de Automação disponíveis.
 *
 * @package BlueSendMail
 * @version 2.2.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Triggers_Registry extends BSM_Registry {

    /**
     * @var array Armazena as definições de classe dos gatilhos. Redeclarado para garantir escopo único.
     */
    protected static $includes = [];

    /**
     * @var array Armazena as instâncias já carregadas dos gatilhos. Redeclarado para garantir escopo único.
     */
    protected static $loaded = [];
    
    /**
     * Carrega o array de gatilhos disponíveis.
     * Para adicionar um novo gatilho, basta criar a classe e adicioná-la a este array.
     */
    protected static function load_includes() {
        self::$includes = [
            'contact_added_to_list' => 'BSM_Trigger_Contact_Added_To_List',
            // Futuramente: 'user_registered' => 'BSM_Trigger_User_Registered',
        ];

        // Inclui os ficheiros das classes de gatilho
        foreach ( self::$includes as $id => $class_name ) {
            $file = BLUESENDMAIL_PLUGIN_DIR . 'includes/automations/triggers/class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
}

