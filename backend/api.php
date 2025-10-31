<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vibe_rpg/backend/functions.php'; // Függvények betöltése
require $_SERVER['DOCUMENT_ROOT'] . '/vibe_rpg/backend/db.php'; // Adatbázis kapcsolat betöltése



header('Content-Type: application/json'); // Minden válasz JSON formátumú lesz
header('Access-Control-Allow-Origin: *'); // Fejlesztéshez engedélyezzük a CORS-t

// 1. Kérés feldolgozása
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$nev = $input['nev'] ?? '';

$response = ['status' => 'error', 'message' => 'Ismeretlen hiba történt.'];

// 2. Akciók kezelése (SWITCH)
switch ($action) {
  case 'load_character':
    $response = loadCharacter($pdo, $nev);
    break;

  case 'do_activity':
    $tevekenyseg = $input['type'] ?? ''; // Itt type van, mert a JS-ben is azt használtuk
    $nev = $input['nev'] ?? '';
    $response = doActivity($pdo, $nev, $tevekenyseg);
    break;

  // ... Később: save_character, rest, stb.
  case 'allocate_skill_point':
    $skill_type = $input['skill_type'] ?? '';
    $nev = $input['nev'] ?? '';
    $response = allocateSkillPoint($pdo, $nev, $skill_type);
    break;
  default:
    $response = ['status' => 'error', 'message' => 'Érvénytelen akció.'];
    break;
}

// 3. Válasz küldése a kliensnek
echo json_encode($response);
exit;

// 4. KARAKTER BETÖLTÉSE FÜGGVÉNY
function loadCharacter(PDO $pdo, string $nev): array
{
  $sql = "SELECT nev, szint, xp, gyujto_skill, harc_skill, energia, pos_x, pos_y FROM karakter WHERE nev = :nev";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':nev', $nev, PDO::PARAM_STR);
  $stmt->execute();
  $karakter = $stmt->fetch();

  if ($karakter) {
    return ['status' => 'ok', 'karakter' => $karakter];
  } else {
    return ['status' => 'error', 'message' => 'A megadott nevű karakter nem található.'];
  }
}

function doActivity(PDO $pdo, string $nev, string $type): array
{
  // 1. Karakter betöltése
  $charResult = loadCharacter($pdo, $nev);
  if ($charResult['status'] !== 'ok') {
    return ['status' => 'error', 'message' => 'Karakter nem található.'];
  }
  $char = $charResult['karakter'];

  // 2. Energia ellenőrzés
  $energia_kell = 10;
  if ($char['energia'] < $energia_kell) {
    return ['status' => 'error', 'message' => 'Nincs elég energia ehhez a tevékenységhez. Pihenj!'];
  }

  // 3. Tevékenység végrehajtása (egyszerű logika)
  $xp_nyerese = 0;
  $log_message = '';

  if ($type === 'collect') {
    // Gyűjtés: XP nyerés a skill szint alapján
    $xp_nyerese = $char['gyujto_skill'] * 2;
    $log_message = "Sikeresen gyűjtöttél nyersanyagot. ";
  } elseif ($type === 'combat') {
    // Harc: XP nyerés a harc skill szint alapján
    $xp_nyerese = $char['harc_skill'] * 3;
    $log_message = "Megküzdöttél egy szörnnyel. ";
  } elseif ($type === 'rest') {
    // Pihenés: Energia növelése, XP nyerés nulla
    $energia_vissza = 30;
    $char['energia'] = min(100, $char['energia'] + $energia_vissza);
    $log_message = "Pihentél. Visszatöltöttél {$energia_vissza} energiát.";

    $energia_kell = 0; // Pihenés nem fogyaszt energiát
    $xp_nyerese = 0;
  } else {
    return ['status' => 'error', 'message' => 'Ismeretlen tevékenység típus.'];
  }

  // 4. Statisztikák frissítése
  $char['energia'] -= $energia_kell;
  $char['xp'] += $xp_nyerese;
  $log_message .= "+{$xp_nyerese} XP-t szereztél.";

  // ÚJ LOGIKA: SZINTLÉPÉS ELLENŐRZÉSE
  $level_up_occurred = false;
  $level_up_message = '';

  // Szintlépés feltétel: (Szint * 100) XP kell a következő szinthez
  // Pl. 1. szinthez 100 XP, 2. szinthez 200 XP, stb.
  while ($char['xp'] >= ($char['szint'] * 100)) {
    $xp_for_next_level = $char['szint'] * 100;
    $char['xp'] -= $xp_for_next_level; // XP maradvány
    $char['szint'] += 1; // Új szint
    $char['skill_pontok'] += 1; // +1 Skill pont
    $level_up_occurred = true;
  }

  if ($level_up_occurred) {
    $level_up_message = " *** SZINTET LÉPTÉL! Új szint: {$char['szint']}! +1 Skill Pontot kaptál. ***";
  }
  $log_message .= $level_up_message;

  // 5. Adatbázis frissítése (mentés)
  $sql_update = "UPDATE karakter SET xp = :xp, energia = :energia, szint = :szint, skill_pontok = :skill_pontok WHERE nev = :nev";
  $stmt_update = $pdo->prepare($sql_update);
  $stmt_update->execute([
    ':xp' => $char['xp'],
    ':energia' => $char['energia'],
    ':szint' => $char['szint'], // ÚJ MEZŐ
    ':skill_pontok' => $char['skill_pontok'], // ÚJ MEZŐ
    ':nev' => $nev
  ]);

  // 6. Válasz küldése
  return [
    'status' => 'ok',
    'karakter' => $char, // A frissített statisztikákat visszaküldjük
    'message' => $log_message
  ];
}

function allocateSkillPoint(PDO $pdo, string $nev, string $skill_type): array
{
  // 1. Karakter betöltése és ellenőrzés
  $charResult = loadCharacter($pdo, $nev);
  if ($charResult['status'] !== 'ok') {
    return ['status' => 'error', 'message' => 'Karakter nem található.'];
  }
  $char = $charResult['karakter'];

  if ($char['skill_pontok'] < 1) {
    return ['status' => 'error', 'message' => 'Nincs felhasználható skill pontod.'];
  }

  // 2. Skill frissítése
  $target_skill = '';

  if ($skill_type === 'gyujto') {
    $char['gyujto_skill'] += 1;
    $target_skill = 'gyujto_skill';
  } elseif ($skill_type === 'harc') {
    $char['harc_skill'] += 1;
    $target_skill = 'harc_skill';
  } else {
    return ['status' => 'error', 'message' => 'Érvénytelen skill típus.'];
  }

  $char['skill_pontok'] -= 1; // Felhasználtuk a pontot

  // 3. Adatbázis frissítése
  $sql_update = "UPDATE karakter SET {$target_skill} = :skill_ertek, skill_pontok = :pontok WHERE nev = :nev";
  $stmt_update = $pdo->prepare($sql_update);
  $stmt_update->execute([
    ':skill_ertek' => $char[$target_skill],
    ':pontok' => $char['skill_pontok'],
    ':nev' => $nev
  ]);

  return [
    'status' => 'ok',
    'karakter' => $char,
    'message' => "Sikeresen elköltöttél egy pontot a(z) **{$skill_type}** skillre. Új szint: {$char[$target_skill]}."
  ];
}
