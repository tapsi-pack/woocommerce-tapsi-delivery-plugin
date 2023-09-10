<?php

/**
 * Tapsi API
 *
 * @link       https://www.inverseparadox.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Tapsi
 * @subpackage Woocommerce_Tapsi/includes
 */

/**
 * Tapsi API
 *
 * Contains functionality to create and accept delivery quotes,
 * and create, read, update, and delete deliveries in the Drive API.
 *
 * @package    Woocommerce_Tapsi
 * @subpackage Woocommerce_Tapsi/includes
 * @author     Inverse Paradox <erik@inverseparadox.net>
 */
class Woocommerce_Tapsi_API
{

    protected $developer_id;

    protected $env;

    protected $key_id;

    protected $signing_secret;

    protected $jwt;

    protected $api_url = "https://openapi.tapsi.com";

    public function __construct()
    {
        // Get the keys from the options
        if (!$this->get_keys()) {
            // No keys were found for this environment.
            return false;
        }

        $this->get_jwt();
    }

    protected function get_keys()
    {
        // If the JWT already exists we're set
        if (!empty($this->jwt)) return true;

        // Set the prefix for the plugin options
        $prefix = "woocommerce_tapsi_";

        // Get the developer ID
        $this->developer_id = get_option($prefix . 'developer_id');

        // Check the environment
        $this->env = get_option($prefix . 'api_environment');

        // Get the Key ID and Signing Secret for the appropriate environment
        $this->key_id = get_option($prefix . $this->env . '_key_id');
        $this->signing_secret = get_option($prefix . $this->env . '_signing_secret');

        // Check to see if keys need decryption
        $encryption = new Woocommerce_Tapsi_Encryption();
        if ($encryption->is_encrypted($this->key_id)) {
            $this->key_id = $encryption->decrypt($this->key_id);
        }
        if ($encryption->is_encrypted($this->signing_secret)) {
            $this->signing_secret = $encryption->decrypt($this->signing_secret);
        }

        if (empty($this->developer_id) || empty($this->key_id) || empty($this->signing_secret)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the current API mode, sandbox or production
     *
     * @return string 'sandbox' or 'production'
     */
    public function get_env()
    {
        return $this->env;
    }

    /**
     * Encodes data for generating token
     *
     * @param string $data Data to encode
     * @return string Encoded data
     */
    protected function base64_url_encode($data)
    {
        $base64_url = strtr(base64_encode($data), '+/', '-_');
        return rtrim($base64_url, '=');
    }

    /**
     * Decode base64 data from URL
     *
     * @param string $base64_url Encoded data
     * @return string Decoded data
     */
    protected function base64_url_decode($base64_url)
    {
        return base64_decode(strtr($base64_url, '-_', '+/'));
    }

    /**
     * Generate a JSON Web Token (JWT) for API request auth
     *
     * @see https://developer.tapsi.com/en-US/docs/drive/how_to/JWTs/
     *
     * @return string Generated JWT
     */
    public function get_jwt()
    {
        if (!empty($this->jwt)) return $this->jwt;

        // Prepare the JWT header
        $header = json_encode(array(
            'alg' => 'HS256',
            'typ' => 'JWT',
            'dd-ver' => 'DD-JWT-V1',
        ));

        // Prepare the JWT payload
        $payload = json_encode(array(
            'aud' => 'tapsi',
            'iss' => $this->developer_id,
            'kid' => $this->key_id,
            'exp' => time() + 300,
            'iat' => time(),
        ));

        // Encode the header and payload in Base64
        $base64_url_header = $this->base64_url_encode($header);
        $base64_url_payload = $this->base64_url_encode($payload);

        // Hash the signature with the header and payload
        $signature = hash_hmac('sha256', $base64_url_header . "." . $base64_url_payload, $this->base64_url_decode($this->signing_secret), true);

        // Base64 encode the signature hash
        $base64_url_signature = $this->base64_url_encode($signature);

        // Set the JWT using the encoded header, payload, and signature
        $this->jwt = $base64_url_header . "." . $base64_url_payload . "." . $base64_url_signature;

        // Also Return the JWT
        return $this->jwt;
    }

    /**
     * Gets a delivery quote from the Drive API
     */
    public function get_delivery_quote(&$delivery)
    {
        $request_path = "/drive/v2/quotes";
        $request_args = array(
            'method' => 'POST',
            'body' => $delivery->json(),
        );
        $response = $this->request($request_path, $request_args);
        if (wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response));
            $delivery->create_from_array($body);
            WCDD()->log->info(sprintf(__('Retrieved delivery quote %s', 'local-delivery-by-tapsi'), $delivery->get_id()));
            return $response;
        } else if (wp_remote_retrieve_response_code($response) === 409) {
            // Retry as a status update
            $response = $this->get_delivery_status($delivery);
        }
        return $response;
    }

    /**
     * Accepts a delivery quote generated by the Drive API
     */
    public function accept_delivery_quote(&$delivery)
    {
        $request_path = sprintf("/drive/v2/quotes/%s/accept", $delivery->get_id());
        $request_args = array(
            'method' => 'POST',
        );
        $response = $this->request($request_path, $request_args);
        if (wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response));
            $delivery->create_from_array($body);
            WCDD()->log->info(sprintf(__('Accepted delivery quote %s', 'local-delivery-by-tapsi'), $delivery->get_id()));
            return $response;
        }
        return $response;
    }

    /**
     * Creates a delivery
     */
    public function create_delivery(&$delivery)
    {
        $request_path = sprintf("/drive/v2/deliveries");
        $request_args = array(
            'method' => 'POST',
            'body' => $delivery->json(),
        );
        $response = $this->request($request_path, $request_args);
        if (wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response));
            $delivery->create_from_array($body);
            WCDD()->log->info(sprintf(__('Created delivery %s', 'local-delivery-by-tapsi'), $delivery->get_id()));
            return $response;
        }
        return $response;
    }

    /**
     * Gets the status of a delivery
     */
    public function get_delivery_status(&$delivery)
    {
        $request_path = sprintf("/drive/v2/deliveries/%s", $delivery->get_id());
        $request_args = array(
            'method' => 'GET',
        );
        $response = $this->request($request_path, $request_args);
        if (wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response));
            $delivery->create_from_array($body);
            WCDD()->log->info(sprintf(__('Retrieved delivery status for %s', 'local-delivery-by-tapsi'), $delivery->get_id()));
            return $response;
        }
        return $response;
    }

    /**
     * Updates a delivery
     */
    public function update_delivery(&$delivery)
    {
        $request_path = sprintf("/drive/v2/deliveries/%s", $this->external_delivery_id);
        $request_args = array(
            'method' => 'PATCH',
        );
        $response = $this->request($request_path, $request_args);
        if (wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response));
            $delivery->create_from_array($body);
            WCDD()->log->info(sprintf(__('Updated delivery %s', 'local-delivery-by-tapsi'), $delivery->get_id()));
            return $response;
        }
        return $response;
    }

    /**
     * Cancels a delivery
     */
    public function cancel_delivery(&$delivery)
    {
        $request_path = sprintf("/drive/v2/deliveries/%s/cancel", $this->external_delivery_id);
        $request_args = array(
            'method' => 'PUT',
        );
        $response = $this->request($request_path, $request_args);
        if (wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response));
            $delivery->create_from_array($body);
            WCDD()->log->info(sprintf(__('Cancelled delivery %s', 'local-delivery-by-tapsi'), $delivery->get_id()));
            return $response;
        }
        return $response;
    }

    /**
     * Sends a request to the Drive API
     *
     * @param string $request_path The path to direct the request to
     * @param array $request_args An array of arguments for wp_remote_request
     * @return array|WP_Error The response array or a WP_Error on failure
     */
    public function request($request_path, $request_args)
    {
        // Before making a request, make sure we have keys
        if (!$this->get_keys()) {
            WCDD()->log->error(sprintf(__('Error performing request to %s: Missing API configuration', 'local-delivery-by-tapsi'), $request_path));
            return false;
        }

        // Set the URL for the request based on the request path and the API url
        $request_url = $this->api_url . $request_path;

        // Set up default arguments for WP Remote Request
        $defaults = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_jwt(), // Get the auth header
                'Content-Type' => 'application/json',
            )
        );

        // Combine the defaults with the passed arguments
        $request_args = wp_parse_args($request_args, $defaults);

        // Log the request
        WCDD()->log->debug(sprintf(__('Sending request to %s', 'local-delivery-by-tapsi'), $request_path));
        WCDD()->log->debug($request_args);

        // Run the remote request
        $response = wp_remote_request($request_url, $request_args);

        // Log the response
        WCDD()->log->debug($response);

        // Log WP error
        if (is_wp_error($response)) {
            WCDD()->log->error(sprintf(__('Error performing request to %s', 'local-delivery-by-tapsi'), $request_path));
            WCDD()->log->error($response);
            return false;
        }

        // Log HTTP error
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {

            $body = json_decode(wp_remote_retrieve_body($response));

            switch ($response_code) {
                case 400:
                    // The request was syntactically invalid
                    if (isset($body->field_errors[0]->field) && $body->field_errors[0]->field == 'dropoff_phone_number') {
                        wc_add_notice(__('Tapsi: ', 'local-delivery-by-tapsi') . __(' Make sure the phone number is valid and belongs to the same country as the address.', 'local-delivery-by-tapsi'), 'notice');
                    } elseif (isset($body->field_errors[0]->field) && $body->field_errors[0]->field == 'dropoff_address') {
                        wc_add_notice(__('Tapsi: ', 'local-delivery-by-tapsi') . __('Delivery is not available from this pickup location to your selected address. Please enter another dropoff address or select a different delivery method.', 'local-delivery-by-tapsi'), 'notice');
                    } else {
                        wc_add_notice(__('Tapsi: ', 'local-delivery-by-tapsi') . __($body->message, 'local-delivery-by-tapsi'), 'notice');
                    }

                    break;
                case 401:
                    // Authentication error
                    wc_add_notice(__('Tapsi: Authentication Error', 'local-delivery-by-tapsi'), 'notice');
                    break;
                case 403:
                    // Authorization error
                    wc_add_notice(__('Tapsi: Authorization Error', 'local-delivery-by-tapsi'), 'notice');
                    break;
                case 404:
                    // Resource doesn't exist
                    wc_add_notice(__('Tapsi: Resource does not exist', 'local-delivery-by-tapsi'), 'notice');
                    break;
                case 409:
                    // System state doesn't allow operation to proceed
                    // wc_add_notice( __( 'Tapsi: ', 'local-delivery-by-tapsi' ) . __( $body->message, 'local-delivery-by-tapsi' ), 'notice' );
                    break;
                case 422:
                    // Logical validation error
                    wc_add_notice(__('Tapsi: ', 'local-delivery-by-tapsi') . __('Delivery is not available from this pickup location to your selected address. Please enter another dropoff address or select a different delivery method.', 'local-delivery-by-tapsi'), 'notice');
                    // wc_add_notice( wc_print_r( $body->message, true ) );
                    break;
                case 429:
                    // Too many requests
                    wc_add_notice(__('Tapsi: Too many requests', 'local-delivery-by-tapsi'), 'notice');
                    break;
            }

            // Add a notice for server connectivity issues
            if (500 >= $response_code && $response_code > 600) {
                wc_add_notice(__('There was a problem communicating with Tapsi. Please try again later.', 'local-delivery-by-tapsi'), 'notice');
            }

            // Log the error
            WCDD()->log->error(sprintf(__('Error %s performing request to %s', 'local-delivery-by-tapsi'), $response_code, $request_url));
            WCDD()->log->error($body);
        }

        // Return the response object
        return $response;
    }

}
