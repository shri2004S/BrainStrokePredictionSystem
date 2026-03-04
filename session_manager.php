<?php
// File: session_manager.php

class SessionManager {
    public static function initializePatientSession($user_id, $user_data) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = 'patient';
        $_SESSION['full_name'] = $user_data['full_name'];
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['logged_in_at'] = time();
    }
    
    public static function initializeDoctorSession($user_id, $user_data) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = 'doctor';
        $_SESSION['full_name'] = $user_data['full_name'];
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['specialization'] = $user_data['specialization'];
        $_SESSION['logged_in_at'] = time();
    }
    
    public static function isPatient() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['role']) && 
               $_SESSION['role'] === 'patient';
    }
    
    public static function isDoctor() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['role']) && 
               $_SESSION['role'] === 'doctor';
    }
    
    public static function destroy() {
        session_unset();
        session_destroy();
    }


    
}
?>