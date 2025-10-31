<?php

// 1. ADATBÁZIS BEÁLLÍTÁSOK
define('DB_HOST', 'localhost'); // Valószínűleg localhost, ha helyben futtatod
define('DB_USER', 'ax07057');
define('DB_PASS', 'an003722');
define('DB_NAME', 'vibe_rpg');

// 2. PDO KAPCSOLAT LÉTREHOZÁSA
try {
  // MySQL DSN (Data Source Name)
  $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

  // PDO opciók: hibakezelés és alapértelmezett beállítások
  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hibát dobjon, ha gond van az SQL-lel
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Alapértelmezett lekérdezési mód: asszociatív tömb
    PDO::ATTR_EMULATE_PREPARES   => false,                // Valódi Prepared Statements használata
  ];

  // A kapcsolat inicializálása
  $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
  // Hiba esetén ne mutassunk érzékeny információt a felhasználónak
  throw new \PDOException($e->getMessage(), (int)$e->getCode());
  // A fejlesztéshez ideiglenesen kiírhatod: die("Adatbázis hiba: " . $e->getMessage());
}
