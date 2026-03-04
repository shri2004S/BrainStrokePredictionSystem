<?php
// File: api_response_handler.php

class APIResponse {
    public static function success($data = [], $message = '') {
        return json_encode([
            'success' => true,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function error($message, $code = 400) {
        http_response_code($code);
        return json_encode([
            'success' => false,
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
?>