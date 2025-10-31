<?php

/**
 * Ment egy új karaktert az adatbázisba.
 * Csak a nevét várja, minden más érték a tábla alapértelmezett (DEFAULT) értéke lesz.
 * @param PDO $pdo A PDO kapcsolat objektum.
 * @param string $nev A karakter neve.
 * @return bool|string True sikeres mentés esetén, string hibaüzenet esetén.
 */
function mentes_uj_karakter(PDO $pdo, string $nev): bool|string
{
  $sql = "INSERT INTO karakter (nev) VALUES (:nev)";

  try {
    $stmt = $pdo->prepare($sql);

    // PARAMÉTER KÖTÉS: Ez biztosítja a biztonságot (SQL Injection elleni védelem)
    $stmt->bindParam(':nev', $nev, PDO::PARAM_STR);

    $stmt->execute();

    return true; // Sikeres mentés

  } catch (\PDOException $e) {
    // Ellenőrizni kell, hogy a hiba a "DUPLICATE ENTRY" (egyedi kulcs megsértése) miatt van-e
    // MySQL/MariaDB hiba kód egyedi kulcs megsértésére: 23000 (vagy a 1062 specifikus kód)
    if ($e->getCode() === '23000' || strpos($e->getMessage(), 'Duplicate entry') !== false) {
      return "Ez a karakter név ('{$nev}') már foglalt. Kérlek válassz másikat.";
    }

    // Egyéb adatbázis hiba
    error_log("Karakter mentési hiba: " . $e->getMessage()); // Naplózás
    return "Adatbázis hiba történt a mentés során.";
  }
}
