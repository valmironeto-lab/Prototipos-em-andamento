<?php
/**
 * Gerencia o processamento de ações de Campanhas.
 *
 * @package BlueSendMail
 */
namespace BlueSendMail\Admin\Handlers;

use BlueSendMail\Core\DB;
use DateTime;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Campaigns_Handler extends Abstract_Handler {

    public function register_hooks() {
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_action( 'wp_ajax_bsm_get_template_content', array( $this, 'ajax_get_template_content' ) );
    }

    public function handle_actions() {
        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'bluesendmail' ) !== false ) {
			if ( false === get_transient( 'bsm_scheduled_check_lock' ) ) {
				set_transient( 'bsm_scheduled_check_lock', true, 5 * MINUTE_IN_SECONDS );
				$this->plugin->cron->enqueue_scheduled_campaigns();
			}
		}

		$page = $_GET['page'] ?? '';
		if ( ( 'bluesendmail-campaigns' === $page || 'bluesendmail-new-campaign' === $page ) && ( isset( $_POST['bsm_save_draft'] ) || isset( $_POST['bsm_send_campaign'] ) || isset( $_POST['bsm_schedule_campaign'] ) ) ) {
            $this->handle_save_campaign();
        }
    }
    
    public function ajax_get_template_content() {
        check_ajax_referer( 'bsm-template-nonce', 'nonce' );
        if ( ! current_user_can( 'bsm_manage_campaigns' ) ) { wp_send_json_error( array( 'message' => 'Permissão negada.' ), 403 ); }
        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        if ( ! $template_id ) { wp_send_json_success( array( 'content' => '' ) ); }
        global $wpdb;
        $content = $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$wpdb->prefix}bluesendmail_templates WHERE template_id = %d", $template_id ) );
        if ( is_null( $content ) ) { wp_send_json_error( array( 'message' => 'Template não encontrado.' ), 404 ); }
        wp_send_json_success( array( 'content' => $content ) );
    }

    private function handle_save_campaign() {
		if ( ! isset( $_POST['bsm_save_campaign_nonce'] ) || ! wp_verify_nonce( $_POST['bsm_save_campaign_nonce'], 'bsm_save_campaign_action' ) ) {
            wp_die( esc_html__( 'Falha na verificação de segurança.', 'bluesendmail' ) );
        }
		if ( ! current_user_can( 'bsm_manage_campaigns' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'bluesendmail' ) );
        }
		
        global $wpdb;
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$title = sanitize_text_field( wp_unslash( $_POST['bsm_title'] ?? '' ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['bsm_subject'] ?? '' ) );
		$preheader = sanitize_text_field( wp_unslash( $_POST['bsm_preheader'] ?? '' ) );
		$content = wp_kses_post( wp_unslash( $_POST['bsm_content'] ?? '' ) );
		$lists = isset( $_POST['bsm_lists'] ) ? array_map( 'absint', (array) $_POST['bsm_lists'] ) : array();
		
        $is_send_now = isset( $_POST['bsm_send_campaign'] );
		$is_schedule = isset( $_POST['bsm_schedule_campaign'] );
		$schedule_enabled = isset( $_POST['bsm_schedule_enabled'] ) && '1' === $_POST['bsm_schedule_enabled'];
		$schedule_date = sanitize_text_field( $_POST['bsm_schedule_date'] ?? '' );
		$schedule_time = sanitize_text_field( $_POST['bsm_schedule_time'] ?? '' );
		$scheduled_for = null;
		
        if ( ( $is_schedule || $schedule_enabled ) && ! empty( $schedule_date ) && ! empty( $schedule_time ) ) {
			$schedule_datetime = new DateTime( "$schedule_date $schedule_time", $this->plugin->bsm_get_timezone() );
			$schedule_datetime->setTimezone( new DateTimeZone( 'UTC' ) );
			$scheduled_for = $schedule_datetime->format( 'Y-m-d H:i:s' );
		}

		$status = 'draft';
		if ( $is_send_now ) {
            $status = 'queued';
        } elseif ( $is_schedule && $scheduled_for ) {
            $status = 'scheduled';
        }

		$data = array( 
            'title' => $title, 
            'subject' => $subject, 
            'preheader' => $preheader, 
            'content' => $content, 
            'status' => $status, 
            'lists' => maybe_serialize( $lists ), 
            'scheduled_for' => $scheduled_for 
        );

		if ( $campaign_id ) {
			$wpdb->update( "{$wpdb->prefix}bluesendmail_campaigns", $data, array( 'campaign_id' => $campaign_id ) );
		} else {
			$data['created_at'] = current_time( 'mysql', 1 );
			$wpdb->insert( "{$wpdb->prefix}bluesendmail_campaigns", $data );
			$campaign_id = $wpdb->insert_id;
		}

		if ( ! $campaign_id ) { 
            set_transient( 'bsm_admin_notice', ['type' => 'error', 'message' => __('Falha ao salvar a campanha.', 'bluesendmail')], 30 ); 
            wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-new-campaign' ) ); 
            exit; 
        }

		$this->plugin->log_event( 'info', 'campaign', "Campanha #{$campaign_id} salva com status '{$status}'." );
		
        if ( $is_send_now ) {
			$this->plugin->enqueue_campaign_recipients( $campaign_id );
			set_transient( 'bsm_admin_notice', ['type' => 'success', 'message' => __('Campanha enfileirada para envio imediato!', 'bluesendmail')], 30 );
			wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-campaigns' ) );
			exit;
		}

		if ( $is_schedule ) {
			set_transient( 'bsm_admin_notice', ['type' => 'success', 'message' => __('Campanha agendada com sucesso!', 'bluesendmail')], 30 );
			wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-campaigns' ) );
			exit;
		}
        
        set_transient( 'bsm_admin_notice', ['type' => 'success', 'message' => __('Rascunho da campanha salvo com sucesso!', 'bluesendmail')], 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=bluesendmail-campaigns&action=edit&campaign=' . $campaign_id ) );
		exit;
	}
}
