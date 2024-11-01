<?php
/*
 * Plugin Name: WooCommerce CSE Payment Gateway
 * Description: Add a payment method to WooCommerce using CSE Gateway.
 * Author: Henry Tran
 * Version: 1.0

 */
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	//Khởi tạo các class
	add_action('plugins_loaded', 'init_gateway_class');
    add_action( 'callback', 'thankyou_custom_payment_redirect');

	//Khởi taoj class payment gayeway
	function init_gateway_class()
	{


		class WC_Gateway_csepay extends WC_Payment_Gateway
		{
			var $notify_url;

			/**
			 * Constructor for the gateway.
			 *
			 * @access public
			 * @return \WC_Gateway_csepay
			 */
			public function __construct()
			{
				global $woocommerce;

				$this->id = 'csepay';
				$this->has_fields = false;
				$this->method_title = __('CSE PAYMENT', 'woocommerce');
				$this->liveurl = 'http://pay.csewallet.io/';
				$this->init_form_fields();
				$this->init_settings();

				$this->title = $this->get_option('title');
				$this->description = $this->get_option('description');
				$this->api_secret = $this->get_option('receiver_acc');
				$this->merchant_id = $this->get_option('merchant_id');
				$this->form_submission_method = false;

				//Action
				add_action('valid-csepay-standard-ipn-request', array($this, 'successful_request'));
				add_action('woocommerce_receipt_csepay', array($this, 'receipt_page'));
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
				add_action('woocommerce_api_wc_gateway_csepay', array($this, 'callback'));
				if (!$this->is_valid_for_use()) $this->enabled = false;
				

			}
			function is_valid_for_use()
			{
				if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_csepay_supported_currencies', array('USD'))))
					return false;
				return true;
			}

			public function admin_options()
			{
				?>
				<h3><?php _e('CSE GATEWAY PAYMENT', 'woocommerce'); ?></h3>
				<?php if ($this->is_valid_for_use()) : ?>

				<table class="form-table">
					<?php
					// Generate the HTML For the settings form.
					$this->generate_settings_html();
					?>
				</table><!--/.form-table-->

			<?php else : ?>
				<div class="inline error"><p>
						<strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('Csepay payment methods do not support currencies on your booth.', 'woocommerce'); ?>
					</p></div>
			<?php
			endif;
			}

			/**
			 * Initialise Gateway Settings Form Fields
			 *
			 * @access public
			 * @return void
			 */
			function init_form_fields()
			{

				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Cse payment', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Enable/Disable', 'woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'woocommerce'),
						'type' => 'text',
						'description' => __('The title of the payment method you want to display to the user.', 'woocommerce'),
						'default' => __('csepay', 'woocommerce'),
						'desc_tip' => true,
					),
					'description' => array(
						'title' => __('Describe payment method', 'woocommerce'),
						'type' => 'textarea',
						'description' => __('Description of the payment method you want to display to users.', 'woocommerce'),
						'default' => __('Pay with csepay. Ensure absolute safety for all transactions', 'woocommerce')
					),
					
					'account_config' => array(
						'title' => __('Configure payment gateway', 'woocommerce'),
						'type' => 'title',
						'description' => '',
					),
					'merchant_id' => array(
						'title' => __('API Key', 'woocommerce'),
						'type' => 'text',
						'description' => __('“APIKey” cse provided when integrated', 'woocommerce'),
						'default' => '',
						'desc_tip' => true,
						
					),
					'receiver_acc' => array(
						'title' => __('API Secret', 'woocommerce'),
						'type' => 'text',
						'description' => __('“APISecret” cse provided when integrated', 'woocommerce'),
						'default' => '',
						'desc_tip' => true,
						
					),
							
				);

			}

			/**
			 * Process the payment and return the result
			 *
			 * @access public
			 * @param int $order_id
			 * @return array
			 */
			function process_payment($order_id)
			{
				$order = new WC_Order($order_id);
				if (!$this->form_submission_method) {
					$csepay_args = $this->get_csepay_args($order);
					if ($this->testmode == 'yes'):
						$csepay_server = $this->testurl; else :
						$csepay_server = $this->liveurl;
					endif;
					$csepay_url = $this->createRequestUrl($csepay_args, $csepay_server);
					return array(
						'result' => 'success',
						'redirect' => $csepay_url
					);
				} else {
					return array(
						'result' => 'success',
						'redirect' => add_query_arg('order', add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
					);
				}
			}


			function get_csepay_args($order)
			{
				global $woocommerce;
				$order_id = $order->id;
				$csepay_args = array(
					'APIKey' => strval($this->merchant_id),
					'APISecret' => strval($this->api_secret),
				);

				$csepay_args['amount'] = $order->order_total;
				if(get_woocommerce_currency()==='USD'||get_woocommerce_currency()==='USD')
				{
					$convert_rate = 0.5;
					$i = 1; 
					while (isset($csepay_args['amount' . $i])) { 
						$csepay_args['amount' . $i] = round( $csepay_args['amount' . $i] / $convert_rate, 2); 
						++$i; 			
						
				}
				$csepay_args['amount'] = round( $csepay_args['amount'] / $convert_rate, 2);
			}
				
				

				return $csepay_args;




			}
		

		          
			function verifyPaymentUrlLive($amount,$message,$payment_type,$order_code,$status,$trans_ref_no,$website_id,$sign)
			{
				
				// My plaintext
				//$secret_key = $this->secure_pass;
				$plaintext = $amount."|".$message."|".$payment_type."|".$order_code."|".$status."|".$trans_ref_no."|".$website_id ."|". $secret_key;
				//print $plaintext;
				// Mã hóa sign
				$verify_secure_code = '';
				$verify_secure_code = strtoupper(hash('sha256', $plaintext));;
				// Xác thực chữ ký của ch? web v?i ch? ký tr? v? t? VTC Pay
				if ($verify_secure_code === $sign) 		return strval($status);
				
				return false;
			}

			private function createRequestUrl($data, $csepay_server)
			{
				$params = $data;
				
				
				$redirect_url = $csepay_server;
				if (strpos($redirect_url, '?') === false) {
					$redirect_url .= '?';
				} else if (substr($redirect_url, strlen($redirect_url) - 1, 1) != '?' && strpos($redirect_url, '&') === false) {
					$redirect_url .= '&';
				}
				
				//$params['bill_to_phone']=urlencode($params['bill_to_phone']);
				// Tạo đoạn url chứa tham số
				$url_params = '';
				foreach ($params as $key => $value) {
					if ($url_params == '')
						$url_params .= $key . '=' . ($value);
					else
						$url_params .= '&' . $key . '=' . ($value);
				}
				return $redirect_url . $url_params;
				//return $plaintext;
			}

		}

		class WC_csepay extends WC_Gateway_csepay
		{
			public function __construct()
			{
				_deprecated_function('WC_csepay', '1.4', 'WC_Gateway_csepay');
				parent::__construct();
			}
		}

		//Defining class gateway
		function add_gateway_class( $methods ) {
			$methods[] = 'WC_Gateway_csepay';
			return $methods;
		}

		add_filter( 'woocommerce_payment_gateways', 'add_gateway_class' );
	}
}