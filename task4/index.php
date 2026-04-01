<?php
// Подключение к базе данных
$host = 'localhost';
$dbname = 'form_db';
$username = 'root';
$password = 'root'; // для MAMP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Получаем сохраненные cookies (если есть)
$cookie_fields = ['fio', 'phone', 'email', 'birthdate', 'gender', 'bio', 'languages'];

// Функция для получения значения из cookie или POST
function getFieldValue($field, $postValue = null) {
    if ($postValue !== null && $postValue !== '') {
        return htmlspecialchars($postValue);
    }
    if (isset($_COOKIE[$field])) {
        return htmlspecialchars($_COOKIE[$field]);
    }
    return '';
}

// Функция для подсветки ошибки
function errorClass($field, $errors) {
    return isset($errors[$field]) ? 'error-field' : '';
}

$success = false;
$errors = [];

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ФИО: только буквы, пробелы, дефисы
    $fio = trim($_POST['fio'] ?? '');
    if (empty($fio)) {
        $errors['fio'] = "ФИО обязательно для заполнения";
    } elseif (strlen($fio) > 150) {
        $errors['fio'] = "ФИО не должно превышать 150 символов";
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $fio)) {
        $errors['fio'] = "ФИО может содержать только буквы, пробелы и дефисы";
    }
    
    // 2. Телефон: формат +7 (999) 123-45-67 или подобный
    $phone = trim($_POST['phone'] ?? '');
    if (!empty($phone) && !preg_match('/^[\+\d\s\(\)\-]{10,20}$/', $phone)) {
        $errors['phone'] = "Телефон должен содержать только цифры, пробелы, скобки и знак +";
    }
    
    // 3. Email
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = "Email обязателен для заполнения";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Некорректный формат email";
    }
    
    // 4. Дата рождения
    $birthdate = $_POST['birthdate'] ?? '';
    if (!empty($birthdate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        $errors['birthdate'] = "Некорректный формат даты (ГГГГ-ММ-ДД)";
    }
    
    // 5. Пол
    $gender = $_POST['gender'] ?? '';
    $allowedGenders = ['male', 'female', 'other'];
    if (empty($gender)) {
        $errors['gender'] = "Выберите пол";
    } elseif (!in_array($gender, $allowedGenders)) {
        $errors['gender'] = "Недопустимое значение поля 'Пол'";
    }
    
    // 6. Языки программирования
    $languages = $_POST['languages'] ?? [];
    $validLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
    
    if (empty($languages)) {
        $errors['languages'] = "Выберите хотя бы один язык программирования";
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $validLanguages)) {
                $errors['languages'] = "Выбран недопустимый язык программирования";
                break;
            }
        }
    }
    
    // 7. Биография
    $bio = trim($_POST['bio'] ?? '');
    if (strlen($bio) > 5000) {
        $errors['bio'] = "Биография не должна превышать 5000 символов";
    }
    
    // 8. Контракт
    $contract = isset($_POST['contract']) && $_POST['contract'] == '1';
    if (!$contract) {
        $errors['contract'] = "Необходимо ознакомиться с контрактом";
    }
    
    // Если ошибок нет — сохраняем
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Сохраняем пользователя
            $stmt = $pdo->prepare("INSERT INTO users (full_name, phone, email, birth_date, gender, bio) 
                                   VALUES (:full_name, :phone, :email, :birth_date, :gender, :bio)");
            $stmt->execute([
                ':full_name' => $fio,
                ':phone' => $phone,
                ':email' => $email,
                ':birth_date' => $birthdate ?: null,
                ':gender' => $gender,
                ':bio' => $bio
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Сохраняем языки
            $langStmt = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) 
                                       VALUES (:user_id, (SELECT id FROM programming_languages WHERE name = :lang_name))");
            foreach ($languages as $lang) {
                $langStmt->execute([':user_id' => $userId, ':lang_name' => $lang]);
            }
            
            $pdo->commit();
            
            // Сохраняем данные в Cookies на 1 год (365 дней)
            setcookie('fio', $fio, time() + 365 * 24 * 60 * 60, '/');
            setcookie('phone', $phone, time() + 365 * 24 * 60 * 60, '/');
            setcookie('email', $email, time() + 365 * 24 * 60 * 60, '/');
            setcookie('birthdate', $birthdate, time() + 365 * 24 * 60 * 60, '/');
            setcookie('gender', $gender, time() + 365 * 24 * 60 * 60, '/');
            setcookie('bio', $bio, time() + 365 * 24 * 60 * 60, '/');
            setcookie('languages', implode(',', $languages), time() + 365 * 24 * 60 * 60, '/');
            
            $success = true;
            
            // Удаляем ошибки из cookies
            setcookie('errors', '', time() - 3600, '/');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = "Ошибка сохранения данных";
        }
    } else {
        // Если есть ошибки, сохраняем их в cookies
        setcookie('errors', json_encode($errors), time() + 3600, '/');
        // Сохраняем введенные данные в cookies (временные)
        setcookie('temp_fio', $fio, time() + 3600, '/');
        setcookie('temp_phone', $phone, time() + 3600, '/');
        setcookie('temp_email', $email, time() + 3600, '/');
        setcookie('temp_birthdate', $birthdate, time() + 3600, '/');
        setcookie('temp_gender', $gender, time() + 3600, '/');
        setcookie('temp_bio', $bio, time() + 3600, '/');
        setcookie('temp_languages', implode(',', $languages), time() + 3600, '/');
        
        // Перенаправление методом GET
        header('Location: index.php');
        exit();
    }
}

// Получаем ошибки из cookies (если есть)
if (isset($_COOKIE['errors'])) {
    $errors = json_decode($_COOKIE['errors'], true) ?: [];
    // Удаляем ошибки после отображения
    setcookie('errors', '', time() - 3600, '/');
}

// Получаем временные данные (если были ошибки)
$temp_data = [];
$temp_fields = ['fio', 'phone', 'email', 'birthdate', 'gender', 'bio', 'languages'];
foreach ($temp_fields as $field) {
    if (isset($_COOKIE["temp_$field"])) {
        $temp_data[$field] = $_COOKIE["temp_$field"];
        // Удаляем временные cookies
        setcookie("temp_$field", '', time() - 3600, '/');
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрационная форма (Lab 4 - Cookies)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        form {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h2 { text-align: center; color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .required::after { content: " *"; color: red; }
        input[type="text"], input[type="tel"], input[type="email"], input[type="date"], select, textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .error-field {
            border-color: #dc3545 !important;
            background-color: #fff8f8;
        }
        .radio-group { display: flex; gap: 20px; margin-top: 8px; }
        .radio-group label { display: inline-flex; align-items: center; font-weight: normal; }
        .radio-group input { width: auto; margin-right: 5px; }
        select[multiple] { height: 150px; }
        .checkbox-group { margin: 20px 0; }
        .checkbox-group label { display: inline; font-weight: normal; }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover { transform: translateY(-2px); }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .field-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        small { color: #666; font-size: 12px; display: block; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <form method="POST">
            <h2>Регистрационная форма (Lab 4)</h2>
            
            <?php if ($success): ?>
                <div class="success">✅ Данные успешно сохранены! Данные сохранены в Cookies на 1 год.</div>
            <?php endif; ?>
            
            <?php if (!empty($errors) && !$success): ?>
                <div class="error-message">
                    <strong>Пожалуйста, исправьте следующие ошибки:</strong><br>
                    <?php foreach ($errors as $error): ?>
                        • <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="required">1. ФИО:</label>
                <input type="text" name="fio" class="<?php echo errorClass('fio', $errors); ?>" 
                       value="<?php echo getFieldValue('fio', $temp_data['fio'] ?? $_POST['fio'] ?? null); ?>">
                <?php if (isset($errors['fio'])): ?>
                    <span class="field-error">⚠️ <?php echo $errors['fio']; ?></span>
                <?php endif; ?>
                <small>Допустимы только буквы, пробелы и дефисы</small>
            </div>

            <div class="form-group">
                <label>2. Телефон:</label>
                <input type="tel" name="phone" class="<?php echo errorClass('phone', $errors); ?>"
                       value="<?php echo getFieldValue('phone', $temp_data['phone'] ?? $_POST['phone'] ?? null); ?>">
                <?php if (isset($errors['phone'])): ?>
                    <span class="field-error">⚠️ <?php echo $errors['phone']; ?></span>
                <?php endif; ?>
                <small>Формат: +7 (999) 123-45-67</small>
            </div>

            <div class="form-group">
                <label class="required">3. E-mail:</label>
                <input type="email" name="email" class="<?php echo errorClass('email', $errors); ?>"
                       value="<?php echo getFieldValue('email', $temp_data['email'] ?? $_POST['email'] ?? null); ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error">⚠️ <?php echo $errors['email']; ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>4. Дата рождения:</label>
                <input type="date" name="birthdate" class="<?php echo errorClass('birthdate', $errors); ?>"
                       value="<?php echo getFieldValue('birthdate', $temp_data['birthdate'] ?? $_POST['birthdate'] ?? null); ?>">
                <?php if (isset($errors['birthdate'])): ?>
                    <span class="field-error">⚠️ <?php echo $errors['birthdate']; ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="required">5. Пол:</label>
                <div class="radio-group">
                    <?php $selectedGender = getFieldValue('gender', $temp_data['gender'] ?? $_POST['gender'] ?? null); ?>
                    <label><input type="radio" name="gender" value="male" <?php echo $selectedGender == 'male' ? 'checked' : ''; ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?php echo $selectedGender == 'female' ? 'checked' : ''; ?>> Женский</label>
                    <label><input type="radio" name="gender" value="other" <?php echo $selectedGender == 'other' ? 'checked' : ''; ?>> Другой</label>
                </div>
                <?php if (isset($errors['gender'])): ?>
                    <span class="field-error">⚠️ <?php echo $errors['gender']; ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="required">6. Любимый язык программирования:</label>
                <?php
                $selectedLanguages = [];
                $cookiesLang = $_COOKIE['languages'] ?? '';
                if ($cookiesLang) {
                    $selectedLanguages = explode(',', $cookiesLang);
                }
                if (isset($temp_data['languages']) && $temp_data['languages']) {
                    $selectedLanguages = explode(',', $temp_data['languages']);
                }
                if (isset($_POST['languages'])) {
                    $selectedLanguages = $_POST['languages'];
                }
                ?>
                <select name="languages[]" multiple size="6" class="<?php echo errorClass('languages', $errors); ?>">
                    <option value="Pascal" <?php echo in_array('Pascal', $selectedLanguages) ? 'selected' : ''; ?>>Pascal</option>
                    <option value="C" <?php echo in_array('C', $selectedLanguages) ? 'selected' : ''; ?>>C</option>
                    <option value="C++" <?php echo in_array('C++', $selectedLanguages) ? 'selected' : ''; ?>>C++</option>
                    <option value="JavaScript" <?php echo in_array('JavaScript', $selectedLanguages) ? 'selected' : ''; ?>>JavaScript</option>
                    <option value="PHP" <?php echo in_array('PHP', $selectedLanguages) ? 'selected' : ''; ?>>PHP</option>
                    <option value="Python" <?php echo in_array('Python', $selectedLanguages) ? 'selected' : ''; ?>>Python</option>
                    <option value="Java" <?php echo in_array('Java', $selectedLanguages) ? 'selected' : ''; ?>>Java</option>
                    <option value="Haskell" <?php echo in_array('Haskell', $selectedLanguages) ? 'selected' : ''; ?>>Haskell</option>
                    <option value="Clojure" <?php echo in_array('Clojure', $selectedLanguages) ? 'selected' : ''; ?>>Clojure</option>
                    <option value="Prolog" <?php echo in_array('Prolog', $selectedLanguages) ? 'selected' : ''; ?>>Prolog</option>
                    <option value="Scala" <?php echo in_array('Scala', $selectedLanguages) ? 'selected' : ''; ?>>Scala</option>
                    <option value="Go" <?php echo in_array('Go', $selectedLanguages) ? 'selected' : ''; ?>>Go</option>
                </select>
                <?php if (isset($errors['languages'])): ?>
                    <span class="field-error">⚠️ <?php echo $errors['languages']; ?></span>
                <?php endif; ?>
                <small>Зажмите Ctrl (Cmd) и кликайте для выбора нескольких</small>
            </div>

            <div class="form-group">
                <label>7. Биография:</label>
                <textarea name="bio" rows="5" class="<?php echo errorClass('bio', $errors); ?>"><?php echo getFieldValue('bio', $temp_data['bio'] ?? $_POST['bio'] ?? null); ?></textarea>
                <?php if (isset($errors['bio'])): ?>
                    <span class="field-error">⚠️ <?php echo $errors['bio']; ?></span>
                <?php endif; ?>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" name="contract" value="1" <?php echo isset($_POST['contract']) ? 'checked' : (isset($_COOKIE['contract']) ? 'checked' : ''); ?>>
                <label class="required">8. С контрактом ознакомлен(а)</label>
                <?php if (isset($errors['contract'])): ?>
                    <span class="field-error">⚠️ <?php echo $errors['contract']; ?></span>
                <?php endif; ?>
            </div>

            <button type="submit">9. Сохранить</button>
        </form>
    </div>
</body>
</html>
