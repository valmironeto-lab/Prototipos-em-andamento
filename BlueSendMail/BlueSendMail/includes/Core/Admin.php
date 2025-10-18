<?php
/**
 * Gerencia a inicialização de todas as funcionalidades do painel de administração.
 *
 * @package BlueSendMail
 */

namespace BlueSendMail\Core;

// Importa as classes que serão utilizadas para facilitar a leitura.
use BlueSendMail\Admin\Menu;
use BlueSendMail\Admin\Assets;
use BlueSendMail\Admin\Pages;
use BlueSendMail\Admin\Handlers;
use BlueSendMail\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->instantiate_classes();
	}

	/**
	 * Instancia as classes da área administrativa de forma segura.
	 */
	private function instantiate_classes() {
		$pages = [];
		$page_classes = [
			'dashboard'   => Pages\Dashboard_Page::class,
			'campaigns'   => Pages\Campaigns_Page::class,
			'contacts'    => Pages\Contacts_Page::class,
			'lists'       => Pages\Lists_Page::class,
			'forms'       => Pages\Forms_Page::class,
			'import'      => Pages\Import_Page::class,
			'reports'     => Pages\Reports_Page::class,
			'settings'    => Pages\Settings_Page::class,
			'logs'        => Pages\Logs_Page::class,
			'templates'   => Pages\Templates_Page::class,
            'automations' => Pages\Automations_Page::class,
		];

		foreach ( $page_classes as $slug => $class_name ) {
			if ( class_exists( $class_name ) ) {
				$pages[ $slug ] = new $class_name( $this->plugin );
			}
		}

		new Menu( $pages );
		new Assets( $this->plugin );
		
        // **ALTERAÇÃO: Instancia os novos Handlers individuais**
        new Handlers\Campaigns_Handler( $this->plugin );
        new Handlers\Contacts_Handler( $this->plugin );
        new Handlers\Lists_Handler( $this->plugin );
        new Handlers\Forms_Handler( $this->plugin );
        new Handlers\Templates_Handler( $this->plugin );
        new Handlers\Automations_Handler( $this->plugin );
        new Handlers\Settings_Handler( $this->plugin );
        new Handlers\Import_Handler( $this->plugin );

        // Adiciona um gancho para notificações
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

    /**
     * Exibe notificações administrativas.
     */
    public function show_admin_notices() {
        $notice = get_transient( 'bsm_admin_notice' );
        if ( $notice ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $notice['type'] ),
                wp_kses_post( $notice['message'] )
            );
            delete_transient( 'bsm_admin_notice' );
        }
    }
}
