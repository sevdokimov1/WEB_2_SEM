<?php
// Подключение к базе данных
$host = 'localhost';
$dbname = 'form_db';
$username = 'root';
$password = 'root'; // В MAMP стандартный пароль root

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Валидация формы
$errors = [];

$fio = trim($_POST['fio'] ?? '');
if (empty($fio)) {
    $errors[] = "ФИО обязательно";
} elseif (strlen($fio) > 150) {
    $errors[] = "ФИО не более 150 символов";
} elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $fio)) {
    $errors[] = "ФИО только буквы и пробелы";
}

$email = trim($_POST['email'] ?? '');
if (empty($email)) {
    $errors[] = "Email обязателен";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Некорректный email";
}

$gender = $_POST['gender'] ?? '';
if (empty($gender)) {
    $errors[] = "Выберите пол";
} elseif (!in_array($gender, ['male', 'female', 'other'])) {
    $errors[] = "Недопустимый пол";
}

$languages = $_POST['languages'] ?? [];
if (empty($languages)) {
    $errors[] = "Выберите язык программирования";
}

$contract = isset($_POST['contract']) && $_POST['contract'] == '1';
if (!$contract) {
    $errors[] = "Подтвердите ознакомление с контрактом";
}

// Если есть ошибки
if (!empty($errors)) {
    $params = $_POST;
    $params['error'] = implode(', ', $errors);
    unset($params['languages']);
    header("Location: index.html?" . http_build_query($params));
    exit();
}

// Сохранение в БД
try {
    $pdo->beginTransaction();
    
    // Сохраняем пользователя
    $stmt = $pdo->prepare("INSERT INTO users (full_name, phone, email, birth_date, gender, bio) 
                           VALUES (:full_name, :phone, :email, :birth_date, :gender, :bio)");
    $stmt->execute([
        ':full_name' => $fio,
        ':phone' => $_POST['phone'] ?? '',
        ':email' => $email,
        ':birth_date' => $_POST['birthdate'] ?: null,
        ':gender' => $gender,
        ':bio' => $_POST['bio'] ?? ''
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Сохраняем языки
    $langStmt = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) 
                               VALUES (:user_id, (SELECT id FROM programming_languages WHERE name = :lang_name))");
    foreach ($languages as $lang) {
        $langStmt->execute([':user_id' => $userId, ':lang_name' => $lang]);
    }
    
    $pdo->commit();
    
    header("Location: index.html?success=1");
    exit();
    
} catch (PDOException $e) {
    $pdo->rollBack();
    header("Location: index.html?error=Ошибка сохранения");
    exit();
}
?>
