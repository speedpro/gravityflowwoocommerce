<?php
/**
 * Gravity Flow Merge Tag Assignee WooCommerce Checkout Url
 *
 * @package     GravityFlow
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

if ( ! function_exists( 'WC' ) ) {
	return;
}

/**
 * Class Gravity_Flow_Merge_Tag_Assignee_WooCommerce
 *
 * @since 1.1
 */
class Gravity_Flow_Merge_Tag_Assignee_WooCommerce_Checkout_Url extends Gravity_Flow_Merge_Tag_Assignee_Base {

	/**
	 * The name of the merge tag.
	 *
	 * @since 1.1
	 *
	 * @var string
	 */
	public $name = 'woocommerce_checkout_url';

	/**
	 * The regular expression to use for the matching.
	 *
	 * @since 1.1
	 *
	 * @var string
	 */
	protected $regex = '/{workflow_woocommerce_checkout_(url|link)(:(.*?))?}/';

	/**
	 * Replace the {workflow_woocommerce_checkout_url}, and {workflow_woocommerce_checkout_link} merge tags.
	 *
	 * @since 1.1
	 *
	 * @param string $text The text being processed.
	 *
	 * @return string
	 */
	public function replace( $text ) {
		$matches = $this->get_matches( $text );

		if ( ! empty( $matches ) ) {
			foreach ( $matches as $match ) {
				$full_tag       = $match[0];
				$type           = $match[1];
				$options_string = isset( $match[2] ) ? $match[2] : '';

				$a = $this->get_attributes( $options_string, array(
					'text' => esc_html__( 'Create a WooCommerce order', 'gravityflowwoocommerce' ),
				) );

				$entry_id = $this->step ? $this->step->get_entry_id() : false;
				if ( empty( $entry_id ) && ! empty( $this->entry ) ) {
					$entry_id = $this->entry['id'];
				}

				if ( empty( $entry_id ) ) {
					return $text;
				}

				$query_args = array(
					'workflow_order_entry_id' => $entry_id,
					'workflow_order_hash'     => gravity_flow_woocommerce()->get_workflow_order_hash( $entry_id, $this->step ),
				);

				$assignee = $this->assignee;
				if ( $assignee->get_type() === 'email' ) {
					$token_lifetime_days              = apply_filters( 'gravityflow_entry_token_expiration_days', 30, $assignee );
					$token_expiration_timestamp       = strtotime( '+' . (int) $token_lifetime_days . ' days' );
					$access_token                     = gravity_flow()->generate_access_token( $assignee, null, $token_expiration_timestamp );
					$query_args['gflow_access_token'] = $access_token;
				}

				$url = add_query_arg(
					$query_args,
					wc_get_checkout_url()
				);
				/**
				 * Filter the payment step hash url.
				 *
				 * @since 1.1
				 *
				 * @param string $url URL.
				 * @param int $entry_id Entry id.
				 * @param Gravity_Flow_Step $this Gravity Flow step.
				 */
				$url       = apply_filters( 'gravityflowwoocommerce_checkout_step_url', $url, $entry_id, $this );

				if ( $type === 'link' ) {
					$url = sprintf( '<a href="%s">%s</a>', $url, $a['text'] );
				}

				$text = str_replace( $full_tag, $url, $text );
			}
		}

		return $text;
	}
}

Gravity_Flow_Merge_Tags::register( new Gravity_Flow_Merge_Tag_Assignee_WooCommerce_Checkout_Url );
