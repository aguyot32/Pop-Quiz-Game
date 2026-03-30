<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Question.php';
require_once __DIR__ . '/models/Database.php';

$questionModel = new Question();
$categories = $questionModel->getAllCategories();

foreach ($categories as &$category) {
    $category['question_count'] = $questionModel->countQuestionsByCategory($category['id']);
}
unset($category);

$db = Database::getInstance();
$qCounts = [];
try {
    $stmt = $db->query("SELECT DISTINCT questions_answered FROM scores WHERE questions_answered > 0 ORDER BY questions_answered ASC");
    if ($stmt) {
        $qCounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}

if (empty($qCounts)) {
    $qCounts = [5, 10, 15, 20, 30, 50];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pop Quiz Game - Accueil</title>
    <link rel="stylesheet" href="assets/css/home.css?v=<?= time() ?>">
    
    <style>
        .container {
            max-width: 1200px !important;
            padding: 20px !important;
        }

        .menu-card { 
            background: var(--bg-card); 
            padding: 30px; 
            border-radius: var(--border-radius); 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); 
            max-width: 100% !important; 
            margin: 0; 
            position: relative; 
            z-index: 1;
        }
        
        .category-selector-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; margin-bottom: 5px; }
        .cat-checkbox-wrapper { display: inline-block; cursor: pointer; }
        .cat-checkbox { display: none; }
        .cat-badge { display: flex; align-items: center; gap: 6px; padding: 8px 16px; background: var(--bg-dark); border: 2px solid transparent; border-radius: 20px; transition: all 0.2s ease; font-size: 0.95rem; user-select: none; opacity: 0.8; color: var(--text-color); }
        .cat-badge:hover { background: rgba(255, 255, 255, 0.1); transform: translateY(-2px); }
        .cat-checkbox:checked + .cat-badge { background: var(--primary-color); color: white; border-color: var(--primary-color); opacity: 1; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); font-weight: 600; }
        .cat-badge.all { font-weight: 700; border-color: var(--text-muted); }
        .cat-checkbox:checked + .cat-badge.all { background: white; color: var(--primary-color); border-color: white; }
        .cat-count { font-size: 0.75em; opacity: 0.7; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 10px; }
        #cat-selection-info { display: block; margin-top: 8px; font-style: italic; color: var(--success-color); }
        
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-color); }
        .form-select, .form-input { width: 100%; padding: 12px 16px; background: var(--bg-lighter); border: 2px solid transparent; border-radius: 8px; color: var(--text-color); }
        .form-select:focus, .form-input:focus { outline: none; border-color: var(--primary-color); }
        .menu-actions { text-align: center; margin-top: 20px; position: relative; z-index: 1;}
        
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; transition: all 0.2s ease; display: inline-block; text-align: center; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .btn-secondary { background: var(--bg-lighter); color: var(--text-color); }
        .btn-secondary:hover { background: #475569; }
        .btn-large { width: 100%; padding: 15px; font-size: 1.1rem; margin-top: 10px; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); padding: 30px; border-radius: var(--border-radius); max-width: 1200px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; }
        .modal-close { position: absolute; top: 15px; right: 20px; font-size: 2rem; cursor: pointer; color: var(--text-muted); }
        .leaderboard-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .leaderboard-table th, .leaderboard-table td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .leaderboard-table th { background: var(--bg-lighter); font-weight: 600; }

        .sortable { cursor: pointer; user-select: none; transition: background-color 0.2s; white-space: nowrap; }
        .sortable:hover { background-color: rgba(255,255,255,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1 class="logo">🎮 Pop Quiz Game</h1>
            <p class="tagline">Testez vos connaissances en culture pop !</p>
        </header>

        <main class="main-menu">
            <div class="menu-card">
                <h2>🎯 Nouvelle Partie</h2>
                
                <div class="form-group">
                    <label>Choisissez les catégories :</label>
                    <div class="category-selector-grid">
                        <label class="cat-checkbox-wrapper">
                            <input type="checkbox" id="cat-all" class="cat-checkbox" checked>
                            <span class="cat-badge all">🌍 Toutes</span>
                        </label>

                        <?php foreach ($categories as $cat): ?>
                            <label class="cat-checkbox-wrapper">
                                <input type="checkbox" value="<?= $cat['id'] ?>" class="cat-checkbox category-item">
                                <span class="cat-badge">
                                    <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                                    <span class="cat-count"><?= $cat['question_count'] ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="form-hint" id="cat-selection-info">Toutes les catégories sélectionnées</small>
                </div>

                <div class="form-group">
                    <label for="difficulty">Difficulté :</label>
                    <select id="difficulty" class="form-select">
                        <option value="">Toutes</option>
                        <option value="easy">Facile</option>
                        <option value="medium">Moyen</option>
                        <option value="hard">Difficile</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="questionCount">Nombre de questions :</label>
                    <select id="questionCount" class="form-select">
                        <option value="5">5 questions</option>
                        <option value="10" selected>10 questions</option>
                        <option value="15">15 questions</option>
                        <option value="20">20 questions</option>
                        <option value="30">30 questions</option>
                        <option value="50">50 questions</option>
                        <option value="100">100 questions</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="playerName">Votre pseudo (optionnel) :</label>
                    <input type="text" id="playerName" class="form-input" placeholder="Entrez votre nom">
                    <small class="form-hint" style="color: var(--text-muted); display: block; margin-top: 5px;">Pour sauvegarder votre score dans le classement</small>
                </div>

                <button id="startGameBtn" class="btn btn-primary btn-large">
                    Commencer la partie
                </button>
            </div>

            <div class="menu-actions">
                <button id="leaderboardBtn" class="btn btn-secondary">
                    🏆 Voir le classement
                </button>
                <a href="propose-question.php" class="btn btn-primary" style="text-decoration: none;">
                    📝 Proposer une question
                </a>
            </div>
        </main>

        <div id="leaderboardModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2 style="margin-bottom: 20px;">🏆 Classement des meilleurs scores</h2>
                
                <div class="filters-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 20px; background: var(--bg-darker); padding: 15px; border-radius: 8px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="leaderboardCategory" style="font-size: 0.85rem;">📂 Catégorie :</label>
                        <select id="leaderboardCategory" class="form-select" style="padding: 8px;">
                            <option value="">Toutes</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['icon'] . ' ' . $category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="leaderboardDifficulty" style="font-size: 0.85rem;">⭐ Difficulté :</label>
                        <select id="leaderboardDifficulty" class="form-select" style="padding: 8px;">
                            <option value="">Toutes</option>
                            <option value="easy">Facile</option>
                            <option value="medium">Moyenne</option>
                            <option value="hard">Difficile</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="leaderboardQuestions" style="font-size: 0.85rem;">📝 Nombre Questions :</label>
                        <select id="leaderboardQuestions" class="form-select" style="padding: 8px;">
                            <option value="">Tous</option>
                            <?php foreach ($qCounts as $count): ?>
                                <option value="<?= $count ?>"><?= $count ?> question<?= $count > 1 ? 's' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="leaderboardContent" class="leaderboard-list">
                    <p class="loading">Chargement...</p>
                </div>
            </div>
        </div>

        <footer class="footer">
            <p>&copy; 2026 Pop Quiz Game - Testez vos connaissances !</p>
        </footer>
    </div>

    <script src="assets/js/home.js"></script>
</body>
</html>