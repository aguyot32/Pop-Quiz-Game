<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Question.php';
require_once __DIR__ . '/models/Database.php';

$questionModel = new Question();
$categories = $questionModel->getAllCategories();

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = $_POST['category_id'] ?? '';
    $answer = trim($_POST['answer'] ?? '');
    $alternatives = trim($_POST['answer_alternatives'] ?? '');
    $difficulty = $_POST['difficulty'] ?? 'medium';
    $hintText = trim($_POST['hint_text'] ?? '');
    $hintImageUrl = trim($_POST['hint_image_url'] ?? '');
    $proposedBy = trim($_POST['proposed_by'] ?? 'Anonyme');
    
    if (empty($categoryId) || empty($answer) || empty($hintText) || empty($hintImageUrl)) {
        $errorMessage = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        try {
            $db = Database::getInstance();
            
            $sql = "INSERT INTO question_proposals 
                    (category_id, answer, answer_alternatives, difficulty, hint_text, hint_image_url, proposed_by, proposed_at, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')";
            
            $success = $db->execute($sql, [
                $categoryId,
                $answer,
                $alternatives ?: null,
                $difficulty,
                $hintText,
                $hintImageUrl,
                $proposedBy
            ]);
            
            if ($success) {
                $successMessage = 'Merci ! Votre question a été soumise et sera examinée prochainement.';
                $_POST = [];
            } else {
                $errorMessage = 'Erreur lors de l\'enregistrement. Vérifiez les données.';
            }
        } catch (Exception $e) {
            $errorMessage = 'Erreur technique : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposer une question - Pop Quiz Game</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .proposal-container { max-width: 800px; margin: 40px auto; padding: 20px; }
        .proposal-card { background: var(--bg-card); padding: 40px; border-radius: var(--border-radius); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); }
        .proposal-header { text-align: center; margin-bottom: 40px; }
        .proposal-header h1 { font-size: 2.5rem; margin-bottom: 10px; color: var(--primary-color); }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: rgba(34, 197, 94, 0.2); border: 2px solid var(--success-color); color: var(--success-color); }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 2px solid var(--danger-color); color: var(--danger-color); }
        .form-info { background: rgba(99, 102, 241, 0.1); border-left: 4px solid var(--primary-color); padding: 15px 20px; margin-bottom: 30px; border-radius: 8px; }
        .back-link { display: inline-block; margin-top: 20px; color: var(--primary-color); text-decoration: none; font-weight: 600; }
        .preview-box { background: var(--bg-dark); padding: 20px; border-radius: 8px; margin-top: 10px; display: none; }
        .preview-image { max-width: 100%; max-height: 300px; border-radius: 8px; display: block; margin: 10px auto; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-color); }
        .form-select, .form-input { 
            width: 100%; 
            padding: 12px 16px; 
            background: var(--bg-lighter); 
            border: 2px solid transparent; 
            border-radius: 8px; 
            color: var(--text-color); 
            font-family: inherit;
            font-size: 1rem;
        }
        .form-select:focus, .form-input:focus { 
            outline: none; 
            border-color: var(--primary-color); 
        }
        .form-hint { 
            font-size: 0.85rem; 
            color: var(--text-muted); 
            margin-top: 5px; 
            display: block; 
        }
        body {
            height: auto;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="proposal-container">
        <div class="proposal-card">
            <div class="proposal-header">
                <h1>📝 Proposer une question</h1>
                <p>Contribuez au jeu en proposant vos propres questions !</p>
            </div>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success">✓ <?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-error">✗ <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            
            <div class="form-info">
                <p><strong>📌 Instructions :</strong></p>
                <p>• Entrez la réponse exacte et un indice clair.</p>
                <p>• L'image doit être une URL valide (jpg, png).</p>
                <p>• Les alternatives servent aux raccourcis (ex: "LOTR" pour "Lord of the Rings").</p>
            </div>
            
            <form method="POST" action="" id="proposalForm">
                <div class="form-group">
                    <label for="category_id">Catégorie *</label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">Sélectionnez une catégorie</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['icon'] . ' ' . $cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="answer">Réponse correcte *</label>
                    <input type="text" id="answer" name="answer" class="form-input" 
                           placeholder="Ex: Le Parrain" 
                           value="<?= htmlspecialchars($_POST['answer'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="answer_alternatives">Réponses alternatives (optionnel)</label>
                    <input type="text" id="answer_alternatives" name="answer_alternatives" class="form-input" 
                           placeholder="Ex: Parrain|Godfather" 
                           value="<?= htmlspecialchars($_POST['answer_alternatives'] ?? '') ?>">
                    <small class="form-hint">Séparées par |</small>
                </div>
                
                <div class="form-group">
                    <label for="difficulty">Difficulté *</label>
                    <select id="difficulty" name="difficulty" class="form-select" required>
                        <option value="easy" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'easy') ? 'selected' : '' ?>>Facile</option>
                        <option value="medium" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'medium') ? 'selected' : 'selected' ?>>Moyen</option>
                        <option value="hard" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'hard') ? 'selected' : '' ?>>Difficile</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="hint_text">Indice texte *</label>
                    <input list="text_options" id="hint_text" name="hint_text" class="form-input" 
                           placeholder="De quel film vient cette image ?" 
                           value="<?= htmlspecialchars($_POST['hint_text'] ?? '') ?>" required>
                    <datalist id="text_options">
                      <option value="De quel film vient cette image ?">
                      <option value="De quelle série vient cette image ?">
                      <option value="De quel jeu vient cette image ?">
                      <option value="De quel groupe/artiste vient cette image ?">
                      <option value="De quel animé/manga vient cette image ?">
                      <option value="De quel personnage vient cette image ?">
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label for="hint_image_url">URL de l'image d'indice *</label>
                    <input type="url" id="hint_image_url" name="hint_image_url" class="form-input" 
                           placeholder="https://..." 
                           value="<?= htmlspecialchars($_POST['hint_image_url'] ?? '') ?>" required>
                    
                    <div id="imagePreview" class="preview-box">
                        <img id="previewImg" src="" alt="Aperçu" class="preview-image">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="proposed_by">Votre pseudo (optionnel)</label>
                    <input type="text" id="proposed_by" name="proposed_by" class="form-input" 
                           placeholder="Anonyme" 
                           value="<?= htmlspecialchars($_POST['proposed_by'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn btn-primary btn-large">Envoyer ma proposition</button>
                <a href="index.php" class="back-link">← Retour au menu principal</a>
            </form>
        </div>
    </div>
    
    <script>
        const imageUrlInput = document.getElementById('hint_image_url');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        
        imageUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
                previewImg.src = url;
                imagePreview.style.display = 'block';
                previewImg.onerror = function() { imagePreview.style.display = 'none'; };
            } else {
                imagePreview.style.display = 'none';
            }
        });
    </script>
</body>
</html>