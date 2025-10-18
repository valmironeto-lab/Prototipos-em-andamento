<?php
/**
 * Gerencia a inicialização de todas as funcionalidades do painel de administração.
 *
 * @package BlueSendMail
 */

namespace BlueSendMail\Core;

// Importa as classes que serão utilizadas para facilitar a leitura.
use BlueSendMail\Admin\BSM_Admin_Menu;
use BlueSendMail\Admin\BSM_Admin_Assets;
use BlueSendMail\Admin\BSM_Admin_Handler;
use BlueSendMail\Admin\Pages\BSM_Dashboard_Page;
use BlueSendMail\Admin\Pages\BSM_Campaigns_Page;
use BlueSendMail\Admin\Pages\BSM_Contacts_Page;
use BlueSendMail\Admin\Pages\BSM_Lists_Page;
use BlueSendMail\Admin\Pages\BSM_Forms_Page;
use BlueSendMail\Admin\Pages\BSM_Import_Page;
use BlueSendMail\Admin\Pages\BSM_Reports_Page;
use BlueSendMail\Admin\Pages\BSM_Settings_Page;
use BlueSendMail\Admin\Pages\BSM_Logs_Page;
use BlueSendMail\Admin\Pages\BSM_Templates_Page;
use BlueSendMail\Admin\Pages\BSM_Automations_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Admin {

	private $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->instantiate_classes();
	}

	/**
	 * Instancia as classes da área administrativa de forma segura.
	 * Não é mais necessário carregar dependências manualmente.
	 */
	private function instantiate_classes() {
		$pages = [];
		$page_classes = [
			'dashboard'   => BSM_Dashboard_Page::class,
			'campaigns'   => BSM_Campaigns_Page::class,
			'contacts'    => BSM_Contacts_Page::class,
			'lists'       => BSM_Lists_Page::class,
			'forms'       => BSM_Forms_Page::class,
			'import'      => BSM_Import_Page::class,
			'reports'     => BSM_Reports_Page::class,
			'settings'    => BSM_Settings_Page::class,
			'logs'        => BSM_Logs_Page::class,
			'templates'   => BSM_Templates_Page::class,
            'automations' => BSM_Automations_Page::class,
		];

		// Itera e instancia apenas as classes que realmente existem
		foreach ( $page_classes as $slug => $class_name ) {
			if ( class_exists( $class_name ) ) {
				$pages[ $slug ] = new $class_name( $this->plugin );
			}
		}

		new BSM_Admin_Menu( $pages );
		new BSM_Admin_Assets( $this->plugin );
		new BSM_Admin_Handler( $this->plugin );

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
