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
	 */
	public static function CalculateABCClassification(&$rows)
	{
		// Calculate grand total of all hours
		$grandTotal = 0;
		foreach ($rows as $row) {
			// Handle both camelCase (from ToObjectArray) and PascalCase (raw objects)
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
}

