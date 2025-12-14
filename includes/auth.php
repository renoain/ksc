<?php
require_once 'database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function login($username, $password) {
        $this->db->query('SELECT * FROM user WHERE username = :username OR email = :email');
        $this->db->bind(':username', $username);
        $this->db->bind(':email', $username);
        
        $row = $this->db->single();
        
        if($row) {
            // Untuk demo, password disimpan plain text. Di production gunakan password_hash()
            if($password === $row->password) {
                session_start();
                $_SESSION['user_id'] = $row->id_user;
                $_SESSION['username'] = $row->username;
                $_SESSION['nama'] = $row->nama;
                $_SESSION['role'] = $row->role;
                $_SESSION['email'] = $row->email;
                $_SESSION['logged_in'] = true;
                
                return true;
            }
        }
        return false;
    }
    
    public function register($data) {
        $this->db->query('INSERT INTO user (nama, username, email, no_hp, password, role) 
                         VALUES (:nama, :username, :email, :no_hp, :password, :role)');
        
        $this->db->bind(':nama', $data['nama']);
        $this->db->bind(':username', $data['username']);
        $this->db->bind(':email', $data['email']);
        $this->db->bind(':no_hp', $data['no_hp']);
        $this->db->bind(':password', $data['password']); // Plain text untuk demo
        $this->db->bind(':role', 'penyewa');
        
        if($this->db->execute()) {
            return true;
        }
        return false;
    }
    
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public function logout() {
        session_start();
        session_unset();
        session_destroy();
        $_SESSION = array();
    }
    
    public function getUserById($id) {
        $this->db->query('SELECT * FROM user WHERE id_user = :id');
        $this->db->bind(':id', $id);
        return $this->db->single();
    }
    
    public function checkUsernameExists($username) {
        $this->db->query('SELECT id_user FROM user WHERE username = :username');
        $this->db->bind(':username', $username);
        $row = $this->db->single();
        return $row ? true : false;
    }
    
    public function checkEmailExists($email) {
        $this->db->query('SELECT id_user FROM user WHERE email = :email');
        $this->db->bind(':email', $email);
        $row = $this->db->single();
        return $row ? true : false;
    }
}
?>