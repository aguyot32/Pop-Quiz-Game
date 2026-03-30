<?php
// models/Database.php
// Classe singleton pour la connexion SQLite

class Database {
    private static $instance = null;
    private $connection;

    // Constructeur privé pour empêcher l'instanciation directe
    private function __construct() {
        try {
            // Vérifier si le dossier database existe
            $dbDir = dirname(DB_PATH);
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            // Connexion à SQLite
            $this->connection = new PDO('sqlite:' . DB_PATH);
            
            // Configuration PDO
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Activer les clés étrangères (important pour SQLite)
            $this->connection->exec('PRAGMA foreign_keys = ON;');
            
            // Initialiser la base si elle n'existe pas
            $this->initializeDatabase();
            
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    // Empêcher le clonage de l'instance
    private function __clone() {}

    // Empêcher la désérialisation
    public function __wakeup() {
        throw new Exception("Impossible de désérialiser un singleton");
    }

    // Obtenir l'instance unique
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Obtenir la connexion PDO
    public function getConnection() {
        return $this->connection;
    }

    // Méthode helper pour les requêtes SELECT
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erreur SQL : " . $e->getMessage());
            return false;
        }
    }

    // Méthode helper pour INSERT/UPDATE/DELETE
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erreur SQL : " . $e->getMessage());
            return false;
        }
    }

    // Obtenir le dernier ID inséré
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    // Initialiser la base de données si elle est vide
    private function initializeDatabase() {
        // Vérifier si la table categories existe
        $result = $this->connection->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='categories'"
        );
        
        if ($result->fetch() === false) {
            // La base est vide, charger le schéma
            $schemaFile = __DIR__ . '/../sql/schema_sqlite.sql';
            
            if (file_exists($schemaFile)) {
                $schema = file_get_contents($schemaFile);
                $this->connection->exec($schema);
            } else {
                // Si le fichier SQL n'existe pas, créer les tables manuellement
                $this->createTables();
            }
        }
    }

    // Créer les tables si le fichier SQL n'est pas disponible
    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            icon TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL,
            answer TEXT NOT NULL,
            answer_alternatives TEXT,
            difficulty TEXT DEFAULT 'medium' CHECK(difficulty IN ('easy', 'medium', 'hard')),
            hint_text TEXT,
            hint_image TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_category ON questions(category_id);
        CREATE INDEX IF NOT EXISTS idx_difficulty ON questions(difficulty);

        CREATE TABLE IF NOT EXISTS scores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_name TEXT,
            score INTEGER NOT NULL,
            questions_answered INTEGER NOT NULL,
            correct_answers INTEGER NOT NULL,
            category_id INTEGER,
            played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        );

        CREATE INDEX IF NOT EXISTS idx_score ON scores(score DESC);
        CREATE INDEX IF NOT EXISTS idx_played_at ON scores(played_at DESC);
        ";
        
        $this->connection->exec($sql);
    }
}