<?php
/**
 * Gatilho: Contato abre uma campanha de e-mail.
 *
 * @package BlueSendMail
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSM_Trigger_Contact_Opened_Campaign extends BSM_Abstract_Trigger {

    public function init() {
        $this->id    = 'contact_opened_campaign';
        $this->name  = __( 'Contato Abre uma Campanha', 'bluesendmail' );
        $this->group = __( 'Engajamento', 'bluesendmail' );
    }

    public function register_hooks() {
        add_action( 'bsm_contact_opened_campaign', array( $this, 'handle_event' ), 10, 2 );
    }

    public function get_fields() {
        global $wpdb;
        $campaigns = $wpdb->get_results( "SELECT campaign_id, title FROM {$wpdb->prefix}bluesendmail_campaigns ORDER BY title ASC", ARRAY_A );
        $options = ['any' => __( 'Qualquer Campanha', 'bluesendmail' )];
        if ( $campaigns ) {
            foreach ( $campaigns as $campaign ) {
                $options[ $campaign['campaign_id'] ] = $campaign['title'];
            }
        }

        return [
            [
                'id' => 'campaign_id',
                'type' => 'select',
                'label' => __( 'Selecione a Campanha', 'bluesendmail' ),
                'options' => $options
            ]
        ];
    }

    public function handle_event( $contact_id, $campaign_id ) {
        $data_layer = new BSM_Data_Layer();
        $data_layer->add_item( 'contact_id', $contact_id );
        $data_layer->add_item( 'campaign_id', $campaign_id );

        BlueSendMail::get_instance()->automations->maybe_trigger_automations( $this, $data_layer );
    }

    public function validate_options( $trigger_settings, $data_layer ) {
        $required_campaign_id = $trigger_settings['campaign_id'] ?? 'any';
        if ( 'any' === $required_campaign_id ) {
            return true;
        }

        $event_campaign_id = $data_layer->get_item('campaign_id');
        return absint($event_campaign_id) === absint($required_campaign_id);
    }
}

