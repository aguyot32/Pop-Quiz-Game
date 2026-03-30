<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Question.php';

class GameController {
    private $questionModel;
    
    public function __construct() {
        $this->questionModel = new Question();
    }
    
    public function startGame($categoryIds = null, $difficulty = null, $questionCount = 10) {
        $questionCount = max(1, min(100, (int)$questionCount)); 
        
        $questions = $this->questionModel->getRandomQuestions($categoryIds, $difficulty, $questionCount);
        
        if (empty($questions)) {
            return [
                'success' => false,
                'message' => 'Aucune question trouvée avec ces critères'
            ];
        }
        
        $gameSession = [
            'game_id' => uniqid('game_', true),
            'category_ids' => $categoryIds,
            'difficulty' => $difficulty,
            'total_questions' => count($questions),
            'current_question' => 0,
            'score' => 0,
            'correct_answers' => 0,
            'questions' => $questions,
            'start_time' => time(),
            'answers_history' => []
        ];
        
        return [
            'success' => true,
            'game_session' => $gameSession
        ];
    }
    
    public function getCurrentQuestion($gameSession) {
        if (!isset($gameSession['questions']) || !isset($gameSession['current_question'])) {
            return [
                'success' => false,
                'message' => 'Session de jeu invalide'
            ];
        }
        
        $currentIndex = $gameSession['current_question'];
        
        if ($currentIndex >= count($gameSession['questions'])) {
            return [
                'success' => false,
                'message' => 'Toutes les questions ont été répondues',
                'game_finished' => true
            ];
        }
        
        $question = $gameSession['questions'][$currentIndex];
        
        $questionData = [
            'id' => $question['id'],
            'category_name' => $question['category_name'],
            'category_icon' => $question['category_icon'],
            'difficulty' => $question['difficulty'],
            'hint_text' => $question['hint_text'],
            'hint_image_url' => $question['hint_image_url'],
            'answer_length' => mb_strlen($question['answer']),
            'question_number' => $currentIndex + 1,
            'total_questions' => count($gameSession['questions'])
        ];
        
        return [
            'success' => true,
            'question' => $questionData,
            'game_state' => [
                'current_question' => $currentIndex + 1,
                'total_questions' => count($gameSession['questions']),
                'score' => $gameSession['score'],
                'correct_answers' => $gameSession['correct_answers']
            ]
        ];
    }
    
    public function checkAnswer($gameSession, $userAnswer, $timeElapsed) {
        if (!isset($gameSession['questions']) || !isset($gameSession['current_question'])) {
            return [
                'success' => false,
                'message' => 'Session de jeu invalide'
            ];
        }
        
        $currentIndex = $gameSession['current_question'];
        
        if ($currentIndex >= count($gameSession['questions'])) {
            return [
                'success' => false,
                'message' => 'Plus de questions disponibles'
            ];
        }
        
        $question = $gameSession['questions'][$currentIndex];
        $questionId = $question['id'];
        $correctAnswer = $question['answer'];
        
        $isCorrect = $this->questionModel->checkAnswer($questionId, $userAnswer);
        
        $pointsEarned = 0;
        if ($isCorrect) {
            $pointsEarned = $this->questionModel->calculateScore($timeElapsed);
            $gameSession['score'] += $pointsEarned;
            $gameSession['correct_answers']++;
        }
        
        $gameSession['answers_history'][] = [
            'question_id' => $questionId,
            'question' => $correctAnswer,
            'user_answer' => $userAnswer,
            'correct' => $isCorrect,
            'time_elapsed' => $timeElapsed,
            'points_earned' => $pointsEarned,
            'skipped' => false
        ];
        
        $gameSession['current_question']++;
        
        $isGameFinished = $gameSession['current_question'] >= count($gameSession['questions']);
        
        return [
            'success' => true,
            'is_correct' => $isCorrect,
            'correct_answer' => $correctAnswer,
            'points_earned' => $pointsEarned,
            'total_score' => $gameSession['score'],
            'game_finished' => $isGameFinished,
            'game_session' => $gameSession
        ];
    }
    
    public function skipQuestion($gameSession) {
        if (!isset($gameSession['questions']) || !isset($gameSession['current_question'])) {
            return [
                'success' => false,
                'message' => 'Session de jeu invalide'
            ];
        }
        
        $currentIndex = $gameSession['current_question'];
        
        if ($currentIndex >= count($gameSession['questions'])) {
            return [
                'success' => false,
                'message' => 'Plus de questions disponibles'
            ];
        }
        
        $question = $gameSession['questions'][$currentIndex];
        
        $gameSession['answers_history'][] = [
            'question_id' => $question['id'],
            'question' => $question['answer'],
            'user_answer' => null,
            'correct' => false,
            'time_elapsed' => 0,
            'points_earned' => 0,
            'skipped' => true
        ];
        
        $gameSession['current_question']++;
        
        $isGameFinished = $gameSession['current_question'] >= count($gameSession['questions']);
        
        return [
            'success' => true,
            'correct_answer' => $question['answer'],
            'game_finished' => $isGameFinished,
            'game_session' => $gameSession
        ];
    }
    
    public function finishGame($gameSession, $playerName = null, $avgTime = 0) {
        if (!isset($gameSession['score'])) {
            return [
                'success' => false,
                'message' => 'Session de jeu invalide'
            ];
        }
        
        $totalQuestions = count($gameSession['questions']);
        $correctAnswers = $gameSession['correct_answers'];
        $score = $gameSession['score'];
        
        $categoryId = null;
        if (isset($gameSession['category_ids']) && is_array($gameSession['category_ids']) && count($gameSession['category_ids']) === 1) {
            $categoryId = $gameSession['category_ids'][0];
        } elseif (isset($gameSession['category_id'])) {
            $categoryId = $gameSession['category_id'];
        }
        
        $accuracy = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
        $avgTimePerQuestion = 0;
        
        if (!empty($gameSession['answers_history'])) {
            $totalTime = array_sum(array_column($gameSession['answers_history'], 'time_elapsed'));
            $avgTimePerQuestion = $totalTime / count($gameSession['answers_history']);
        }
        
        $scoreSaved = false;
        if (!empty($playerName)) {
            $scoreSaved = $this->questionModel->saveScore(
                $playerName,
                $score,
                $totalQuestions,
                $correctAnswers,
                $categoryId,
                $avgTime > 0 ? $avgTime : $avgTimePerQuestion
            );
            
            if ($scoreSaved && !empty($gameSession['difficulty'])) {
                require_once __DIR__ . '/../models/Database.php';
                $db = Database::getInstance();
                $db->execute("UPDATE scores SET difficulty = ? WHERE player_name = ? ORDER BY id DESC LIMIT 1", [$gameSession['difficulty'], $playerName]);
            }
        }
        
        return [
            'success' => true,
            'results' => [
                'score' => $score,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'wrong_answers' => $totalQuestions - $correctAnswers,
                'accuracy' => $accuracy,
                'avg_time_per_question' => $avgTimePerQuestion,
                'answers_history' => $gameSession['answers_history']
            ],
            'score_saved' => $scoreSaved
        ];
    }
    
    public function getCategories() {
        $categories = $this->questionModel->getAllCategories();
        
        foreach ($categories as &$category) {
            $category['question_count'] = $this->questionModel->countQuestionsByCategory($category['id']);
        }
        
        return [
            'success' => true,
            'categories' => $categories
        ];
    }
    
    public function getLeaderboard($categoryId = null, $difficulty = null, $qCount = null, $sort = 'score_desc', $limit = 50) {
        require_once __DIR__ . '/../models/Database.php';
        $db = Database::getInstance();
        $params = [];
        $conditions = [];

        if (!empty($categoryId)) {
            $conditions[] = "s.category_id = ?";
            $params[] = $categoryId;
        }
        if (!empty($difficulty)) {
            $conditions[] = "s.difficulty = ?";
            $params[] = $difficulty;
        }
        if (!empty($qCount)) {
            $conditions[] = "s.questions_answered = ?";
            $params[] = $qCount;
        }

        $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

        $orderBy = "s.score DESC, s.avg_time ASC";
        switch ($sort) {
            case 'time_asc':
                $orderBy = "s.avg_time ASC, s.score DESC";
                break;
            case 'time_desc':
                $orderBy = "s.avg_time DESC, s.score DESC";
                break;
            case 'accuracy_desc':
                $orderBy = "(s.correct_answers / s.questions_answered) DESC, s.score DESC";
                break;
            case 'accuracy_asc':
                $orderBy = "(s.correct_answers / s.questions_answered) ASC, s.score ASC";
                break;
            case 'date_desc':
                $orderBy = "s.played_at DESC";
                break;
            case 'date_asc':
                $orderBy = "s.played_at ASC";
                break;
            case 'score_asc':
                $orderBy = "s.score ASC, s.avg_time DESC";
                break;
            case 'score_desc':
            default:
                $orderBy = "s.score DESC, s.avg_time ASC";
                break;
        }

        $sql = "SELECT s.*, c.name as category_name, c.icon as category_icon 
                FROM scores s 
                LEFT JOIN categories c ON s.category_id = c.id 
                $where 
                ORDER BY $orderBy 
                LIMIT " . (int)$limit;

        try {
            $stmt = $db->query($sql, $params);
            $scores = $stmt->fetchAll();
            return [
                'success' => true,
                'leaderboard' => $scores
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur BDD: ' . $e->getMessage()
            ];
        }
    }
    
    public function getHints($gameSession) {
        return [
            'success' => false,
            'message' => 'Indices supplémentaires désactivés'
        ];
    }

    public function reportQuestion($questionId, $type, $details) {
        require_once __DIR__ . '/../models/Database.php';
        $db = Database::getInstance();
        
        $sql = "INSERT INTO question_reports (question_id, problem_type, details) VALUES (?, ?, ?)";
        if ($db->execute($sql, [$questionId, $type, $details])) {
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement en base de données.'];
    }
}