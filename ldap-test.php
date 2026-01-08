<?php
header('Content-Type: application/json');

// Get input parameters
$ldapServer = $_POST['ldapServer'] ?? '';
$baseDN = $_POST['baseDN'] ?? '';
$adminDN = $_POST['adminDN'] ?? '';
$adminPassword = $_POST['adminPassword'] ?? '';
$testUsername = $_POST['testUsername'] ?? '';
$testPassword = $_POST['testPassword'] ?? '';

// Helper function to log (uses Apache error log)
function logEvent($message) {
    error_log("[LDAP Test] " . $message);
}

// Validate inputs
if (empty($ldapServer) || empty($baseDN) || empty($adminDN) || empty($adminPassword) || empty($testUsername) || empty($testPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    logEvent("ERROR: Missing required fields");
    exit;
}

logEvent("Starting LDAP test: ldapServer=$ldapServer, adminDN=$adminDN, testUsername=$testUsername");

try {
    // Connect to LDAP server
    $ldapConn = @ldap_connect($ldapServer);
    
    if (!$ldapConn) {
        throw new Exception("Failed to connect to LDAP server: $ldapServer");
    }
    
    logEvent("Connected to LDAP server: $ldapServer");
    
    // Set LDAP options
    ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
    
    // Bind with admin credentials
    $adminBind = @ldap_bind($ldapConn, $adminDN, $adminPassword);
    
    if (!$adminBind) {
        $error = ldap_error($ldapConn);
        throw new Exception("Admin bind failed. DN: $adminDN. Error: $error");
    }
    
    logEvent("Admin bind successful: $adminDN");
    
    // Search for test user
    $filter = "(uid=$testUsername)";
    $attributes = array('*', '+');
    $search = @ldap_search($ldapConn, $baseDN, $filter, $attributes);
    
    if (!$search) {
        $error = ldap_error($ldapConn);
        throw new Exception("Search failed for user: $testUsername. Error: $error");
    }
    
    logEvent("Search executed for user: $testUsername");
    
    // Get search results
    $entries = ldap_get_entries($ldapConn, $search);
    
    if ($entries['count'] == 0) {
        throw new Exception("User not found: $testUsername");
    }
    
    logEvent("User found: $testUsername");
    
    $userDN = $entries[0]['dn'];
    
    // Try to bind as the test user
    $userBind = @ldap_bind($ldapConn, $userDN, $testPassword);
    
    if (!$userBind) {
        $error = ldap_error($ldapConn);
        throw new Exception("User authentication failed. DN: $userDN. Error: $error");
    }
    
    logEvent("User authentication successful: $testUsername ($userDN)");
    
    // Extract and normalize user attributes
    $entry = $entries[0];
    $userAttributes = array();
    
    // Helper function to safely get attribute
    function getAttr($entry, $key) {
        if (!isset($entry[$key])) {
            return 'N/A';
        }
        
        $value = $entry[$key];
        
        // If it's an array
        if (is_array($value)) {
            // Remove the 'count' key if present
            if (isset($value['count'])) {
                unset($value['count']);
            }
            
            // If it's now empty, return N/A
            if (empty($value)) {
                return 'N/A';
            }
            
            // If it only has one element, return it
            if (count($value) === 1) {
                return reset($value);
            }
            
            // If multiple elements, return as array
            return array_values($value);
        }
        
        // If it's a string, return it
        return $value;
    }
    
    // Get common attributes
    $userAttributes['mail'] = getAttr($entry, 'mail');
    $userAttributes['cn'] = getAttr($entry, 'cn');
    $userAttributes['ou'] = getAttr($entry, 'ou');
    $userAttributes['accountStatus'] = getAttr($entry, 'accountstatus');
    
    // Get objectClass - ensure it's always an array
    $objectClass = getAttr($entry, 'objectclass');
    if (is_string($objectClass)) {
        $objectClass = array($objectClass);
    } elseif (!is_array($objectClass)) {
        $objectClass = array('N/A');
    }
    $userAttributes['objectClass'] = $objectClass;
    
    // Close LDAP connection
    ldap_close($ldapConn);
    
    // Return success response
    $response = array(
        'success' => true,
        'user' => array(
            'username' => $testUsername,
            'dn' => $userDN,
            'attributes' => $userAttributes
        )
    );
    
    echo json_encode($response);
    logEvent("LDAP test completed successfully for user: $testUsername");
    
} catch (Exception $e) {
    http_response_code(400);
    $errorMsg = $e->getMessage();
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    logEvent("ERROR: " . $errorMsg);
}
?>
