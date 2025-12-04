<?php
// ===== HELPER FUNCTIONS UNIFICATE =====

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Richieste preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

/**
 * Invia una risposta JSON standardizzata
 */
function sendResponse($status, $data = null, $message = null) {
    http_response_code($status);
    
    $response = [];
    
    if ($status >= 400) {
        $response['error'] = true;
        $response['message'] = $message ?? 'An error occurred';
    } else {
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($message !== null) {
            $response['message'] = $message;
        }
    }
    
    $response['status'] = $status;
    $response['timestamp'] = time();
    
    echo json_encode($response);
    exit;
}

