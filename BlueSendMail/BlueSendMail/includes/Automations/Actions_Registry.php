<?php
/**
 * Registo de todas as Ações de Automação disponíveis.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Automations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions_Registry extends Registry {

    protected static $includes = [];
    protected static $loaded = [];

    /**
     * Carrega o array de ações disponíveis.
     */
    protected static function load_includes() {
        self::$includes = [
            'action' => Actions\Send_Campaign::class,
            'delay'  => Actions\Delay::class,
        ];
    }
}
