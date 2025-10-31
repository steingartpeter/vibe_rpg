<?php
// Ezt a fájlt be kell húzni, hogy elérhető legyen a $pdo kapcsolat objektum
include_once $_SERVER['DOCUMENT_ROOT'] . '/vibe_rpg/backend/db.php';
include_once  $_SERVER['DOCUMENT_ROOT'] . '/vibe_rpg/backend/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Csak a 'nev' mezőt fogadjuk el a felhasználótól
    $nev = trim($_POST['nev'] ?? '');

    if (empty($nev)) {
        $error = 'Kérlek adj meg egy nevet a karakterednek.';
    } else {
        // Karakter mentése a függvényben (lásd B. pont)
        $result = mentes_uj_karakter($pdo, $nev);

        if ($result === true) {
            $success = "Sikeresen létrehoztad a karaktert: **{$nev}**! Kezdődhet a játék.";
        } else {
            $error = $result; // A hibaüzenetet kapjuk vissza (pl. "Név már foglalt")
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Új Karakter Létrehozása</title>
</head>

<body>
    <h1>Új Karakter Létrehozása</h1>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: green;"><?php echo $success; ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="nev">Karakter neve (Csak név a prototípusban):</label><br>
        <input type="text" id="nev" name="nev" required><br><br>
        <button type="submit">Karakter Létrehozása</button>
    </form>
</body>

</html>