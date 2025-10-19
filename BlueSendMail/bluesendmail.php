<?php
/**
 * Plugin Name:       BlueSendMail
 * Plugin URI:        https://blueagenciadigital.com.br/bluesendmail
 * Description:       Uma plataforma de e-mail marketing e automação nativa do WordPress para gerenciar contatos, criar campanhas e garantir alta entregabilidade.
 * Version:           2.1.0
 * Author:            Blue Mkt Digital
 * Author URI:        https://blueagenciadigital.com.br/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bluesendmail
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.2
 */

namespace BlueSendMail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes do Plugin
define( 'BLUESENDMAIL_VERSION', '2.1.0' );
define( 'BLUESENDMAIL_PLUGIN_FILE', __FILE__ );
define( 'BLUESENDMAIL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLUESENDMAIL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Carrega o autoloader do Composer
if ( file_exists( BLUESENDMAIL_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once BLUESENDMAIL_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Autoloader Auxiliar para Classes Legadas (WP_List_Table).
 */
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'BlueSendMail_') === 0 && strpos($class_name, '_List_Table') !== false) {
        $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
        $file_path = BLUESENDMAIL_PLUGIN_DIR . 'includes/Tables/' . $file_name;

        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});


// Funções de Ativação/Desativação
register_activation_hook( BLUESENDMAIL_PLUGIN_FILE, array( __NAMESPACE__ . '\Core\DB', 'on_activation' ) );
register_deactivation_hook( BLUESENDMAIL_PLUGIN_FILE, __NAMESPACE__ . '\Core\Cron::on_deactivation' );

/**
 * Função de inicialização do plugin.
 */
function bluesendmail_init() {
	Plugin::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\bluesendmail_init' );

