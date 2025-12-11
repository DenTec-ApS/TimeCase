<?php
error_log("===== LOADING SALDI SERVICE =====");
/**
 * SaldiService handles all Saldi API integration
 * @package Projects::Util
 */

class SaldiService {

	/**
	 * Get Saldi API configuration from GlobalConfig
	 * Reads from _app_config.php (public config) and _machine_config.php (sensitive config)
	 */
	private static function getConfig() {
		return array(
			'api_url' => GlobalConfig::$SALDI_API_URL,
			'db' => GlobalConfig::$SALDI_DB,
			'user' => GlobalConfig::$SALDI_USER,
			'api_key' => GlobalConfig::$SALDI_API_KEY
		);
	}

	/**
	 * Fetch customer data from Saldi by kontonr (customer number)
	 * @param string $kontonr The customer number
	 * @return array|null Customer data from Saldi or null if not found
	 */
	public static function fetchCustomerIdByKontonr($kontonr) {
		$config = self::getConfig();
		$params = array(
			'action' => 'fetch_from_table',
			'db' => $config['db'],
			'saldiuser' => $config['user'],
			'key' => $config['api_key'],
			'select' => 'id,kontonr,firmanavn,cvrnr,addr1,addr2,postnr,bynavn,land,tlf,email,betalingsbet,betalingsdage,gruppe',
			'from' => 'adresser',
			'where' => "kontonr='" . addslashes($kontonr) . "'",
			'limit' => '1'
		);

		error_log("SaldiService::fetchCustomerIdByKontonr - Searching for kontonr: " . $kontonr);
		$response = self::makeRequest($params);
		error_log("SaldiService::fetchCustomerIdByKontonr - Response: " . print_r($response, true));

		if ($response && isset($response[1]) && is_array($response[1])) {
			error_log("SaldiService::fetchCustomerIdByKontonr - Found customer: " . $response[1]['firmanavn']);
			return $response[1];
		}
		error_log("SaldiService::fetchCustomerIdByKontonr - Customer not found in response");
		return null;
	}

	/**
	 * Check if an order exists for a customer on a specific date
	 * @param int $customerId Saldi customer ID
	 * @param string $date Order date (format: Y-m-d)
	 * @return int|null Order ID if exists, null otherwise
	 */
	public static function checkOrderExists($customerId, $date) {
		$config = self::getConfig();
		$params = array(
			'action' => 'fetch_from_table',
			'db' => $config['db'],
			'saldiuser' => $config['user'],
			'key' => $config['api_key'],
			'select' => 'id,ordrenr',
			'from' => 'ordrer',
			'where' => "konto_id=" . intval($customerId) . " AND ordredate='" . addslashes($date) . "' AND art='DO'",
			'limit' => '1'
		);

		error_log("SaldiService::checkOrderExists - Looking for order. CustomerId: $customerId, Date: $date");
		$response = self::makeRequest($params);
		error_log("SaldiService::checkOrderExists - Response: " . print_r($response, true));

		if ($response && isset($response[1]) && is_array($response[1])) {
			error_log("SaldiService::checkOrderExists - Found existing order ID: " . $response[1]['id']);
			return $response[1]['id'];
		}
		error_log("SaldiService::checkOrderExists - No existing order found, will create new one");
		return null;
	}

	/**
	 * Create a new order in Saldi
	 * @param array $orderData Order details
	 * @return int|null Order ID if created successfully, null otherwise
	 */
	public static function createOrder($orderData) {
		$config = self::getConfig();
		$params = array(
			'action' => 'insert_shop_order',
			'db' => $config['db'],
			'saldiuser' => $config['user'],
			'key' => $config['api_key'],
			'shop_ordre_id' =>  $orderData['timeEntryId'] . "0" . date('hms'),
			'shop_addr_id' => $orderData['saldiCustomerId'],
			'saldi_kontonr' => $orderData['kontonr'],
			'firmanavn' => $orderData['firmanavn'],
			'addr1' => $orderData['addr1'],
			'addr2' => $orderData['addr2'],
			'postnr' => $orderData['postnr'],
			'bynavn' => $orderData['bynavn'],
			'land' => $orderData['land'],
			'tlf' => $orderData['tlf'],
			'email' => $orderData['email'],
			'ordredate' => $orderData['ordredate'],
			'gruppe' => isset($orderData['gruppe']) ? $orderData['gruppe'] : 1,
			'cvr' => $orderData['cvrnr'],
			'afd' => '1',
			'nettosum' => '0',
			'momssum' => '0',
			'betalingsbet' => isset($orderData['betalingsbet']) ? $orderData['betalingsbet'] : 'Netto',
			'betalingsdage' => isset($orderData['betalingsdage']) ? $orderData['betalingsdage'] : 30
		);

		error_log("SaldiService::createOrder - Creating new order for customer: " . $orderData['firmanavn'] . ", kontonr: " . $orderData['kontonr']);
		$response = self::makeRequest($params);
		error_log("SaldiService::createOrder - Response: " . print_r($response, true));

		if ($response) {
			$orderId = intval($response);
			if ($orderId > 0) {
				error_log("SaldiService::createOrder - Order created successfully with ID: $orderId");
				return $orderId;
			}
		}
		error_log("SaldiService::createOrder - Failed to create order");
		return null;
	}

	/**
	 * Add an item to an order in Saldi
	 * @param int $orderId Saldi order ID
	 * @param array $lineItem Item details
	 * @return bool True if successful, false otherwise
	 */
	public static function addOrderLine($orderId, $lineItem) {
		$config = self::getConfig();
		$params = array(
			'action' => 'insert_shop_orderline',
			'db' => $config['db'],
			'saldiuser' => $config['user'],
			'key' => $config['api_key'],
			'saldi_ordre_id' => intval($orderId),
			'varenr' => $lineItem['productNumber'],
			'antal' => $lineItem['quantity'],
			'pris' => $lineItem['price'],
			'lager' => '1',
			'rabat' => '0',
			'beskrivelse' => $lineItem['description']
		);

		error_log("SaldiService::addOrderLine - Adding line item to order $orderId. Product: " . $lineItem['productNumber'] . ", Qty: " . $lineItem['quantity'] . ", Price: " . $lineItem['price']);
		$response = self::makeRequest($params);
		error_log("SaldiService::addOrderLine - Response: " . print_r($response, true));

		// insert_shop_orderline doesn't return a value on success
		if ($response !== false) {
			error_log("SaldiService::addOrderLine - Line item added successfully");
			return true;
		}
		error_log("SaldiService::addOrderLine - Failed to add line item");
		return false;
	}

	/**
	 * Make HTTP request to Saldi API
	 * @param array $params Query parameters
	 * @return mixed Decoded JSON response or false on error
	 */
	private static function makeRequest($params) {
		try {
			$config = self::getConfig();
			// Build query string
			$query = http_build_query($params);
			$url = $config['api_url'] . '?' . $query;

			error_log("=== Saldi API Request ===");
			error_log("URL: " . $url);
			error_log("Action: " . $params['action']);

			// Initialize cURL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

			$response = curl_exec($ch);
			$curlError = curl_error($ch);
			$curlErrno = curl_errno($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			error_log("=== Saldi API Response ===");
			error_log("HTTP Code: " . $httpCode);
			error_log("cURL Errno: " . $curlErrno);
			if ($curlError) {
				error_log("cURL Error: " . $curlError);
				return false;
			}

			error_log("Raw Response (first 500 chars): " . substr($response, 0, 500));

			if ($httpCode !== 200) {
				error_log("API returned non-200 status code: " . $httpCode);
				error_log("Full Response: " . $response);
				return false;
			}

			// Try to decode JSON response
			$decoded = json_decode($response, true);
			if ($decoded !== null) {
				error_log("Successfully decoded JSON response");
				error_log("Decoded Response: " . print_r($decoded, true));
				return $decoded;
			}

			// If not valid JSON, return the raw response
			error_log("Response is not valid JSON, returning raw response");
			return $response;
		} catch (Exception $e) {
			error_log("Saldi API Exception: " . $e->getMessage());
			return false;
		}
	}
}
?>
