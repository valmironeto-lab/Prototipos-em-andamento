<?php
/**
 * Gatilho: Contato é adicionado a uma lista.
 *
 * @package BlueSendMail
 * @version 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Trigger_Contact_Added_To_List extends BSM_Abstract_Trigger {

    public function init() {
        $this->id    = 'contact_added_to_list';
        $this->name  = __( 'Contato Adicionado à Lista', 'bluesendmail' );
        $this->group = __( 'Contatos', 'bluesendmail' );
    }

    public function register_hooks() {
        add_action( 'bsm_contact_added_to_list', array( $this, 'handle_event' ), 10, 2 );
    }

    public function get_fields() {
        global $wpdb;
        $all_lists = $wpdb->get_results( "SELECT list_id, name FROM {$wpdb->prefix}bluesendmail_lists ORDER BY name ASC", ARRAY_A );
        $options = [];
        if ( $all_lists ) {
            foreach ( $all_lists as $list ) {
                $options[ $list['list_id'] ] = $list['name'];
            }
        }

        return [
            [
                'id' => 'list_id',
                'type' => 'select',
                'label' => __( 'Selecione a Lista', 'bluesendmail' ),
                'options' => $options
            ]
        ];
    }

    public function handle_event( $contact_id, $list_id ) {
        $contact = get_user_by( 'id', $contact_id ); // Corrigido para obter dados do contato
        if (!$contact) {
            // Se não for um usuário WP, buscar na tabela de contatos
            global $wpdb;
            $contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bluesendmail_contacts WHERE contact_id = %d", $contact_id));
        }
        
        if ($contact) {
            $data_layer = new BSM_Data_Layer( [
                'contact' => $contact,
                'list_id' => $list_id
            ] );
            $this->automations_engine->handle_trigger_event( $this->id, $data_layer );
        }
    }

    public function validate_options( $trigger_settings, $data_layer ) {
        $event_list_id = $data_layer->get_item('list_id');
        $required_list_id = absint( $trigger_settings['list_id'] ?? 0 );

        return $event_list_id === $required_list_id;
    }
}
