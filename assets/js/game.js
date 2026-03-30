let isProcessing = false;

let gameState = {
    currentQuestion: null,
    startTime: null,
    timerInterval: null,
    maxTime: 30,
    sessionId: null,
    localCache: {
        score: 0,
        correctAnswers: 0,
        totalQuestions: 0,
        answersHistory: []
    }
};


async function apiCall(action, method = 'GET', data = null) {
    let url = `api/game-api.php?action=${action}`;
    
    if (method === 'GET' && gameState.sessionId) {
        url += '&session_id=' + encodeURIComponent(gameState.sessionId);
    }
    
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    if (gameState.sessionId) {
        options.headers['X-Session-ID'] = gameState.sessionId;
    }
    
    if (method === 'POST') {
        const bodyData = data || {};
        
        if (gameState.sessionId) {
            bodyData.session_id = gameState.sessionId;
        }
        
        options.body = JSON.stringify(bodyData);
    }
    
    try {
        const response = await fetch(url, options);
        
        if (!response.ok) {
            const text = await response.text();
            return { 
                success: false, 
                message: `Erreur HTTP ${response.status}`,
                debug: text
            };
        }
        
        const text = await response.text();
        
        try {
            return JSON.parse(text);
        } catch (parseError) {
            return { 
                success: false, 
                message: 'Réponse serveur invalide',
                debug: text.substring(0, 500)
            };
        }
    } catch (error) {
        return { 
            success: false, 
            message: 'Erreur de connexion: ' + error.message 
        };
    }
}

async function startNewGame(categoryIds, difficulty, questionCount) {
    const data = {
        category_ids: categoryIds,
        difficulty: difficulty,
        question_count: questionCount
    };
    
    const result = await apiCall('start_game', 'POST', data);
    
    if (result.success) {
        gameState.sessionId = result.session_id;
        sessionStorage.setItem('gameSessionId', result.session_id);
        return true;
    } else {
        alert('Erreur: ' + result.message);
        return false;
    }
}
async function getCurrentQuestion() {
    const result = await apiCall('get_question', 'GET');
    
    if (result.success) {
        gameState.currentQuestion = result.question;
        updateGameState(result.game_state);
        return result.question;
    } else if (result.game_finished) {
        return null;
    } else {
        console.error('Erreur:', result.message);
        return null;
    }
}

async function checkAnswer(answer, exactTime) {
    const data = {
        answer: answer,
        time_elapsed: exactTime
    };
    
    const result = await apiCall('check_answer', 'POST', data);
    return result;
}

async function skipQuestion() {
    const result = await apiCall('skip_question', 'POST', {});
    return result;
}

async function finishGame(playerName = null) {
    const totalTime = gameState.localCache.answersHistory.reduce((sum, item) => sum + item.time_elapsed, 0);

    const rawAvg = gameState.localCache.answersHistory.length > 0 
        ? (totalTime / gameState.localCache.answersHistory.length) 
        : 0;
    const avgTime = parseFloat(rawAvg.toFixed(3));
    
    const accuracy = gameState.localCache.totalQuestions > 0 
        ? Math.round((gameState.localCache.correctAnswers / gameState.localCache.totalQuestions) * 100) 
        : 0;
    
    const fallbackResults = {
        score: gameState.localCache.score,
        total_questions: gameState.localCache.totalQuestions,
        correct_answers: gameState.localCache.correctAnswers,
        wrong_answers: gameState.localCache.totalQuestions - gameState.localCache.correctAnswers,
        accuracy: accuracy,
        avg_time_per_question: avgTime,
        answers_history: gameState.localCache.answersHistory
    };
    
    const data = { 
        player_name: playerName,
        fallback_results: fallbackResults
    };
    
    const result = await apiCall('finish_game', 'POST', data);
    
    if (result.success) {
        sessionStorage.removeItem('gameSessionId');
        gameState.sessionId = null;
    }
    
    return result;
}

async function abandonGame() {
    const result = await apiCall('abandon_game', 'POST');
    sessionStorage.removeItem('gameSessionId');
    gameState.sessionId = null;
    return result;
}

function showScreen(screenId) {
    document.querySelectorAll('.game-screen').forEach(screen => {
        screen.classList.remove('active');
    });
    
    const targetScreen = document.getElementById(screenId);
    if (targetScreen) {
        targetScreen.classList.add('active');
    }

    const reportBtn = document.getElementById('reportButton');
    if (reportBtn) {
        if (screenId === 'gameOverScreen' || screenId === 'loadingScreen') {
            reportBtn.style.display = 'none';
        } else {
            reportBtn.style.display = 'block';
        }
    }
}
function updateGameState(state) {
    document.getElementById('questionNumber').textContent = 
        `${state.current_question}/${state.total_questions}`;
    document.getElementById('currentScore').textContent = state.score;
    document.getElementById('correctAnswers').textContent = state.correct_answers;
    
    gameState.localCache.score = state.score;
    gameState.localCache.correctAnswers = state.correct_answers;
    gameState.localCache.totalQuestions = state.total_questions;
    
    console.log('État du jeu mis à jour:', {
        question: `${state.current_question}/${state.total_questions}`,
        score: state.score,
        correctAnswers: state.correct_answers
    });
}

function displayQuestion(question) {
    const difficultyBadge = document.getElementById('difficultyBadge');
    difficultyBadge.textContent = question.difficulty === 'easy' ? 'Facile' : 
                                   question.difficulty === 'medium' ? 'Moyen' : 'Difficile';
    difficultyBadge.className = 'difficulty-badge ' + question.difficulty;
    
    document.getElementById('hintText').textContent = question.hint_text;

    const answerInput = document.getElementById('answerInput');
    const submitBtn = document.getElementById('submitAnswerBtn');
    const skipBtn = document.getElementById('skipBtn');
    
    answerInput.value = '';
    answerInput.disabled = true;
    submitBtn.disabled = true;
    skipBtn.disabled = true;
    isProcessing = true; 

    const loadingMsg = document.querySelector('#loadingScreen p');
    if (loadingMsg) loadingMsg.textContent = "Chargement de l'image...";
    showScreen('loadingScreen');

    const imgElement = document.getElementById('hintImage');
    const targetUrl = question.hint_image_url;
    
    let imageHandled = false; 

    const handleBrokenImage = async () => {
        if (imageHandled) return; 
        imageHandled = true;
        
        if (typeof window.brokenImageCount === 'undefined') window.brokenImageCount = 0;
        window.brokenImageCount++;

        if (window.brokenImageCount > 3) {
            console.warn("Trop d'images cassées à la suite ! Arrêt d'urgence.");
            window.brokenImageCount = 0;
            
            const skipResult = await skipQuestion(); 
            if (skipResult && skipResult.success) {
                gameState.localCache.answersHistory.push({
                    question: question.hint_text || 'Question',
                    user_answer: '[ Images introuvables ]',
                    correct: false, time_elapsed: 0, points_earned: 0, skipped: true
                });
                showResult(false, skipResult.correct_answer, 0, 0, skipResult.game_finished);
                document.getElementById('resultIcon').textContent = '⚠️';
                document.getElementById('resultIcon').className = 'result-icon skipped';
                document.getElementById('resultTitle').textContent = 'Images indisponibles !';
            }
            return;
        }
        
        console.warn("Lien de l'image cassé. Remplacement silencieux de la question...");
        
        if (loadingMsg) loadingMsg.textContent = "Image indisponible, recherche d'une autre question...";
        showScreen('loadingScreen');
        
        const result = await apiCall('replace_question', 'POST'); 
        
        if (result && result.success) {
            const newQuestion = await getCurrentQuestion();
            if (newQuestion) {
                displayQuestion(newQuestion);
            } else {
                await endGame();
            }
        } else {
            const skipResult = await skipQuestion(); 
            if (skipResult && skipResult.success) {
                gameState.localCache.answersHistory.push({
                    question: question.hint_text || 'Question',
                    user_answer: '[ Image introuvable ]',
                    correct: false, time_elapsed: 0, points_earned: 0, skipped: true
                });
                showResult(false, skipResult.correct_answer, 0, 0, skipResult.game_finished);
                document.getElementById('resultIcon').textContent = '⚠️';
                document.getElementById('resultIcon').className = 'result-icon skipped';
                document.getElementById('resultTitle').textContent = 'Image indisponible !';
            }
        }
    };

    let fallbackTimer = setTimeout(() => {
        handleBrokenImage();
    }, 4000);

    const img = new Image();

    img.onload = function() {
        if (imageHandled) return; 
        imageHandled = true;
        clearTimeout(fallbackTimer);
        
        window.brokenImageCount = 0;
        
        imgElement.src = targetUrl;
        showScreen('questionScreen');
        
        answerInput.disabled = false;
        submitBtn.disabled = false;
        skipBtn.disabled = false;
        isProcessing = false;
        
        setTimeout(() => answerInput.focus(), 100);
        window.exactStartTime = performance.now();
        startTimer();
    };

    img.onerror = function() {
        clearTimeout(fallbackTimer);
        handleBrokenImage();
    };

    if (targetUrl && targetUrl.trim() !== '') {
        img.src = targetUrl;
    } else {
        img.onerror(); 
    }
}

function startTimer() {
    gameState.startTime = Date.now();
    let timeLeft = gameState.maxTime;
    
    const timerProgress = document.getElementById('timerProgress');
    const timerText = document.getElementById('timerText');
    
    timerProgress.style.width = '100%';
    timerText.textContent = timeLeft + 's';
    
    clearInterval(gameState.timerInterval);
    
    gameState.timerInterval = setInterval(() => {
        timeLeft--;
        const percentage = (timeLeft / gameState.maxTime) * 100;
        
        timerProgress.style.width = percentage + '%';
        timerText.textContent = timeLeft + 's';
        
        if (timeLeft <= 0) {
            clearInterval(gameState.timerInterval);
            handleTimeout();
        }
    }, 1000);
}

async function handleTimeout() {
    clearInterval(gameState.timerInterval);
    
    const result = await skipQuestion();
    
    if (result && result.success) {
        gameState.localCache.answersHistory.push({
            question: gameState.currentQuestion.hint_text || 'Question',
            user_answer: null,
            correct: false,
            time_elapsed: gameState.maxTime,
            points_earned: 0,
            skipped: false,
            timeout: true
        });
        
        showResult(false, result.correct_answer, 0, gameState.maxTime, result.game_finished);
    } else {
        alert('Erreur lors du timeout');
    }
}

function showResult(isCorrect, correctAnswer, points, timeUsed, gameFinished, userAnswer = "") {
    clearInterval(gameState.timerInterval);
    
    const resultIcon = document.getElementById('resultIcon');
    const resultTitle = document.getElementById('resultTitle');
    
    if (isCorrect) {
        resultIcon.textContent = '✓';
        resultIcon.className = 'result-icon correct';
        resultTitle.textContent = 'Bonne réponse !';
    } else {
        if (timeUsed >= gameState.maxTime) {
            resultIcon.textContent = '⏱';
            resultIcon.className = 'result-icon skipped';
            resultTitle.textContent = 'Temps écoulé !';
        } 
        else if (timeUsed === 0 && points === 0) {
            resultIcon.textContent = '⏭';
            resultIcon.className = 'result-icon skipped';
            resultTitle.textContent = 'Question passée';
        }
        else {
            resultIcon.textContent = '✗';
            resultIcon.className = 'result-icon wrong';
            resultTitle.textContent = 'Mauvaise réponse...';
        }
    }
    
    document.getElementById('correctAnswerText').textContent = correctAnswer;

    const userAnswerContainer = document.getElementById('userAnswerContainer');
    const userAnswerText = document.getElementById('userAnswerText');

    if (!isCorrect && userAnswer && userAnswer !== "") {
        userAnswerText.textContent = userAnswer;
        userAnswerContainer.style.display = 'block';
    } else {
        userAnswerContainer.style.display = 'none';
    }

    document.getElementById('pointsEarned').textContent = points > 0 ? '+' + points : '0';
    document.getElementById('timeUsed').textContent = parseFloat(timeUsed).toFixed(3) + 's';
    
    const nextBtn = document.getElementById('nextQuestionBtn');
    nextBtn.disabled = false;

    if (gameFinished) {
        nextBtn.textContent = 'Voir les résultats';
    } else {
        nextBtn.textContent = 'Question suivante';
    }
    
    showScreen('resultScreen');
    
    setTimeout(() => {
        nextBtn.focus();
    }, 100);
}

function displayGameOver(results) {
    document.getElementById('finalScore').textContent = results.score + ' pts';
    document.getElementById('finalCorrect').textContent = 
        `${results.correct_answers}/${results.total_questions}`;
    document.getElementById('finalAccuracy').textContent = results.accuracy + '%';
    let exactTotal = 0;
    let exactCount = 0;
    results.answers_history.forEach(item => {
        exactTotal += parseFloat(item.time_elapsed || 0);
        exactCount++;
    });
    const exactAvg = exactCount > 0 ? (exactTotal / exactCount) : 0;
    
    document.getElementById('finalAvgTime').textContent = exactAvg.toFixed(3) + 's';
    
    const historyList = document.getElementById('answersHistoryList');
    historyList.innerHTML = '';
    
    results.answers_history.forEach((item, index) => {
        const historyItem = document.createElement('div');
        historyItem.className = 'history-item ' + (item.correct ? 'correct' : 
                                                   item.skipped ? 'skipped' : 'wrong');
        
        const exactTimeDisplay = parseFloat(item.time_elapsed).toFixed(3);
        
        historyItem.innerHTML = `
            <div class="history-question">
                <div class="history-question-text">
                    ${index + 1}. ${item.question}
                </div>
                <div class="history-user-answer">
                    ${item.user_answer ? 'Votre réponse : ' + item.user_answer : 
                      item.skipped ? 'Question passée' : 'Temps écoulé'}
                </div>
            </div>
            <div class="history-stats">
                <span class="history-time">${exactTimeDisplay}s</span>
                <span class="history-points">${item.points_earned > 0 ? '+' : ''}${item.points_earned}</span>
            </div>
        `;
        
        historyList.appendChild(historyItem);
    });
    
    showScreen('gameOverScreen');
    
    setTimeout(() => {
        document.getElementById('playAgainBtn').focus();
    }, 100);
}

async function submitAnswer() {

    if (isProcessing) return;

    const answerInput = document.getElementById('answerInput');
    const answer = answerInput.value.trim();
    
    if (!answer) {
        alert('Veuillez entrer une réponse');
        return;
    }

    isProcessing = true;
    answerInput.disabled = true;
    const submitBtn = document.getElementById('submitAnswerBtn');
    submitBtn.disabled = true;

    const rawTime = (performance.now() - window.exactStartTime) / 1000;
    const timeElapsed = parseFloat(rawTime.toFixed(3));

    answerInput.value = '';
    const result = await checkAnswer(answer, timeElapsed);

    submitBtn.disabled = false;
    
    if (result.success) {
        console.log('Réponse soumise:', {
            answer: answer,
            isCorrect: result.is_correct,
            correctAnswer: result.correct_answer,
            points: result.points_earned,
            timeElapsed: timeElapsed,
            gameFinished: result.game_finished
        });
        
        gameState.localCache.answersHistory.push({
            question: gameState.currentQuestion.hint_text || 'Question',
            user_answer: answer,
            correct: result.is_correct,
            time_elapsed: timeElapsed,
            points_earned: result.points_earned,
            skipped: false
        });
        
        if (result.is_correct) {
            gameState.localCache.correctAnswers++;
            gameState.localCache.score += result.points_earned;
            
            animateStatUpdate('currentScore', gameState.localCache.score);
            animateStatUpdate('correctAnswers', gameState.localCache.correctAnswers);
        }
        
        showResult(
            result.is_correct,
            result.correct_answer,
            result.points_earned,
            timeElapsed,
            result.game_finished,
            answer
        );
    } else {
        console.error('Erreur lors de la soumission:', result);
        alert('Erreur: ' + result.message);
    }
}

function animateStatUpdate(elementId, newValue) {
    const element = document.getElementById(elementId);
    
    element.classList.add('updated');
    
    element.textContent = newValue;
    
    setTimeout(() => {
        element.classList.remove('updated');
    }, 500);
}

async function nextQuestion() {
    const nextBtn = document.getElementById('nextQuestionBtn');
    if (nextBtn.disabled) return;
    nextBtn.disabled = true;

    showScreen('loadingScreen');
    
    const question = await getCurrentQuestion();
    
    if (question) {
        displayQuestion(question);
    } else {
        await endGame();
    }
}

async function endGame() {
    showScreen('loadingScreen');
    
    const playerName = localStorage.getItem('playerName');
    const nameToSave = (playerName && playerName.trim() !== '') ? playerName.trim() : null;
    
    const result = await finishGame(nameToSave);
    
    if (result && result.success && result.results) {
        if (result.from_cache) {
            console.log('⚠️ Résultats affichés depuis le cache local (session expirée)');
        }
        displayGameOver(result.results);
    } else {
        console.error('Erreur sauvegarde:', result);
        alert('Erreur lors de la fin de partie: ' + (result.message || 'Inconnue'));
        window.location.href = 'index.php';
    }
}

async function handleSkip() {
    if (!confirm('Voulez-vous vraiment passer cette question ?')) {
        return;
    }
    
    clearInterval(gameState.timerInterval);
    
    const result = await skipQuestion();
    
    if (result && result.success) {
        gameState.localCache.answersHistory.push({
            question: gameState.currentQuestion.hint_text || 'Question',
            user_answer: null,
            correct: false,
            time_elapsed: 0,
            points_earned: 0,
            skipped: true
        });
        
        showResult(false, result.correct_answer, 0, 0, result.game_finished);
    } else {
        alert('Erreur lors du passage de la question');
    }
}

async function handleAbandon() {
    if (!confirm('Voulez-vous vraiment abandonner la partie ?')) {
        return;
    }
    
    await abandonGame();
    window.location.href = 'index.php';
}

function openReportModal() {
    if (!gameState.currentQuestion || !gameState.currentQuestion.id) {
        alert("Impossible de signaler : aucune question en cours.");
        return;
    }
    document.getElementById('reportModal').style.display = 'flex';
}

function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
    document.getElementById('reportDetails').value = '';
}

async function submitReport() {
    const questionId = gameState.currentQuestion.id;
    const type = document.getElementById('reportType').value;
    const details = document.getElementById('reportDetails').value.trim();

    try {
        const response = await fetch('api/game-api.php?action=report_question', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question_id: questionId,
                type: type,
                details: details
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Merci ! Ton signalement a été envoyé.');
            closeReportModal();
        } else {
            alert('Erreur : ' + data.message);
        }
    } catch (error) {
        console.error('Erreur lors du signalement:', error);
        alert('Une erreur de connexion est survenue.');
    }
}

if (window.location.pathname.includes('game.php')) {
    document.addEventListener('DOMContentLoaded', async function() {
        const gameSettings = JSON.parse(sessionStorage.getItem('gameSettings') || '{}');
        
        if (!gameSettings.questionCount) {
            alert('Aucune partie en cours');
            window.location.href = 'index.php';
            return;
        }
        
        const savedSessionId = sessionStorage.getItem('gameSessionId');
        if (savedSessionId) {
            gameState.sessionId = savedSessionId;
        }
        
        const success = await startNewGame(
            gameSettings.categoryIds,
            gameSettings.difficulty,
            gameSettings.questionCount
        );
        
        sessionStorage.setItem('lastGameSettings', JSON.stringify(gameSettings));
        
        if (success) {
            const question = await getCurrentQuestion();
            if (question) {
                displayQuestion(question);
            }
        } else {
            window.location.href = 'index.php';
        }
        
        document.getElementById('submitAnswerBtn').addEventListener('click', submitAnswer);
        
        document.getElementById('answerInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                
                if (!isProcessing && !this.disabled) {
                    document.getElementById('submitAnswerBtn').click();
                }
            }
        });
        
        document.getElementById('skipBtn').addEventListener('click', handleSkip);
        document.getElementById('nextQuestionBtn').addEventListener('click', nextQuestion);
        document.getElementById('abandonBtn').addEventListener('click', handleAbandon);
        
        document.getElementById('playAgainBtn').addEventListener('click', function() {
            const lastSettings = JSON.parse(sessionStorage.getItem('lastGameSettings') || '{}');
            
            if (lastSettings.questionCount) {
                sessionStorage.setItem('gameSettings', JSON.stringify(lastSettings));
                window.location.href = 'game.php';
            } else {
                sessionStorage.removeItem('gameSettings');
                window.location.href = 'index.php';
            }
        });
        
        document.getElementById('backToMenuBtn').addEventListener('click', function() {
            sessionStorage.removeItem('gameSettings');
            sessionStorage.removeItem('lastGameSettings');
            sessionStorage.removeItem('gameSessionId');
            window.location.href = 'index.php';
        });
        
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const resultScreen = document.getElementById('resultScreen');
                const gameOverScreen = document.getElementById('gameOverScreen');
                
                if (resultScreen.classList.contains('active')) {
                    const nextBtn = document.getElementById('nextQuestionBtn');
                    if (!nextBtn.disabled) {
                        nextQuestion();
                    }
                } else if (gameOverScreen.classList.contains('active')) {
                    document.getElementById('playAgainBtn').click();
                }
            }
        });
    });
}
