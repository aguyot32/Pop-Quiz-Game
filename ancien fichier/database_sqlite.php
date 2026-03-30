<?php
// config/database.php
// Configuration pour SQLite

// Chemin de la base de données SQLite
// Utilisation de /tmp car le serveur refuse l'écriture dans public_html
define('DB_PATH', '/tmp/pop_game.db');

// Configuration des chemins
define('BASE_PATH', dirname(__DIR__));
define('ASSETS_PATH', BASE_PATH . '/assets');
define('IMAGES_PATH', ASSETS_PATH . '/images');
define('HINTS_PATH', IMAGES_PATH . '/hints');

// URLs de base - ADAPTEZ SELON VOTRE CONFIGURATION
// Si vous êtes sur http://localhost/pop-game/
define('BASE_URL', 'http://localhost/pop-game');

// Si vous êtes sur un serveur comme le vôtre, adaptez :
// define('BASE_URL', 'http://votre-domaine.com');

define('ASSETS_URL', BASE_URL . '/assets');
define('IMAGES_URL', ASSETS_URL . '/images');
define('HINTS_URL', IMAGES_URL . '/hints');

// Configuration du jeu
define('LETTERS_REVEAL_DELAY', 500); // Délai en ms entre chaque lettre
define('MAX_TIME_PER_QUESTION', 30); // Temps maximum en secondes
define('POINTS_FAST_ANSWER', 100); // Points pour réponse rapide
define('POINTS_MEDIUM_ANSWER', 50); // Points pour réponse moyenne
define('POINTS_SLOW_ANSWER', 25); // Points pour réponse lente

// Image par défaut si l'image n'existe pas
define('DEFAULT_HINT_IMAGE', 'default.jpg');