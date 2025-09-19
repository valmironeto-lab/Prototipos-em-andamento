<?php
/**
 * Contacts List Table Class.
 *
 * @package BlueSendMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BlueSendMail_Contacts_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Contato', 'bluesendmail' ),
				'plural'   => __( 'Contatos', 'bluesendmail' ),
				'ajax'     => false,
			)
		);
	}
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'E-mail', 'bluesendmail' ),
			'name'       => __( 'Nome', 'bluesendmail' ),
			'lists'      => __( 'Listas', 'bluesendmail' ),
			'status'     => __( 'Status', 'bluesendmail' ),
			'created_at' => __( 'Data de Inscrição', 'bluesendmail' ),
		);
	}
	public function get_bulk_actions() {
		return array( 'bulk-delete' => __( 'Excluir', 'bluesendmail' ) );
	}
	public function prepare_items() {
		global $wpdb;
		$table_contacts        = $wpdb->prefix . 'bluesendmail_contacts';
		$table_lists           = $wpdb->prefix . 'bluesendmail_lists';
		$table_contact_lists   = $wpdb->prefix . 'bluesendmail_contact_lists';
		$per_page              = 20;
		$current_page          = $this->get_pagenum();
		$orderby               = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_key( $_REQUEST['orderby'] ) : 'created_at';
		$order                 = ( ! empty( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), array( 'ASC', 'DESC' ) ) ) ? strtoupper( $_REQUEST['order'] ) : 'DESC';
		$offset                = ( $current_page - 1 ) * $per_page;

		// Consulta OTIMIZADA com GROUP_CONCAT para buscar listas em uma única query.
		$sql = $wpdb->prepare(
			"SELECT c.*, GROUP_CONCAT(l.name SEPARATOR ', ') as list_names
             FROM {$table_contacts} AS c
             LEFT JOIN {$table_contact_lists} AS cl ON c.contact_id = cl.contact_id
             LEFT JOIN {$table_lists} AS l ON cl.list_id = l.list_id
             GROUP BY c.contact_id
             ORDER BY {$orderby} {$order}
             LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		$this->items = $wpdb->get_results( $sql, ARRAY_A );

		$total_items = $wpdb->get_var( "SELECT COUNT(contact_id) FROM $table_contacts" );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}
	public function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'name'       => array( 'first_name', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="contact[]" value="%s" />', $item['contact_id'] );
	}
	protected function column_name( $item ) {
		$name = trim( $item['first_name'] . ' ' . $item['last_name'] );
		return $name ? esc_html( $name ) : '—';
	}
	protected function column_lists( $item ) {
		// Agora usamos o campo 'list_names' que veio da consulta otimizada.
		return ! empty( $item['list_names'] ) ? esc_html( $item['list_names'] ) : '—';
	}
	protected function column_email( $item ) {
		$edit_url     = admin_url( 'admin.php?page=bluesendmail-contacts&action=edit&contact=' . $item['contact_id'] );
		$delete_nonce = wp_create_nonce( 'bsm_delete_contact_' . $item['contact_id'] );
		$delete_url   = admin_url( 'admin.php?page=bluesendmail-contacts&action=delete&contact=' . $item['contact_id'] . '&_wpnonce=' . $delete_nonce );
		$actions      = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( 'Editar', 'bluesendmail' ) ),
			'delete' => sprintf( '<a href="%s" style="color:red;" onclick="return confirm(\'Tem certeza que deseja excluir este contato? Esta ação não pode ser desfeita.\');">%s</a>', esc_url( $delete_url ), __( 'Excluir', 'bluesendmail' ) ),
		);
		return sprintf( '<strong>%1$s</strong> %2$s', esc_html( $item['email'] ), $this->row_actions( $actions ) );
	}
}
