<?php
/** @package    verysimple::Phreeze */

/** import supporting libraries */
require_once("verysimple/HTTP/RequestUtil.php");
require_once("verysimple/Util/ExceptionThrower.php");

/**
 * Dispatcher direct a web request to the correct controller & method
 *
 * @package    verysimple::Phreeze
 * @author     VerySimple Inc.
 * @copyright  1997-2007 VerySimple, Inc.
 * @license    http://www.gnu.org/licenses/lgpl.html  LGPL
 * @version    2.4
 */
class Dispatcher
{
	/**
	 * Set to true and Phreeze will not try to handle deprecated function warnings
	 * @var boolean default = true
	 */
	static $IGNORE_DEPRECATED = true;

	/**
	 * Processes user input and executes the specified controller method, ensuring
	 * that the controller dependencies are all injected properly
	 *
	 * @param Phreezer $phreezer Object persistance engine
	 * @param IRenderEngine $renderEngine rendering engine
	 * @param string (optional) $action the user requested action (if not provided will use router->GetRoute())
	 * @param Context (optional) a context object for persisting state
	 * @param IRouter (optional) router object for reading/writing URLs (if not provided, GenericRouter will be used)
	 */
	static function Dispatch($phreezer,$renderEngine,$action='',$context=null,$router=null)
	{
		if ($router == null)
		{
			require_once('GenericRouter.php');
			$router = new GenericRouter();
		}

		list($controller_param,$method_param) = $router->GetRoute( $action );

		// Log the matched route and method
		error_log("====== DISPATCHER ROUTE MATCH ======");
		error_log("Controller: " . $controller_param);
		error_log("Method: " . $method_param);
		error_log("Full Route: " . $controller_param . "." . $method_param);

		// normalize the input
		$controller_class = $controller_param."Controller";
		$controller_file = "Controller/" . $controller_param . "Controller.php";
		$actual_controller_file = $controller_file; // Track which file path we'll actually use

		error_log("DISPATCHER: Looking for controller file: " . $controller_file);

		// look for the file in the expected places, halt if not found
		if (file_exists($controller_file)) {
			$actual_controller_file = $controller_file;
			error_log("DISPATCHER: Found controller in current directory");
		} else if (file_exists("libs/".$controller_file)) {
			$actual_controller_file = "libs/".$controller_file;
			error_log("DISPATCHER: Found controller in libs/ directory");
		} else {
			error_log("DISPATCHER: File not found in primary paths, searching include path");
			// go to plan b, search the include path for the controller
			$paths = explode(PATH_SEPARATOR,get_include_path());
			$found = false;
			foreach ($paths as $path)
			{
				if (file_exists($path ."/".$controller_file))
				{
					error_log("DISPATCHER: Found controller in include path: " . $path ."/".$controller_file);
					$actual_controller_file = $path ."/".$controller_file;
					$found = true;
					break;
				}
			}

			if (!$found) throw new Exception("File ~/libs/".$controller_file." was not found in include path");
		}
		error_log("DISPATCHER: Controller file exists at: " . $actual_controller_file);

		// convert any php errors into an exception
		if (self::$IGNORE_DEPRECATED)
		{
			ExceptionThrower::Start();
		}
		else
		{
			ExceptionThrower::Start(E_ALL);
			ExceptionThrower::$IGNORE_DEPRECATED = false;
		}

		// we should be fairly certain the file exists at this point
		error_log("DISPATCHER: About to include controller file");
		error_log("DISPATCHER: Current working directory: " . getcwd());
		error_log("DISPATCHER: Full path to try: " . getcwd() . "/" . $actual_controller_file);
		error_log("DISPATCHER: File readable: " . (is_readable($actual_controller_file) ? "yes" : "no"));

		try {
			include_once($actual_controller_file);
			error_log("DISPATCHER: Controller file included successfully");
		} catch (Throwable $ex) {
			error_log("DISPATCHER: Exception/Error during include: " . $ex->getMessage());
			error_log("DISPATCHER: Exception trace: " . $ex->getTraceAsString());
			throw $ex;
		}

		// we found the file but the expected class doesn't appear to be defined
		if (!class_exists($controller_class))
		{
			error_log("Controller file was found, but class '".$controller_class."' is not defined");
			throw new Exception("Controller file was found, but class '".$controller_class."' is not defined");
		}
		error_log("DISPATCHER: Controller class exists");

		// create an instance of the controller class
		error_log("DISPATCHER: About to instantiate controller: " . $controller_class);
		$controller = new $controller_class($phreezer,$renderEngine,$context,$router);
		error_log("DISPATCHER: Controller instantiated");

		// we have a valid instance, just verify there is a matching method
		if (!is_callable(array($controller, $method_param)))
		{
			throw new Exception("'".$controller_class.".".$method_param."' is not a valid action");
		}
		error_log("DISPATCHER: Method is callable");

		// file, class and method all are ok, go ahead and call it
		error_log("====== ABOUT TO CALL CONTROLLER METHOD ======");
		error_log("Calling: " . $controller_class . "->" . $method_param . "()");
		try {
			call_user_func(array(&$controller, $method_param));
		} catch (Exception $ex) {
			error_log("====== EXCEPTION IN CONTROLLER METHOD ======");
			error_log("Exception: " . $ex->getMessage());
			error_log("Stack Trace: " . $ex->getTraceAsString());
			throw $ex;
		}

		// reset error handling back to whatever it was
		//restore_exception_handler();
		ExceptionThrower::Stop();

		return true;
	}

	/**
	 * Fired by the PHP error handler function.  Calling this function will
	 * always throw an exception unless error_reporting == 0.  If the
	 * PHP command is called with @ preceeding it, then it will be ignored
	 * here as well.
	 *
	 * @deprecated use ExceptionThrower::HandleError instead
	 * @param string $code
	 * @param string $string
	 * @param string $file
	 * @param string $line
	 * @param string $context
	 */
	static function HandleException($code, $string, $file, $line, $context)
	{
		ExceptionThrower::HandleError($code, $string, $file, $line, $context);
	}
}
