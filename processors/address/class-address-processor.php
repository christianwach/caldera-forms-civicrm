<?php

/**
 * CiviCRM Caldera Forms Address Processor Class.
 *
 * @since 0.2
 */
class CiviCRM_Caldera_Forms_Address_Processor {

	/**
	 * The processor key.
	 *
	 * @since 0.2
	 * @access public
	 * @var str $key_name The processor key
	 */
	public $key_name = 'civicrm_address';

	/**
	 * Fields to ignore while prepopulating
	 *
	 * @since 0.4
	 * @access public
	 * @var array $fields_to_ignore Fields to ignore
	 */
	public $fields_to_ignore = array( 'contact_link', 'location_type_id' );

	/**
	 * Initialises this object.
	 *
	 * @since 0.2
	 */
	public function __construct() {

		// register this processor
		add_filter( 'caldera_forms_get_form_processors', array( $this, 'register_processor' ) );
		// filter form before rendering
		add_filter( 'caldera_forms_render_get_form', array( $this, 'pre_render') );

	}

	/**
	 * Adds this processor to Caldera Forms.
	 *
	 * @since 0.2
	 *
	 * @uses 'caldera_forms_get_form_processors' filter
	 *
	 * @param array $processors The existing processors
	 * @return array $processors The modified processors
	 */
	public function register_processor( $processors ) {

		$processors[$this->key_name] = array(
			'name' => __( 'CiviCRM Address', 'caldera-forms-civicrm' ),
			'description' => __( 'Add CiviCRM address to contacts', 'caldera-forms-civicrm' ),
			'author' => 'Andrei Mondoc',
			'template' => CF_CIVICRM_INTEGRATION_PATH . 'processors/address/address_config.php',
			'pre_processor' => array( $this, 'pre_processor' ),
		);

		return $processors;

	}

	/**
	 * Form processor callback.
	 *
	 * @since 0.2
	 *
	 * @param array $config Processor configuration
	 * @param array $form Form configuration
	 */
	public function pre_processor( $config, $form ) {

		// globalised transient object
		global $transdata;

		if ( ! empty( $transdata['civicrm']['contact_id_' . $config['contact_link']] ) ) {

			try {
				$address = civicrm_api3( 'Address', 'getsingle', array(
					'sequential' => 1,
					'contact_id' => $transdata['civicrm']['contact_id_' . $config['contact_link']],
					'location_type_id' => $config['location_type_id'],
				));
			} catch ( CiviCRM_API3_Exception $e ) {
				// Ignore if none found
			}

			// Get form values
			$form_values = CiviCRM_Caldera_Forms_Helper::map_fields_to_processor( $config, $form, $form_values );

			if( ! empty( $form_values ) ) {
				$form_values['contact_id'] = $transdata['civicrm']['contact_id_' . $config['contact_link']]; // Contact ID set in Contact Processor

				// Pass address ID if we got one
				if ( isset( $address ) && is_array( $address ) ) {
					$form_values['id'] = $address['id']; // Address ID
				} else {
					$form_values['location_type_id'] = $config['location_type_id']; // Address Location Type
				}

				// FIXME
				// Concatenate DATE + TIME
				// $form_values['activity_date_time'] = $form_values['activity_date_time'];

				try {
					$create_address = civicrm_api3( 'Address', 'create', $form_values );
				} catch ( CiviCRM_API3_Exception $e ) {
					$error = $e->getMessage() . '<br><br><pre>' . $e->getTraceAsString() . '</pre>';
	                return array( 'note' => $error, 'type' => 'error' );
				}
			}
		}
	}

	/**
	 * Autopopulates Form with Civi data
	 *
	 * @uses 'caldera_forms_render_get_form' filter
	 *
	 * @since 0.2
	 *
	 * @param array $form The form
	 * @return array $form The modified form
	 */
	public function pre_render( $form ){
		// continue as normal if form has no processors
		if( empty( $form['processors'] ) ) return $form;

		// globalised transient object
		global $transdata;

		foreach ( $form['processors'] as $processor => $pr_id ) {

			if( $pr_id['type'] == $this->key_name && isset( $pr_id['runtimes'] ) ){
				if ( isset( $transdata['civicrm']['contact_id_' . $pr_id['config']['contact_link']] ) ) {
					try {

						$civi_contact_address = civicrm_api3( 'Address', 'getsingle', array(
							'sequential' => 1,
							'contact_id' => $transdata['civicrm']['contact_id_' . $pr_id['config']['contact_link']],
							'location_type_id' => $pr_id['config']['location_type_id'],
						));

					} catch ( CiviCRM_API3_Exception $e ) {
						// Ignore if we have more than one address with same location type
					}
				}

				if ( isset( $civi_contact_address ) && ! isset( $civi_contact_address['count'] ) ) {
					$form = CiviCRM_Caldera_Forms_Helper::map_fields_to_prerender(
						$pr_id['config'],
						$form,
						$this->fields_to_ignore,
						$civi_contact_address
					);
				}

				// Clear Address data
				unset( $civi_contact_address );
			}
		}

		return $form;
	}
}
