<?php
/** @package    Projects::Model::DAO */

/** import supporting libraries */
require_once("verysimple/Phreeze/Reporter.php");

/**
 * This is an example Reporter based on the Customer object.  The reporter object
 * allows you to run arbitrary queries that return data which may or may not fith within
 * the data access API.  This can include aggregate data or subsets of data.
 *
 * Note that Reporters are read-only and cannot be used for saving data.
 *
 * @package Projects::Model::DAO
 * @author ClassBuilder
 * @version 1.0
 */
class CustomerReporter extends Reporter
{

	// the properties in this class must match the columns returned by GetCustomQuery().
	// 'CustomFieldExample' is an example that is not part of the `customers` table
	public $StatusDescription;
	public $TotalHours;
	public $PercentageOfTotal;
	public $ABCClass;

	public $Id;
	public $Name;
	public $ContactPerson;
	public $Email;
	public $Address;
	public $Location;
	public $Url;
	public $Tel;
	public $Tel2;
	public $StatusId;
	public $Description;
	public $SaldiKundenr;

	/*
	* GetCustomQuery returns a fully formed SQL statement.  The result columns
	* must match with the properties of this reporter object.
	*
	* @param Criteria $criteria
	* @return string SQL statement
	*/
	static function GetCustomQuery($criteria)
	{
		$sql = "select
			statuses.description as StatusDescription
			,`customers`.`id` as Id
			,`customers`.`name` as Name
			,`customers`.`contact_person` as ContactPerson
			,`customers`.`email` as Email
			,`customers`.`address` as Address
			,`customers`.`location` as Location
			,`customers`.`web` as Web
			,`customers`.`tel` as Tel
			,`customers`.`tel2` as Tel2
			,`customers`.`status_id` as StatusId
			,`customers`.`description` as Description
			,`customers`.`saldi_kundenr` as SaldiKundenr
			,COALESCE(ROUND(SUM(TIMESTAMPDIFF(MINUTE, te.start, te.end)) / 60.0, 2), 0) as TotalHours
			,0 as PercentageOfTotal
			,'C' as ABCClass
		from `customers`
		inner join statuses on statuses.id = customers.status_id
		left join time_entries te on te.customer_id = customers.id";

		// the criteria can be used or you can write your own custom logic.
		// be sure to escape any user input with $criteria->Escape()
		$sql .= $criteria->GetWhere();

		// Add GROUP BY clause
		$sql .= " group by customers.id";

		// Add the ORDER BY clause
		$sql .= $criteria->GetOrder();

		return $sql;
	}

	/**
	 * Post-process the results to calculate percentage of total and ABC class
	 * This is called after the query results are retrieved
	 *
	 * @param array $rows The rows to update with percentage and ABC classification (paginated)
	 * @param Phreezer $phreezer The Phreezer instance for querying (optional)
	 */
	public static function CalculateABCClassification(&$rows, $phreezer = null)
	{
		// Get ABC classifications for all customers (not paginated)
		$abcMap = self::GetABCClassificationMap($phreezer);

		// Apply the classifications to the paginated rows
		foreach ($rows as $row) {
			$customerId = isset($row->id) ? $row->id : $row->Id;

			if (isset($abcMap[$customerId])) {
				$row->percentageOfTotal = $abcMap[$customerId]['percentage'];
				$row->aBCClass = $abcMap[$customerId]['class'];
			} else {
				// Fallback if customer not found
				$row->percentageOfTotal = 0;
				$row->aBCClass = 'C';
			}
		}
	}

	/**
	 * Get ABC classification map for all customers
	 * Calculates classification based on cumulative percentage across entire dataset
	 *
	 * @param Phreezer $phreezer The Phreezer instance for querying
	 * @return array Map of customer ID => ['percentage' => float, 'class' => string]
	 */
	public static function GetABCClassificationMap($phreezer = null)
	{
		if ($phreezer === null) {
			global $Phreezer;
			$phreezer = $Phreezer;
		}

		if ($phreezer === null) {
			return [];
		}

		// Get grand total
		$grandTotal = self::GetGrandTotalHours($phreezer);

		if ($grandTotal == 0) {
			return [];
		}

		// Query all customers with their hours (no pagination)
		$sql = "SELECT
			`customers`.`id` as Id,
			COALESCE(ROUND(SUM(TIMESTAMPDIFF(MINUTE, te.start, te.end)) / 60.0, 2), 0) as TotalHours
		FROM `customers`
		LEFT JOIN time_entries te ON te.customer_id = customers.id
		GROUP BY customers.id
		ORDER BY TotalHours DESC";

		try {
			$rs = $phreezer->DataAdapter->Select($sql);
			$allCustomers = [];

			while ($row = $phreezer->DataAdapter->Fetch($rs)) {
				$customerId = $row['Id'];
				$hours = (float)$row['TotalHours'];
				$percentage = ($hours / $grandTotal) * 100;

				$allCustomers[$customerId] = [
					'hours' => $hours,
					'percentage' => round($percentage, 2)
				];
			}
			$phreezer->DataAdapter->Release($rs);

			// Calculate cumulative percentage and assign ABC classes
			$abcMap = [];
			$cumulativePercentage = 0;

			foreach ($allCustomers as $customerId => $data) {
				// Determine class based on cumulative BEFORE adding this customer
				if ($cumulativePercentage < 80) {
					$class = 'A';
				} elseif ($cumulativePercentage < 95) {
					$class = 'B';
				} else {
					$class = 'C';
				}

				$abcMap[$customerId] = [
					'percentage' => $data['percentage'],
					'class' => $class
				];

				// Add to cumulative after assigning class
				$cumulativePercentage += $data['percentage'];
			}

			return $abcMap;
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Get the grand total of hours across all customers
	 *
	 * @param Phreezer $phreezer The Phreezer instance
	 * @return float The total hours across all customers
	 */
	public static function GetGrandTotalHours($phreezer = null)
	{
		// If no phreezer provided, try to use global or static reference
		if ($phreezer === null) {
			global $Phreezer;
			$phreezer = $Phreezer;
		}

		if ($phreezer === null) {
			return 0;
		}

		// Query to get sum of all time entries
		$sql = "SELECT COALESCE(ROUND(SUM(TIMESTAMPDIFF(MINUTE, te.start, te.end)) / 60.0, 2), 0) as GrandTotal FROM time_entries te WHERE te.start IS NOT NULL AND te.end IS NOT NULL";

		try {
			$rs = $phreezer->DataAdapter->Select($sql);
			$row = $phreezer->DataAdapter->Fetch($rs);
			$phreezer->DataAdapter->Release($rs);

			if ($row) {
				// Try different possible column names (case-insensitive keys)
				if (isset($row['GrandTotal'])) {
					return (float)$row['GrandTotal'];
				}
				if (isset($row['grandtotal'])) {
					return (float)$row['grandtotal'];
				}
				// Get first value if keys are different
				$values = array_values($row);
				if (!empty($values)) {
					return (float)$values[0];
				}
			}
			return 0;
		} catch (Exception $e) {
			return 0;
		}
	}
}

