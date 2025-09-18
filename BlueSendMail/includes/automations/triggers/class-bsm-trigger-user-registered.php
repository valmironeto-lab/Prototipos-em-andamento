<?php
/**
 * Gatilho: Um novo usuário se cadastra no WordPress.
 *
 * @package BlueSendMail
 * @since 2.3.0
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
        add_action( 'user_register', array( $this, 'handle_event' ), 10, 1 );
    }

    public function handle_event( $user_id ) {
        global $wpdb;

        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) {
            return;
        }

        // Verifica se já existe um contato com este e-mail
        $contact_id = $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM {$wpdb->prefix}bluesendmail_contacts WHERE email = %s", $user_data->user_email ) );

        // Se não existir, cria um novo contato
        if ( ! $contact_id ) {
            $wpdb->insert(
                "{$wpdb->prefix}bluesendmail_contacts",
                [
                    'email'      => $user_data->user_email,
                    'first_name' => $user_data->first_name,
                    'last_name'  => $user_data->last_name,
                    'status'     => 'subscribed',
                    'created_at' => current_time( 'mysql', 1 ),
                    'updated_at' => current_time( 'mysql', 1 ),
                ]
            );
            $contact_id = $wpdb->insert_id;
        }

        if ( $contact_id ) {
            $data_layer = new BSM_Data_Layer();
            $data_layer->add_item( 'contact_id', $contact_id );
            BlueSendMail::get_instance()->automations->maybe_trigger_automations( $this, $data_layer );
        }
    }
}

