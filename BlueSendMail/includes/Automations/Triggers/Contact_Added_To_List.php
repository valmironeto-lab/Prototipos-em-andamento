<?php
/**
 * Gatilho: Contacto é adicionado a uma lista.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Automations\Triggers;

use BlueSendMail\Automations\Abstracts\Trigger;
use BlueSendMail\Automations\Data_Layer;
use BlueSendMail\Core\Automation_Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Contact_Added_To_List extends Trigger {

    public function init() {
        $this->id    = 'contact_added_to_list';
        $this->name  = __( 'Contacto Adicionado à Lista', 'bluesendmail' );
        $this->group = __( 'Contactos', 'bluesendmail' );
    }

    public function register_hooks( Automation_Engine $engine ) {
        add_action( 'bsm_contact_added_to_list', function( $contact_id, $list_id ) use ( $engine ) {
            $this->handle_event( $contact_id, $list_id, $engine );
        }, 10, 2 );
    }
    
    public function handle_event( $contact_id, $list_id, Automation_Engine $engine ) {
        global $wpdb;

        $contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_contacts WHERE contact_id = %d", $contact_id ) );
        
        if ( $contact ) {
            $data_layer = new Data_Layer( [
                'contact' => $contact,
                'list_id' => $list_id
            ] );
            $engine->trigger_event( $this->id, $data_layer );
        }
    }

    public function validate_options( array $trigger_settings, Data_Layer $data_layer ) : bool {
        $required_list_id = absint( $trigger_settings['list_id'] ?? 0 );
        if ( $required_list_id === 0 ) {
            return true;
        }
        $event_list_id = $data_layer->get_item('list_id');
        return absint($event_list_id) === $required_list_id;
    }
}
