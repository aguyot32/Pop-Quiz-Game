<?php

require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pop Quiz Game - En jeu</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body class="game-page">
    <div class="game-layout">
        <aside class="game-sidebar">
            <div class="sidebar-content">
                <div class="stat-card">
                    <span class="stat-label">Question</span>
                    <span class="stat-value-large" id="questionNumber">-/-</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-label">Score</span>
                    <span class="stat-value-large" id="currentScore">0</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-label">Bonnes réponses</span>
                    <span class="stat-value-large" id="correctAnswers">0</span>
                </div>
                
                <button id="abandonBtn" class="btn btn-danger btn-abandon">
                    Abandonner
                </button>
            </div>
        </aside>

        <main class="game-main" id="gameMain" style="position: relative;">
            
            <button id="reportButton" onclick="openReportModal()" title="Signaler un problème avec cette question" style="position: absolute; top: 75px; right: 20px; font-size: 0.85rem; padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-muted); cursor: pointer; border-radius: 8px; transition: all 0.2s; z-index: 100;" onmouseover="this.style.color='var(--danger-color)'; this.style.borderColor='var(--danger-color)'; this.style.background='rgba(239, 68, 68, 0.1)';" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.background='rgba(255,255,255,0.05)';">
                🚩 Signaler
            </button>

            <div id="loadingScreen" class="game-screen active">
                <div class="loading-content">
                    <div class="spinner"></div>
                    <h2>Préparation de la partie...</h2>
                    <p>Chargement des questions</p>
                </div>
            </div>

            <div id="questionScreen" class="game-screen">
                
                <div class="game-header-row">
                    <span class="timer-text" id="timerText">30s</span>
                </div>
                
                <div class="timer-bar">
                    <div class="timer-progress" id="timerProgress"></div>
                </div>

                <div class="hint-image-container">
                    <div class="hint-image-wrapper">
                        <span class="difficulty-badge" id="difficultyBadge">Difficulté</span>
                        <img id="hintImage" src="" alt="Indice visuel" class="hint-image">
                    </div>
                </div>

                <div class="question-text-container">
                    <p id="hintText" class="question-text">De quel jeu vient cette image ?</p>
                </div>

                <div class="answer-input-container">
                    <input 
                        type="text" 
                        id="answerInput" 
                        class="answer-input" 
                        placeholder="Tapez votre réponse..."
                        autocomplete="off"
                        autofocus
                    >
                </div>

                <div class="action-buttons">
                    <button id="submitAnswerBtn" class="btn-game btn-game-primary">
                        Valider
                    </button>
                    <button id="skipBtn" class="btn-game btn-game-secondary">
                        Passer
                    </button>
                </div>
            </div>

            <div id="resultScreen" class="game-screen">
                <div class="result-content">
                    <div class="result-icon" id="resultIcon">✓</div>
                    <h2 class="result-title" id="resultTitle">Bonne réponse !</h2>
                    <div class="result-answer">
                        <div id="userAnswerContainer" style="display: none; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed rgba(255,255,255,0.2);">
                            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 5px;">Ta réponse :</p>
                            <h4 id="userAnswerText" style="color: var(--danger-color); margin: 0; font-size: 1.2rem;">---</h4>
                        </div>
                        
                        <p>La bonne réponse était :</p>
                        <h3 id="correctAnswerText">---</h3>
                    </div>
                    <div class="result-stats">
                        <div class="result-stat">
                            <span class="result-stat-label">Points gagnés</span>
                            <span class="result-stat-value" id="pointsEarned">+0</span>
                        </div>
                        <div class="result-stat">
                            <span class="result-stat-label">Temps</span>
                            <span class="result-stat-value" id="timeUsed">0s</span>
                        </div>
                    </div>
                    <button id="nextQuestionBtn" class="btn btn-primary btn-large">
                        Question suivante
                    </button>
                </div>
            </div>

            <div id="gameOverScreen" class="game-screen">
                <div class="game-over-content">
                    <h2>🎉 Partie terminée !</h2>
                    
                    <div class="final-stats">
                        <div class="final-stat-card">
                            <div class="final-stat-icon">🏆</div>
                            <div class="final-stat-info">
                                <span class="final-stat-label">Score final</span>
                                <span class="final-stat-value" id="finalScore">0</span>
                            </div>
                        </div>
                        
                        <div class="final-stat-card">
                            <div class="final-stat-icon">✅</div>
                            <div class="final-stat-info">
                                <span class="final-stat-label">Bonnes réponses</span>
                                <span class="final-stat-value" id="finalCorrect">0/0</span>
                            </div>
                        </div>
                        
                        <div class="final-stat-card">
                            <div class="final-stat-icon">🎯</div>
                            <div class="final-stat-info">
                                <span class="final-stat-label">Précision</span>
                                <span class="final-stat-value" id="finalAccuracy">0%</span>
                            </div>
                        </div>
                        
                        <div class="final-stat-card">
                            <div class="final-stat-icon">⏱️</div>
                            <div class="final-stat-info">
                                <span class="final-stat-label">Temps moyen</span>
                                <span class="final-stat-value" id="finalAvgTime">0s</span>
                            </div>
                        </div>
                    </div>

                    <div class="answers-history">
                        <h3>Historique des réponses</h3>
                        <div id="answersHistoryList"></div>
                    </div>

                    <div class="game-over-actions">
                        <button id="playAgainBtn" class="btn btn-primary btn-large">
                            Rejouer
                        </button>
                        <button id="backToMenuBtn" class="btn btn-secondary">
                            Retour au menu
                        </button>
                    </div>
                </div>
            </div>

            <div id="reportModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); align-items: center; justify-content: center;">
                <div class="modal-content" style="background: var(--bg-card); padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; position: relative;">
                    <span class="modal-close" onclick="closeReportModal()" style="position: absolute; top: 15px; right: 20px; font-size: 2rem; cursor: pointer; color: var(--text-muted);">&times;</span>
                    <h2 style="margin-bottom: 10px; color: var(--danger-color);">🚩 Signaler un problème</h2>
                    <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">Aidez-nous à améliorer le jeu en signalant une erreur sur cette question.</p>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: bold;">Quel est le problème ?</label>
                        <select id="reportType" style="width: 100%; padding: 12px; background: var(--bg-lighter); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white;">
                            <option value="answer">La réponse attendue est fausse</option>
                            <option value="abbreviation">Il manque une abréviation</option>
                            <option value="image">L'image ne s'affiche pas ou est mal coupée</option>
                            <option value="category">Mauvaise catégorie</option>
                            <option value="difficulty">La difficulté est mal évaluée</option>
                            <option value="question">Le texte de la question comporte une erreur</option>
                            <option value="other">Autre problème...</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: bold;">Détails (Optionnel) :</label>
                        <textarea id="reportDetails" rows="3" placeholder="Précisez le problème pour m'aider à le corriger..." style="width: 100%; padding: 12px; background: var(--bg-lighter); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; resize: vertical;"></textarea>
                    </div>

                    <button onclick="submitReport()" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; border-radius: 8px; background: var(--primary-color); color: white; border: none; cursor: pointer;">Envoyer le signalement</button>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/game.js?v=<?= time() ?>"></script>
</body>
</html>