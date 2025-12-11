<?php
error_log("====== DEBUG ROUTE TEST ======");
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("QUERY_STRING: " . $_SERVER['QUERY_STRING']);
error_log("PATH_INFO: " . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : 'Not Set'));

// Parse the URI to understand what's happening
$uri = $_SERVER['REQUEST_URI'];
error_log("Full URI: " . $uri);

// Remove the query string if present
if (strpos($uri, '?') !== false) {
    $uri = substr($uri, 0, strpos($uri, '?'));
}

error_log("URI without query: " . $uri);

// Try to extract the route
$parts = explode('/', trim($uri, '/'));
error_log("URI parts: " . print_r($parts, true));

echo json_encode(array(
    'debug' => 'Check the Apache error log for details',
    'uri' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD']
));
?>
