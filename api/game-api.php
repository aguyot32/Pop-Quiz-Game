<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_errors.log');

function sendJSON($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function handleShutdown() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        sendJSON([
            'success' => false,
            'message' => 'Erreur fatale du serveur',
            'debug' => $error['message'] . ' in ' . $error['file'] . ':' . $error['line']
        ]);
    }
}
register_shutdown_function('handleShutdown');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../controllers/GameController.php';
    require_once __DIR__ . '/../models/SessionManager.php';
    
    $controller = new GameController();
    $sessionManager = new SessionManager();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $_GET['session_id'] ?? null;
    
    $inputData = [];
    if ($method === 'POST') {
        $jsonInput = file_get_contents('php://input');
        
        if (!empty($jsonInput) && trim($jsonInput) !== '') {
            $inputData = json_decode($jsonInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendJSON([
                    'success' => false, 
                    'message' => 'JSON invalide: ' . json_last_error_msg()
                ]);
            }
            
            if (!is_array($inputData)) {
                $inputData = [];
            }
            
            if (isset($inputData['session_id'])) {
                $sessionId = $inputData['session_id'];
            }
        }
    }
    
    $response = ['success' => false, 'message' => 'Action invalide'];
    
    switch ($action) {
        
        case 'start_game':
            if ($method === 'POST') {
                $categoryIds = $inputData['category_ids'] ?? $inputData['category_id'] ?? null;
                $difficulty = $inputData['difficulty'] ?? null;
                $questionCount = $inputData['question_count'] ?? 10;
                
                if ($categoryIds === '' || $categoryIds === 'null') $categoryIds = null;
                if ($difficulty === '' || $difficulty === 'null') $difficulty = null;
                
                $result = $controller->startGame($categoryIds, $difficulty, $questionCount);           
                if ($result['success']) {
                    $newSessionId = $sessionManager->generateSessionId();
                    $sessionManager->saveSession($newSessionId, $result['game_session'], 7200);
                    
                    $response = [
                        'success' => true,
                        'session_id' => $newSessionId,
                        'game_id' => $result['game_session']['game_id']
                    ];
                } else {
                    $response = $result;
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode POST requise'];
            }
            break;
        
        case 'get_question':
            if ($method === 'GET') {
                if (!$sessionId) {
                    $response = ['success' => false, 'message' => 'Session ID manquant'];
                    break;
                }
                
                $gameSession = $sessionManager->getSession($sessionId);
                
                if ($gameSession) {
                    $response = $controller->getCurrentQuestion($gameSession);
                    $sessionManager->extendSession($sessionId);
                } else {
                    $response = ['success' => false, 'message' => 'Session invalide ou expirée'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode GET requise'];
            }
            break;
        
        case 'check_answer':
            if ($method === 'POST') {
                if (!$sessionId) {
                    $response = ['success' => false, 'message' => 'Session ID manquant'];
                    break;
                }
                
                $gameSession = $sessionManager->getSession($sessionId);
                
                if ($gameSession) {
                    $userAnswer = $inputData['answer'] ?? '';
                    $timeElapsed = (float)($inputData['time_elapsed'] ?? 0);
                    
                    $response = $controller->checkAnswer($gameSession, $userAnswer, $timeElapsed);
                    
                    if ($response['success']) {
                        $sessionManager->updateSession($sessionId, $response['game_session'], 7200);
                        unset($response['game_session']);
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Session invalide ou expirée'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode POST requise'];
            }
            break;
        
        case 'skip_question':
            if ($method === 'POST') {
                if (!$sessionId) {
                    $response = ['success' => false, 'message' => 'Session ID manquant'];
                    break;
                }
                
                $gameSession = $sessionManager->getSession($sessionId);
                
                if ($gameSession) {
                    $response = $controller->skipQuestion($gameSession);
                    
                    if ($response['success']) {
                        $sessionManager->updateSession($sessionId, $response['game_session'], 7200);
                        unset($response['game_session']);
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Session invalide ou expirée'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode POST requise'];
            }
            break;
        
        case 'replace_question':
            if ($method === 'POST') {
                if (!$sessionId) {
                    $response = ['success' => false, 'message' => 'Session ID manquant'];
                    break;
                }
                
                $gameSession = $sessionManager->getSession($sessionId);
                
                if ($gameSession && isset($gameSession['questions']) && is_array($gameSession['questions'])) {
                    
                    require_once __DIR__ . '/../models/Database.php';
                    $db = Database::getInstance();
                    
                    $currentIndex = $gameSession['current_question'] ?? 0;
                    
                    if (!isset($gameSession['discarded_questions'])) {
                        $gameSession['discarded_questions'] = [];
                    }
                    
                    $existingIds = $gameSession['discarded_questions'];
                    foreach ($gameSession['questions'] as $q) {
                        if (is_object($q) && isset($q->id)) $existingIds[] = $q->id;
                        elseif (is_array($q) && isset($q['id'])) $existingIds[] = $q['id'];
                        elseif (is_numeric($q)) $existingIds[] = $q;
                    }
                    
                    $brokenQuestion = $gameSession['questions'][$currentIndex] ?? null;
                    $brokenId = 0;
                    if (is_object($brokenQuestion) && isset($brokenQuestion->id)) $brokenId = $brokenQuestion->id;
                    elseif (is_array($brokenQuestion) && isset($brokenQuestion['id'])) $brokenId = $brokenQuestion['id'];
                    elseif (is_numeric($brokenQuestion)) $brokenId = $brokenQuestion;
                    
                    if ($brokenId) {
                        $gameSession['discarded_questions'][] = $brokenId;
                        $existingIds[] = $brokenId;
                    }
                    
                    $existingIds = array_filter(array_unique($existingIds));
                    
                    $catCondition = "";
                    $diffCondition = "";
                    if ($brokenId) {
                        try {
                            $stmtCat = $db->query("SELECT category_id, difficulty FROM questions WHERE id = ?", [$brokenId]);
                            $catData = $stmtCat->fetch();
                            if ($catData) {
                                if (isset($catData['category_id'])) $catCondition = " AND category_id = " . (int)$catData['category_id'];
                                if (isset($catData['difficulty'])) $diffCondition = " AND difficulty = '" . $catData['difficulty'] . "'";
                            }
                        } catch (Exception $e) {}
                    }
                    
                    $placeholders = count($existingIds) > 0 ? implode(',', array_fill(0, count($existingIds), '?')) : '0';
                    $params = count($existingIds) > 0 ? array_values($existingIds) : [];
                    
                    $newQuestion = null;
                    
                    try {
                        $stmt = $db->query("SELECT * FROM questions WHERE id NOT IN ($placeholders) $catCondition $diffCondition ORDER BY RAND() LIMIT 1", $params);
                        $newQuestion = $stmt->fetch();
                    } catch (Exception $e) {}
                    
                    array_splice($gameSession['questions'], $currentIndex, 1);
                    
                    if ($newQuestion) {
                        if (isset($newQuestion['hint_image']) && !isset($newQuestion['hint_image_url'])) {
                            $newQuestion['hint_image_url'] = $newQuestion['hint_image'];
                        }
                        
                        $isStoringIds = isset($gameSession['questions'][0]) && is_numeric($gameSession['questions'][0]);
                        if ($isStoringIds) {
                            $gameSession['questions'][] = $newQuestion['id'];
                        } else {
                            $gameSession['questions'][] = $newQuestion;
                        }
                    }
                    
                    $sessionManager->updateSession($sessionId, $gameSession, 7200);
                    $response = ['success' => true, 'message' => 'Traitement terminé.'];
                    
                } else {
                    $response = ['success' => false, 'message' => 'Session invalide'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode POST requise'];
            }
            break;
        
        case 'finish_game':
            if ($method === 'POST') {
                if (!$sessionId) {
                    $response = ['success' => false, 'message' => 'Session ID manquant'];
                    break;
                }
                
                $gameSession = $sessionManager->getSession($sessionId);
                
                if ($gameSession) {
                    $playerName = $inputData['player_name'] ?? null;
                    $avgTime = (float)($inputData['avg_time'] ?? 0);
                    
                    if (empty($playerName) || trim($playerName) === '') {
                        $playerName = null;
                    }
                    
                    $response = $controller->finishGame($gameSession, $playerName, $avgTime);
                    
                    if ($response['success']) {
                        $sessionManager->deleteSession($sessionId);
                    }
                } else {
                    if (isset($inputData['fallback_results'])) {
                        $response = [
                            'success' => true,
                            'results' => $inputData['fallback_results'],
                            'from_cache' => true
                        ];
                    } else {
                        $response = ['success' => false, 'message' => 'Session invalide ou expirée'];
                    }
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode POST requise'];
            }
            break;
        
        case 'get_categories':
            if ($method === 'GET') {
                $response = $controller->getCategories();
            } else {
                $response = ['success' => false, 'message' => 'Méthode GET requise'];
            }
            break;
        
        case 'get_leaderboard':
            if ($method === 'GET') {
                $categoryId = $_GET['category_id'] ?? null;
                $difficulty = $_GET['difficulty'] ?? null;
                $questions = $_GET['questions'] ?? null;
                $sort = $_GET['sort'] ?? 'score_desc';
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                
                $response = $controller->getLeaderboard($categoryId, $difficulty, $questions, $sort, $limit);
            }
            break;
        
        case 'get_hints':
            if ($method === 'GET') {
                if (!$sessionId) {
                    $response = ['success' => false, 'message' => 'Session ID manquant'];
                    break;
                }
                
                $gameSession = $sessionManager->getSession($sessionId);
                
                if ($gameSession) {
                    $response = $controller->getHints($gameSession);
                    $sessionManager->extendSession($sessionId);
                } else {
                    $response = ['success' => false, 'message' => 'Session invalide ou expirée'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode GET requise'];
            }
            break;
        
        case 'get_game_state':
            if ($method === 'GET') {
                if (!$sessionId) {
                    $response = ['success' => false, 'message' => 'Session ID manquant'];
                    break;
                }
                
                $gameSession = $sessionManager->getSession($sessionId);
                
                if ($gameSession) {
                    $response = [
                        'success' => true,
                        'game_state' => [
                            'game_id' => $gameSession['game_id'],
                            'current_question' => $gameSession['current_question'] + 1,
                            'total_questions' => count($gameSession['questions']),
                            'score' => $gameSession['score'],
                            'correct_answers' => $gameSession['correct_answers']
                        ]
                    ];
                    $sessionManager->extendSession($sessionId);
                } else {
                    $response = ['success' => false, 'message' => 'Session invalide ou expirée'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode GET requise'];
            }
            break;
        
        case 'abandon_game':
            if ($method === 'POST') {
                if ($sessionId && $sessionManager->sessionExists($sessionId)) {
                    $sessionManager->deleteSession($sessionId);
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Partie abandonnée'
                ];
            } else {
                $response = ['success' => false, 'message' => 'Méthode POST requise'];
            }
            break;

        case 'report_question':
            if ($method === 'POST') {
                $questionId = (int)($inputData['question_id'] ?? 0);
                $type = $inputData['type'] ?? 'other';
                $details = $inputData['details'] ?? '';
                
                if ($questionId > 0) {
                    $response = $controller->reportQuestion($questionId, $type, $details);
                } else {
                    $response = ['success' => false, 'message' => 'ID de question invalide'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Méthode POST requise'];
            }
            break;
        
        default:
            $response = [
                'success' => false,
                'message' => 'Action non reconnue: ' . $action
            ];
            break;
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    error_log('Erreur API: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

sendJSON($response);