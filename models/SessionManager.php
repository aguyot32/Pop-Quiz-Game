<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

class SessionManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->cleanOldSessions();
    }
    
    public function generateSessionId() {
        return bin2hex(random_bytes(32));
    }
    
    public function saveSession($sessionId, $gameData, $expiresInSeconds = 3600) {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresInSeconds);
        $gameDataJson = json_encode($gameData);
        
        $sql = "REPLACE INTO game_sessions 
                (session_id, game_data, updated_at, expires_at) 
                VALUES (?, ?, NOW(), ?)";
        
        return $this->db->execute($sql, [$sessionId, $gameDataJson, $expiresAt]);
    }
    
    public function getSession($sessionId) {
        $sql = "SELECT game_data, expires_at FROM game_sessions 
                WHERE session_id = ? AND expires_at > NOW()";
        
        $stmt = $this->db->query($sql, [$sessionId]);
        $result = $stmt ? $stmt->fetch() : null;
        
        if ($result) {
            return json_decode($result['game_data'], true);
        }
        
        return null;
    }
    
    public function updateSession($sessionId, $gameData, $expiresInSeconds = 3600) {
        return $this->saveSession($sessionId, $gameData, $expiresInSeconds);
    }
    
    public function deleteSession($sessionId) {
        $sql = "DELETE FROM game_sessions WHERE session_id = ?";
        return $this->db->execute($sql, [$sessionId]);
    }
    
    public function sessionExists($sessionId) {
        $sql = "SELECT COUNT(*) as count FROM game_sessions 
                WHERE session_id = ? AND expires_at > NOW()";
        
        $stmt = $this->db->query($sql, [$sessionId]);
        $result = $stmt ? $stmt->fetch() : null;
        
        return $result && $result['count'] > 0;
    }
    
    private function cleanOldSessions() {
        if (rand(1, 100) > 1) return;
        
        $sql = "DELETE FROM game_sessions WHERE expires_at < NOW()";
        $this->db->execute($sql);
    }
    
    public function extendSession($sessionId, $expiresInSeconds = 3600) {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresInSeconds);
        
        $sql = "UPDATE game_sessions 
                SET expires_at = ?, updated_at = NOW() 
                WHERE session_id = ?";
        
        return $this->db->execute($sql, [$expiresAt, $sessionId]);
    }
}