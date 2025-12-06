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
	 * @param array $rows The rows to update with percentage and ABC classification
	 * @param Phreezer $phreezer The Phreezer instance for querying (optional)
	 */
	public static function CalculateABCClassification(&$rows, $phreezer = null)
	{
		// Calculate grand total from the rows themselves (each row already has their total)
		$grandTotal = 0;
		foreach ($rows as $row) {
			$hours = isset($row->totalHours) ? $row->totalHours : (isset($row->TotalHours) ? $row->TotalHours : 0);
			$grandTotal += (float)$hours;
		}

		// If no data, return early
		if ($grandTotal == 0) {
			foreach ($rows as $row) {
				$row->percentageOfTotal = 0;
				$row->aBCClass = 'C';
			}
			return;
		}

		// Calculate percentage and assign ABC class for each row
		foreach ($rows as $row) {
			// Handle both camelCase (from ToObjectArray) and PascalCase (raw objects)
			$hours = isset($row->totalHours) ? $row->totalHours : (isset($row->TotalHours) ? $row->TotalHours : 0);
			$percentage = ((float)$hours / $grandTotal) * 100;

			// Always set camelCase properties (matching JSON output format)
			$row->percentageOfTotal = round($percentage, 2);

			// Assign ABC class based on percentage thresholds
			if ($percentage >= 70) {
				$row->aBCClass = 'A';
			} elseif ($percentage >= 20) {
				$row->aBCClass = 'B';
			} else {
				$row->aBCClass = 'C';
			}
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
		$sql = "SELECT COALESCE(ROUND(SUM(TIMESTAMPDIFF(MINUTE, te.start, te.end)) / 60.0, 2), 0) as GrandTotal
				FROM time_entries te
				WHERE te.start IS NOT NULL AND te.end IS NOT NULL";

		try {
			$rs = $phreezer->DataAdapter->Execute($sql);
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

