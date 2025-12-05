<?php
/** @package    Projects::Model */

/** import supporting libraries */
require_once("DAO/CustomerCriteriaDAO.php");

/**
 * The CustomerCriteria class extends CustomerDAOCriteria and is used
 * to query the database for objects and collections
 * 
 * @inheritdocs
 * @package Projects::Model
 * @author ClassBuilder
 * @version 1.0
 */
class CustomerCriteria extends CustomerCriteriaDAO
{
	/**
	 * Override SetOrder to allow sorting by computed fields like TotalHours
	 */
	public function SetOrder($property, $desc = false)
	{
		if (!$property) {
			return;
		}

		// Special handling for TotalHours computed field
		if (strtolower($property) == 'totalhours') {
			$this->_where_delim = $this->_order ? "," : "";
			$this->_order .= $this->_where_delim . 'SUM(TIMESTAMPDIFF(MINUTE, te.start, te.end)) / 60.0' . ($desc ? " desc" : "");
			return;
		}

		// For all other properties, use the parent implementation
		parent::SetOrder($property, $desc);
	}

	/**
	 * Override OnPrepare to handle any additional custom logic if needed
	 */
	public function OnPrepare()
	{
		parent::OnPrepare();
	}

}
