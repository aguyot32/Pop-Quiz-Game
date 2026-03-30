<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ImageManager.php';

class Question {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAllCategories() {
        $sql = "SELECT * FROM categories ORDER BY name";
        $stmt = $this->db->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    public function getCategoryById($categoryId) {
        $sql = "SELECT * FROM categories WHERE id = ?";
        $stmt = $this->db->query($sql, [$categoryId]);
        return $stmt ? $stmt->fetch() : null;
    }
    
    public function getRandomQuestions($categoryIds = null, $difficulty = null, $limit = 10) {
        $sql = "SELECT q.*, c.name as category_name, c.icon as category_icon 
                FROM questions q 
                JOIN categories c ON q.category_id = c.id 
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($categoryIds)) {
            if (!is_array($categoryIds)) {
                $categoryIds = [$categoryIds];
            }
            
            $categoryIds = array_map('intval', $categoryIds);
            
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            
            $sql .= " AND q.category_id IN ($placeholders)";
            
            $params = array_merge($params, $categoryIds);
        }
        
        if ($difficulty !== null && $difficulty !== '' && $difficulty !== 'all') {
            $sql .= " AND q.difficulty = ?";
            $params[] = $difficulty;
        }
        
        $limit = (int)$limit;
        $sql .= " ORDER BY RAND() LIMIT " . (int)$limit;
        
        $stmt = $this->db->query($sql, $params);
        $questions = $stmt ? $stmt->fetchAll() : [];
        
        foreach ($questions as &$question) {
            $question['hint_image_url'] = ImageManager::getImageOrPlaceholder(
                $question['hint_image'], 
                $question['answer']
            );
        }
        
        return $questions;
    }
    
    public function getQuestionById($id) {
        $sql = "SELECT q.*, c.name as category_name, c.icon as category_icon 
                FROM questions q 
                JOIN categories c ON q.category_id = c.id 
                WHERE q.id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        $question = $stmt ? $stmt->fetch() : null;
        
        if ($question) {
            $question['hint_image_url'] = ImageManager::getImageOrPlaceholder(
                $question['hint_image'], 
                $question['answer']
            );
        }
        
        return $question;
    }
    
    public function countQuestionsByCategory($categoryId = null) {
        if ($categoryId !== null) {
            $sql = "SELECT COUNT(*) as count FROM questions WHERE category_id = ?";
            $stmt = $this->db->query($sql, [$categoryId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM questions";
            $stmt = $this->db->query($sql);
        }
        
        $result = $stmt ? $stmt->fetch() : null;
        return $result ? $result['count'] : 0;
    }
    
    public function addQuestion($categoryId, $answer, $difficulty, $hintText, $hintImage = null) {
        $sql = "INSERT INTO questions (category_id, answer, difficulty, hint_text, hint_image) 
                VALUES (?, ?, ?, ?, ?)";
        
        $success = $this->db->execute($sql, [
            $categoryId,
            $answer,
            $difficulty,
            $hintText,
            $hintImage
        ]);
        
        if ($success) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    public function updateQuestion($id, $categoryId, $answer, $difficulty, $hintText, $hintImage = null) {
        $sql = "UPDATE questions 
                SET category_id = ?, answer = ?, difficulty = ?, hint_text = ?, hint_image = ? 
                WHERE id = ?";
        
        return $this->db->execute($sql, [
            $categoryId,
            $answer,
            $difficulty,
            $hintText,
            $hintImage,
            $id
        ]);
    }
    
    public function deleteQuestion($id) {
        $question = $this->getQuestionById($id);
        
        if ($question && !empty($question['hint_image'])) {
            ImageManager::deleteImage($question['hint_image']);
        }
        
        $sql = "DELETE FROM questions WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    public function checkAnswer($questionId, $userAnswer) {
        $question = $this->getQuestionById($questionId);
        
        if (!$question) {
            return false;
        }
        
        $userAnswer = $this->normalizeString($userAnswer);
        
        $correctAnswer = $this->normalizeString($question['answer']);
        
        if ($correctAnswer === $userAnswer) {
            return true;
        }
        
        if (!empty($question['answer_alternatives'])) {
            $alternatives = explode('|', $question['answer_alternatives']);
            foreach ($alternatives as $alternative) {
                $normalizedAlt = $this->normalizeString($alternative);
                if ($normalizedAlt === $userAnswer) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function normalizeString($str) {
        $str = mb_strtolower($str, 'UTF-8');
        
        $str = transliterator_transliterate('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC;', $str);
        
        $str = preg_replace('/\s+/', '', $str);
        
        $str = preg_replace('/[^a-z0-9]/', '', $str);
        
        return $str;
    }
    
    public function calculateScore($timeElapsed) {
        if ($timeElapsed <= 5) {
            return POINTS_FAST_ANSWER;
        } elseif ($timeElapsed <= 15) {
            return POINTS_MEDIUM_ANSWER;
        } else {
            return POINTS_SLOW_ANSWER;
        }
    }
    
    public function saveScore($playerName, $score, $questionsAnswered, $correctAnswers, $categoryId = null, $avgTime = 0) {
        $sql = "INSERT INTO scores (player_name, score, questions_answered, correct_answers, category_id, avg_time) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        return $this->db->execute($sql, [
            $playerName,
            $score,
            $questionsAnswered,
            $correctAnswers,
            $categoryId,
            $avgTime
        ]);
    }

    public function getTopScores($limit = 10, $categoryId = null) {
        $sql = "SELECT s.*, c.name as category_name 
                FROM scores s 
                LEFT JOIN categories c ON s.category_id = c.id 
                WHERE 1=1";
        
        $params = [];
        
        if ($categoryId !== null && $categoryId !== '') {
            $sql .= " AND s.category_id = ?";
            $params[] = $categoryId;
        }
        
        $limit = (int)$limit;
        $sql .= " ORDER BY s.score DESC, s.played_at DESC LIMIT $limit";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }
}