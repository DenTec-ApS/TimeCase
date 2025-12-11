<?php
/**
 * @package PROJECTS
 *
 * MACHINE-SPECIFIC CONFIGURATION SETTINGS
 *
 * This file contains machine-specific settings that should NOT be added to version control.
 * Copy this file to _machine_config.php and fill in your actual values.
 *
 * IMPORTANT: _machine_config.php should be in your .gitignore
 *
 * Settings here override defaults from _app_config.php
 */

/**
 * SALDI API CONFIGURATION (SENSITIVE - DO NOT COMMIT)
 * The API key should NEVER be committed to version control
 * Get your API key from your Saldi account
 */
GlobalConfig::$SALDI_API_KEY = 'SALDI_API_KEY';

/**
 * DATABASE CONNECTION (if not using _global_config.php)
 * Uncomment and configure if needed:
 */
// GlobalConfig::$CONNECTION_SETTING = 'mysql://username:password@localhost/database_name';
