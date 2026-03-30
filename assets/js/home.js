document.addEventListener('DOMContentLoaded', function() {
            
    const allCheckbox = document.getElementById('cat-all');
    const otherCheckboxes = document.querySelectorAll('.category-item');
    const selectionInfo = document.getElementById('cat-selection-info');

    if (allCheckbox) {
        allCheckbox.addEventListener('change', function() {
            if(this.checked) {
                otherCheckboxes.forEach(cb => cb.checked = false);
                selectionInfo.textContent = "Toutes les catégories sélectionnées";
                selectionInfo.style.color = "var(--success-color)";
            } else {
                selectionInfo.textContent = "Sélectionnez au moins une catégorie";
                selectionInfo.style.color = "var(--warning-color)";
            }
        });
    }

    otherCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if(this.checked) {
                allCheckbox.checked = false;
            }
            
            const checkedCount = document.querySelectorAll('.category-item:checked').length;
            
            if(checkedCount === 0) {
                allCheckbox.checked = true;
                selectionInfo.textContent = "Toutes les catégories sélectionnées";
                selectionInfo.style.color = "var(--success-color)";
            } else if(checkedCount === 1) {
                selectionInfo.textContent = `1 catégorie sélectionnée`;
                selectionInfo.style.color = "var(--primary-color)";
            } else {
                selectionInfo.textContent = `${checkedCount} catégories sélectionnées`;
                selectionInfo.style.color = "var(--primary-color)";
            }
        });
    });

    const startGameBtn = document.getElementById('startGameBtn');
    if (startGameBtn) {
        startGameBtn.addEventListener('click', function() {
            let selectedCategoryIds = null;

            if (!allCheckbox.checked) {
                selectedCategoryIds = [];
                document.querySelectorAll('.category-item:checked').forEach(cb => {
                    selectedCategoryIds.push(cb.value);
                });
                
                if (selectedCategoryIds.length === 0) {
                    selectedCategoryIds = null; 
                }
            }

            const difficulty = document.getElementById('difficulty').value || null;
            const questionCount = parseInt(document.getElementById('questionCount').value);
            const playerName = document.getElementById('playerName').value.trim();

            if (playerName) {
                localStorage.setItem('playerName', playerName);
            }

            startGame(selectedCategoryIds, difficulty, questionCount);
        });
    }

    const savedName = localStorage.getItem('playerName');
    const playerNameInput = document.getElementById('playerName');
    if (savedName && playerNameInput) {
        playerNameInput.value = savedName;
    }

    const leaderboardBtn = document.getElementById('leaderboardBtn');
    const modalClose = document.querySelector('.modal-close');
    const modal = document.getElementById('leaderboardModal');

    if (leaderboardBtn) leaderboardBtn.addEventListener('click', showLeaderboard);
    
    if (modalClose) {
        modalClose.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    }
    
    const filtres = ['leaderboardCategory', 'leaderboardDifficulty', 'leaderboardQuestions'];
    filtres.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => {
                currentLeaderboardSort = 'score_desc';
                loadLeaderboard();
            });
        }
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.style.display = 'none';
    });
});

function startGame(categoryIds, difficulty, questionCount) {
    sessionStorage.setItem('gameSettings', JSON.stringify({
        categoryIds: categoryIds, 
        difficulty: difficulty,
        questionCount: questionCount
    }));
    window.location.href = 'game.php';
}

function showLeaderboard() {
    document.getElementById('leaderboardModal').style.display = 'flex';
    loadLeaderboard();
}

let currentLeaderboardSort = 'score_desc';

window.setLeaderboardSort = function(type) {
    if (type === 'score') currentLeaderboardSort = currentLeaderboardSort === 'score_desc' ? 'score_asc' : 'score_desc';
    else if (type === 'time') currentLeaderboardSort = currentLeaderboardSort === 'time_asc' ? 'time_desc' : 'time_asc';
    else if (type === 'accuracy') currentLeaderboardSort = currentLeaderboardSort === 'accuracy_desc' ? 'accuracy_asc' : 'accuracy_desc';
    else if (type === 'date') currentLeaderboardSort = currentLeaderboardSort === 'date_desc' ? 'date_asc' : 'date_desc';
    loadLeaderboard();
};

function getSortIcon(type) {
    if (currentLeaderboardSort === type + '_desc') return '▼';
    if (currentLeaderboardSort === type + '_asc') return '▲';
    return '<span style="opacity: 0.3; font-size: 0.8em;">↕</span>';
}

async function loadLeaderboard() {
    const content = document.getElementById('leaderboardContent');
    
    if (!content.innerHTML.includes('table')) {
        content.innerHTML = '<p class="loading">Chargement...</p>';
    } else {
        content.style.opacity = '0.5';
        content.style.pointerEvents = 'none';
        content.style.transition = 'opacity 0.2s ease';
    }

    const categoryId = document.getElementById('leaderboardCategory')?.value;
    const difficulty = document.getElementById('leaderboardDifficulty')?.value;
    const qCount = document.getElementById('leaderboardQuestions')?.value;
    const sort = currentLeaderboardSort;

    try {
        let url = 'api/game-api.php?action=get_leaderboard&limit=50';
        if (categoryId) url += '&category_id=' + categoryId;
        if (difficulty) url += '&difficulty=' + difficulty;
        if (qCount) url += '&questions=' + qCount;
        if (sort) url += '&sort=' + sort;

        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.leaderboard.length > 0) {
            let html = '<table class="leaderboard-table"><thead><tr>';
            html += '<th>Rang</th><th>Joueur</th>';
            html += `<th class="sortable" onclick="setLeaderboardSort('score')" title="Trier par Score">Score ${getSortIcon('score')}</th>`;
            html += `<th class="sortable" onclick="setLeaderboardSort('accuracy')" title="Trier par Précision">Précision ${getSortIcon('accuracy')}</th>`;
            html += `<th class="sortable" onclick="setLeaderboardSort('time')" title="Trier par Temps">Temps ${getSortIcon('time')}</th>`;
            html += `<th class="sortable" onclick="setLeaderboardSort('date')" title="Trier par Date">Date ${getSortIcon('date')}</th>`;
            html += '</tr></thead><tbody>';

            data.leaderboard.forEach((entry, index) => {
                const accuracy = entry.questions_answered > 0 ? Math.round((entry.correct_answers / entry.questions_answered) * 100) : 0;
                const date = new Date(entry.played_at).toLocaleDateString('fr-FR');
                const rank = index + 1;
                const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : rank;
                
                const exactAvgTime = parseFloat(entry.avg_time || 0).toFixed(3);

                html += `<tr>
                    <td><strong>${medal}</strong></td>
                    <td>${entry.player_name || 'Anonyme'}</td>
                    <td><strong>${entry.score}</strong> pts</td>
                    <td>${accuracy}%</td>
                    <td>⏱️ ${exactAvgTime}s</td>
                    <td>${date}</td>
                </tr>`;
            });

            html += '</tbody></table>';
            content.innerHTML = html;
        } else {
            content.innerHTML = '<p class="empty-state" style="text-align:center; padding: 20px;">Aucun score ne correspond à ces critères.</p>';
        }
    } catch (error) {
        content.innerHTML = '<p class="error" style="color:var(--danger-color); text-align:center;">Erreur lors du chargement.</p>';
        console.error('Erreur:', error);
    } finally {
        content.style.opacity = '1';
        content.style.pointerEvents = 'auto';
    }
}