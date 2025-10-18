<?php
/**
 * Classe Abstrata para Handlers.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

use BlueSendMail\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Abstract_Handler {

    /**
     * @var Plugin
     */
    protected $plugin;

    public function __construct( Plugin $plugin ) {
        $this->plugin = $plugin;
        $this->register_hooks();
    }

    /**
     * Registra os ganchos (actions/filters) do WordPress.
     */
    abstract public function register_hooks();
}
