<?php
/** @package    PROJECTS::Controller */

/** import supporting libraries */
require_once("AppBaseController.php");
require_once("Model/Customer.php");
require_once("Model/TimeEntry.php");
require_once("util/password.php");
require_once("util/SaldiService.php");

/**
 * CustomerController is the controller class for the Customer object.  The
 * controller is responsible for processing input from the user, reading/updating
 * the model as necessary and displaying the appropriate view.
 */
class CustomerController extends AppBaseController
{

	/**
	 * Override here for any controller-specific functionality
	 *
	 * @inheritdocs
	 */
	protected function Init()
	{
		parent::Init();
	}

	/**
	 * Displays a list view of objects
	 */
	public function ListView()
	{
		$this->RequirePermission(
				self::$ROLE_ADMIN |
				self::$ROLE_MANAGER, 'User.LoginForm');

		$this->Render();
	}

	/**
	 * API Method queries for records and render as JSON
	 */
	public function Query($returnJSON = true)
	{
		$this->RequirePermission(
				self::$ROLE_ADMIN |
				self::$ROLE_MANAGER |
				self::$ROLE_ADVANCED_USER |
				self::$ROLE_CUSTOMER, 'User.LoginForm');

		try
		{

			$criteria = new CustomerCriteria();

			// skip if calling from another controller (reports)
			if ($returnJSON){
				$url_filer = $this->GetRouter()->GetUrlParam('filter');

				// show only active records
				if ($url_filer == 'active')
					$criteria->StatusId_NotEquals = '3';
			}

			// for customers show only them
			if ($this->IsAuthorized(self::$ROLE_CUSTOMER)){
				$criteria->AddFilter(new CriteriaFilter('Id', $this->GetCurrentUser()->CustomerId));
			}

			$filter = RequestUtil::Get('filter');
			if ($filter) $criteria->AddFilter(
				new CriteriaFilter('Id,Name,ContactPerson,Email,Address,Location,Web,Tel,Tel2,Description'
				, '%'.$filter.'%')
			);


			foreach (array_keys($_REQUEST) as $prop)
			{
				$prop_normal = ucfirst($prop);
				$prop_equals = $prop_normal.'_Equals';

				if (property_exists($criteria, $prop_normal))
				{
					$criteria->$prop_normal = RequestUtil::Get($prop);
				}
				elseif (property_exists($criteria, $prop_equals))
				{
					$criteria->$prop_equals = RequestUtil::Get($prop);
				}
			}

			$output = new stdClass();

			// if a sort order was specified then specify in the criteria
			$output->orderBy = RequestUtil::Get('orderBy');
			$output->orderDesc = RequestUtil::Get('orderDesc') != '';

			if ($output->orderBy){
				$criteria->SetOrder($output->orderBy, $output->orderDesc);
			}else{
				$criteria->SetOrder('StatusId', false);
			}

			$page = RequestUtil::Get('page');

			if ($page != '')
			{
				// if page is specified, use this instead (at the expense of one extra count query)
				$pagesize = $this->GetDefaultPageSize();

				$customers = $this->Phreezer->Query('CustomerReporter',$criteria)->GetDataPage($page, $pagesize);
				$output->rows = $customers->ToObjectArray(true,$this->SimpleObjectParams());
				$output->totalResults = $customers->TotalResults;
				$output->totalPages = $customers->TotalPages;
				$output->pageSize = $customers->PageSize;
				$output->currentPage = $customers->CurrentPage;
			}
			else
			{
				// return all results
				$customers = $this->Phreezer->Query('CustomerReporter',$criteria);
				$output->rows = $customers->ToObjectArray(true, $this->SimpleObjectParams());
				$output->totalResults = count($output->rows);
				$output->totalPages = 1;
				$output->pageSize = $output->totalResults;
				$output->currentPage = 1;
			}

			// Calculate ABC classification based on percentage of total hours
			require_once "Reporter/CustomerReporter.php";
			CustomerReporter::CalculateABCClassification($output->rows, $this->Phreezer);

			// mask some data
			if (!$this->IsAuthorized(self::$ROLE_ADMIN | self::$ROLE_MANAGER)){
				foreach ($output->rows as &$row){
					foreach ($row as $key => $field)
						if ($key != 'id' && $key != 'name' && $key != 'totalHours' && $key != 'percentageOfTotal' && $key != 'aBCClass') unset($row->$key);

				}
			}

			if ($returnJSON)
				$this->RenderJSON($output, $this->JSONPCallback());
			else
				return $output->rows;

		}
		catch (Exception $ex)
		{
			if ($returnJSON)
				$this->RenderExceptionJSON($ex);
			else
				echo $ex;
		}
	}

	/**
	 * API Method retrieves a single record and render as JSON
	 */
	public function Read()
	{
		$this->RequirePermission(
				self::$ROLE_ADMIN |
				self::$ROLE_MANAGER, 'User.LoginForm');

		try
		{
			$pk = $this->GetRouter()->GetUrlParam('id');
			$customer = $this->Phreezer->Get('Customer',$pk);
			$this->RenderJSON($customer, $this->JSONPCallback(), true, $this->SimpleObjectParams());
		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method inserts a new record and render response as JSON
	 */
	public function Create()
	{
		$this->RequirePermission(
				self::$ROLE_ADMIN |
				self::$ROLE_MANAGER, 'User.LoginForm');

		try
		{

			$json = json_decode(RequestUtil::GetBody());

			if (!$json)
			{
				throw new Exception('The request body does not contain valid JSON');
			}

			$customer = new Customer($this->Phreezer);

			$customer->Name = $this->SafeGetVal($json, 'name');
			$customer->ContactPerson = $this->SafeGetVal($json, 'contactPerson');
			$customer->AllowLogin = $this->SafeGetVal($json, 'allowLogin');
			$customer->Address = $this->SafeGetVal($json, 'address');
			$customer->Location = $this->SafeGetVal($json, 'location');
			$customer->Web = $this->SafeGetVal($json, 'web');
			$customer->Tel = $this->SafeGetVal($json, 'tel');
			$customer->Tel2 = $this->SafeGetVal($json, 'tel2');
			$customer->StatusId = $this->SafeGetVal($json, 'statusId');
			$customer->Description = $this->SafeGetVal($json, 'description');
			$saldiKundenr = $this->SafeGetVal($json, 'saldiKundenr');
			$customer->SaldiKundenr = ($saldiKundenr === '' || $saldiKundenr === null) ? null : $saldiKundenr;

			$customer->Password = $this->SafeGetVal($json, 'password');
			if ($customer->Password != ''){
				$customer->Password = password_hash($customer->Password , PASSWORD_BCRYPT);
			}

			$customer->Email = $this->SafeGetVal($json, 'email');
			if ($customer->Email == '') $customer->Email = null;

			$customer->Validate();
			$errors = $customer->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$customer->Save();
				$this->RenderJSON($customer, $this->JSONPCallback(), true, $this->SimpleObjectParams());
			}

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method updates an existing record and render response as JSON
	 */
	public function Update()
	{
		$this->RequirePermission(
				self::$ROLE_ADMIN |
				self::$ROLE_MANAGER, 'User.LoginForm');

		try
		{

			$json = json_decode(RequestUtil::GetBody());

			if (!$json)
			{
				throw new Exception('The request body does not contain valid JSON');
			}

			$pk = $this->GetRouter()->GetUrlParam('id');
			$customer = $this->Phreezer->Get('Customer',$pk);

			$customer->Name = $this->SafeGetVal($json, 'name', $customer->Name);
			$customer->ContactPerson = $this->SafeGetVal($json, 'contactPerson', $customer->ContactPerson);
			$customer->AllowLogin = $this->SafeGetVal($json, 'allowLogin', $customer->AllowLogin);
			$customer->Web = $this->SafeGetVal($json, 'web', $customer->Web);
			$customer->Address = $this->SafeGetVal($json, 'address', $customer->Address);
			$customer->Location = $this->SafeGetVal($json, 'location', $customer->Location);
			$customer->Web = $this->SafeGetVal($json, 'web', $customer->Web);
			$customer->Tel = $this->SafeGetVal($json, 'tel', $customer->Tel);
			$customer->Tel2 = $this->SafeGetVal($json, 'tel2', $customer->Tel2);
			$customer->StatusId = $this->SafeGetVal($json, 'statusId', $customer->StatusId);
			$customer->Description = $this->SafeGetVal($json, 'description', $customer->Description);
			$saldiKundenr = $this->SafeGetVal($json, 'saldiKundenr', $customer->SaldiKundenr);
			$customer->SaldiKundenr = ($saldiKundenr === '' || $saldiKundenr === null) ? null : $saldiKundenr;

			$customer->Email = $this->SafeGetVal($json, 'email');
			if ($customer->Email == '') $customer->Email = null;

			$pw_tmp = $this->SafeGetVal($json, 'password', $customer->Password);
			if ($pw_tmp != ''){
				$customer->Password = password_hash($pw_tmp, PASSWORD_BCRYPT);
			}

			$customer->Validate();
			$errors = $customer->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$customer->Save();
				$this->RenderJSON($customer, $this->JSONPCallback(), true, $this->SimpleObjectParams());
			}


		}
		catch (Exception $ex)
		{


			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method deletes an existing record and render response as JSON
	 */
	public function Delete()
	{
		$this->RequirePermission(
				self::$ROLE_ADMIN |
				self::$ROLE_MANAGER, 'User.LoginForm');

		try
		{

			$pk = $this->GetRouter()->GetUrlParam('id');
			$customer = $this->Phreezer->Get('Customer',$pk);

			$customer->Delete();

			$output = new stdClass();

			$this->RenderJSON($output, $this->JSONPCallback());

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method syncs all customers from Saldi API to TimeCase database
	 * Automatically pages through entire Saldi customer database
	 */
	public function SyncFromSaldi()
	{
		$this->RequirePermission(self::$ROLE_ADMIN, 'User.LoginForm');

		// Increase execution time limit for full database sync
		set_time_limit(0); // No time limit for this operation

		try
		{
			$limit = 500;
			$offset = 0;
			$totalStats = array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'errors' => 0,
				'errorMessages' => array(),
				'batchesProcessed' => 0
			);

			error_log("CustomerController::SyncFromSaldi - Starting full sync");

			// Loop until no more customers are returned
			while (true) {
				error_log("CustomerController::SyncFromSaldi - Fetching batch at offset: $offset");

				// Fetch customers from Saldi
				$saldiCustomers = SaldiService::getCustomers($limit, $offset);

				if (!$saldiCustomers || count($saldiCustomers) === 0) {
					error_log("CustomerController::SyncFromSaldi - No more customers to fetch, sync complete");
					break;
				}

				$totalStats['batchesProcessed']++;

				// Process each customer in this batch
				foreach ($saldiCustomers as $saldiCustomer) {
					try {
						// Validate required fields
						if (!isset($saldiCustomer['id']) || !isset($saldiCustomer['kontonr']) || !isset($saldiCustomer['firmanavn'])) {
							error_log("WARNING: Skipping customer with missing required fields");
							$totalStats['skipped']++;
							continue;
						}

						$saldiId = $saldiCustomer['id'];
						$kontonr = $saldiCustomer['kontonr'];

						// Check if customer already exists by saldi_kundenr
						$criteria = new CustomerCriteria();
						$criteria->SaldiKundenr_Equals = $kontonr;
						$existingCustomers = $this->Phreezer->Query('Customer', $criteria);

						if ($existingCustomers->Count() > 0) {
							// Update existing customer only if fields have changed
							$customer = $existingCustomers->Next();
							$hasChanges = false;
							$changes = array();

							$newName = $saldiCustomer['firmanavn'];
							if ($customer->Name !== $newName) {
								$changes[] = "Name: '" . $customer->Name . "' -> '" . $newName . "'";
								$customer->Name = $newName;
								$hasChanges = true;
							}

							if ($customer->ContactPerson !== $newName) {
								$changes[] = "ContactPerson: '" . $customer->ContactPerson . "' -> '" . $newName . "'";
								$customer->ContactPerson = $newName;
								$hasChanges = true;
							}

							$newEmail = (isset($saldiCustomer['email']) && $saldiCustomer['email']) ? $saldiCustomer['email'] : null;
							if ($customer->Email !== $newEmail) {
								$changes[] = "Email: '" . ($customer->Email ?: 'NULL') . "' -> '" . ($newEmail ?: 'NULL') . "'";
								$customer->Email = $newEmail;
								$hasChanges = true;
							}

							$newAddress = isset($saldiCustomer['addr1']) ? $saldiCustomer['addr1'] : $customer->Address;
							if ($customer->Address !== $newAddress) {
								$changes[] = "Address: '" . $customer->Address . "' -> '" . $newAddress . "'";
								$customer->Address = $newAddress;
								$hasChanges = true;
							}

							$newLocation = isset($saldiCustomer['bynavn']) ? $saldiCustomer['bynavn'] : $customer->Location;
							if ($customer->Location !== $newLocation) {
								$changes[] = "Location: '" . $customer->Location . "' -> '" . $newLocation . "'";
								$customer->Location = $newLocation;
								$hasChanges = true;
							}

							$newTel = isset($saldiCustomer['tlf']) ? $saldiCustomer['tlf'] : $customer->Tel;
							if ($customer->Tel !== $newTel) {
								$changes[] = "Tel: '" . $customer->Tel . "' -> '" . $newTel . "'";
								$customer->Tel = $newTel;
								$hasChanges = true;
							}

							$newDescription = 'Saldi ID: ' . $saldiId;
							if ($customer->Description !== $newDescription) {
								$changes[] = "Description: '" . $customer->Description . "' -> '" . $newDescription . "'";
								$customer->Description = $newDescription;
								$hasChanges = true;
							}

							if ($hasChanges) {
								$customer->Save();
								$totalStats['updated']++;
								error_log("Updated customer ID " . $customer->Id . " (Saldi: $saldiId, Kontonr: $kontonr) - Changes: " . implode("; ", $changes));
							} else {
								# error_log("No changes detected for customer ID " . $customer->Id . " (Saldi: $saldiId, Kontonr: $kontonr)");
							}
						} else {
							// Create new customer
							$customer = new Customer($this->Phreezer);
							$customer->Name = $saldiCustomer['firmanavn'];
							$customer->ContactPerson = $saldiCustomer['firmanavn'];
							$customer->Email = (isset($saldiCustomer['email']) && $saldiCustomer['email']) ? $saldiCustomer['email'] : null;
							$customer->Password = '';
							$customer->AllowLogin = 0;
							$customer->Address = isset($saldiCustomer['addr1']) ? $saldiCustomer['addr1'] : '';
							$customer->Location = isset($saldiCustomer['bynavn']) ? $saldiCustomer['bynavn'] : '';
							$customer->Web = isset($saldiCustomer['land']) ? $saldiCustomer['land'] : 'DK';
							$customer->Tel = isset($saldiCustomer['tlf']) ? $saldiCustomer['tlf'] : '';
							$customer->Tel2 = isset($saldiCustomer['addr2']) ? $saldiCustomer['addr2'] : '';
							$customer->StatusId = 2; // Default to active status
							$customer->Description = 'Saldi ID: ' . $saldiId . ', CVR: ' . (isset($saldiCustomer['cvrnr']) ? $saldiCustomer['cvrnr'] : 'N/A');
							$customer->SaldiKundenr = $kontonr;
							$customer->Save();
							$totalStats['created']++;
							error_log("Created new customer (Saldi: $saldiId, Kontonr: $kontonr)");
						}
					} catch (Exception $e) {
						$totalStats['errors']++;
						$errorMsg = "Error processing customer: " . $e->getMessage();
						$totalStats['errorMessages'][] = $errorMsg;
						error_log($errorMsg);
					}
				}

				// Move to next batch
				$offset += $limit;
			}

			error_log("Sync completed - Batches: " . $totalStats['batchesProcessed'] . ", Created: " . $totalStats['created'] . ", Updated: " . $totalStats['updated'] . ", Skipped: " . $totalStats['skipped'] . ", Errors: " . $totalStats['errors']);

			// Prepare output
			$output = new stdClass();
			$output->success = true;
			$output->stats = $totalStats;
			$output->message = "Successfully synced customers from Saldi";

			$this->RenderJSON($output, $this->JSONPCallback());

		}
		catch (Exception $ex)
		{
			error_log("SyncFromSaldi Exception: " . $ex->getMessage());
			$this->RenderExceptionJSON($ex);
		}
	}
}

