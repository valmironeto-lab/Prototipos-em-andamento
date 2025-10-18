<?php
/**
 * Registo de todos os Gatilhos de Automação disponíveis.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Automations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Triggers_Registry extends Registry {

    protected static $includes = [];
    protected static $loaded = [];
    
    /**
     * Carrega o array de gatilhos disponíveis.
     */
    protected static function load_includes() {
        self::$includes = [
            'contact_added_to_list' => Triggers\Contact_Added_To_List::class,
        ];
    }
}
