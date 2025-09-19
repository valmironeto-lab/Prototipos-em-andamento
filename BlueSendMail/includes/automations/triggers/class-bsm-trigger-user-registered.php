<?php
/**
 * Gatilho: Um novo usuário se cadastra no WordPress.
 *
 * @package BlueSendMail
 * @version 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Trigger_User_Registered extends BSM_Abstract_Trigger {

    public function init() {
        $this->id    = 'user_registered';
        $this->name  = __( 'Usuário se Cadastra no WordPress', 'bluesendmail' );
        $this->group = __( 'Usuários WordPress', 'bluesendmail' );
    }

    public function register_hooks() {
        add_action( 'user_register', array( $this, 'handle_event' ), 20, 1 );
    }

    public function handle_event( $user_id ) {
        global $wpdb;

        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) {
            return;
        }

        // Verifica se já existe um contato com este e-mail
        $contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bluesendmail_contacts WHERE email = %s", $user_data->user_email ) );
        $contact_id = $contact ? $contact->contact_id : null;

        // Se não existir, cria um novo contato
        if ( ! $contact_id ) {
            $contact_data = [
                'email'      => $user_data->user_email,
                'first_name' => $user_data->first_name,
                'last_name'  => $user_data->last_name,
                'status'     => 'subscribed',
                'created_at' => current_time( 'mysql', 1 ),
                'updated_at' => current_time( 'mysql', 1 ),
            ];
            $wpdb->insert( "{$wpdb->prefix}bluesendmail_contacts", $contact_data );
            $contact_id = $wpdb->insert_id;
            $contact = (object) $contact_data;
            $contact->contact_id = $contact_id;
        }

        if ( $contact ) {
            $data_layer = new BSM_Data_Layer( [ 'contact' => $contact ] );
            $this->automations_engine->handle_trigger_event( $this->id, $data_layer );
        }
    }
}
