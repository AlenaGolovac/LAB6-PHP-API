
<?php
// Практична робота №6
// Варіант 11 — Система обліку студентів/оцінок
// Єдина точка входу: api.php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

// Формування JSON-відповіді
function sendResponse(int $status, array $payload): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

// Формування відповіді з помилкою
function errorResponse(int $status, string $message): never {
    sendResponse($status, [
        'success' => false,
        'error' => $message
    ]);
}

// Безпечне отримання параметрів URL
function getQueryParam(string $name): ?string {
    if (!array_key_exists($name, $_GET)) {
        return null;
    }

    if (!is_string($_GET[$name])) {
        errorResponse(400, "Некоректний параметр: $name");
    }

    return $_GET[$name];
}

// Перевірка ID студента
function parsePositiveId(string $id): int {
    $value = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($value === false) {
        errorResponse(400, 'Некоректний ID студента');
    }

    return $value;
}

// Підготовка даних студента для JSON
function normalizeStudent(array $student): array {
    return [
        'id' => (int)$student['id'],
        'name' => (string)$student['name'],
        'group_name' => (string)$student['group_name'],
        'average_grade' => (float)$student['average_grade']
    ];
}

// GET — отримання списку студентів
function getStudents(PDO $pdo): never {
    $stmt = $pdo->query(
        'SELECT id, name, group_name, average_grade
         FROM students
         ORDER BY id'
    );

    $students = array_map(
        'normalizeStudent',
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    sendResponse(200, [
        'success' => true,
        'data' => $students
    ]);
}

// GET — отримання студента за ID
function getStudentById(PDO $pdo, string $id): never {
    $studentId = parsePositiveId($id);

    $stmt = $pdo->prepare(
        'SELECT id, name, group_name, average_grade
         FROM students
         WHERE id = :id'
    );

    $stmt->execute(['id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student === false) {
        errorResponse(404, 'Студента не знайдено');
    }

    sendResponse(200, [
        'success' => true,
        'data' => normalizeStudent($student)
    ]);
}

// Отримання даних POST-запиту
function getPostData(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');

        try {
            // Розрізняємо JSON-об'єкт {} та масив []
            $input = json_decode(
                $raw === false ? '' : $raw,
                false,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            errorResponse(400, 'Некоректний формат JSON');
        }

        if (!$input instanceof stdClass) {
            errorResponse(400, 'Очікується JSON-об’єкт');
        }

        return (array)$input;
    }

    return $_POST;
}

// POST — створення нового студента
function createStudent(PDO $pdo): never {
    $input = getPostData();

    // Перевірка обов'язкових полів
    foreach (['name', 'group_name', 'average_grade'] as $field) {
        if (
            !array_key_exists($field, $input) ||
            !is_scalar($input[$field]) ||
            is_bool($input[$field]) ||
            trim((string)$input[$field]) === ''
        ) {
            errorResponse(
                400,
                "Відсутнє або некоректне поле: $field"
            );
        }
    }

    $name = trim((string)$input['name']);
    $groupName = trim((string)$input['group_name']);
    $gradeRaw = (string)$input['average_grade'];

    // Перевірка довжини полів
    if (
        mb_strlen($name, 'UTF-8') > 100 ||
        mb_strlen($groupName, 'UTF-8') > 50
    ) {
        errorResponse(
            400,
            'Перевищено допустиму довжину поля'
        );
    }

    // Перевірка середнього бала
    if (
        !is_numeric($gradeRaw) ||
        !is_finite((float)$gradeRaw) ||
        (float)$gradeRaw < 0 ||
        (float)$gradeRaw > 100
    ) {
        errorResponse(
            400,
            'Середній бал має бути числом від 0 до 100'
        );
    }

    $grade = round((float)$gradeRaw, 2);

    // Додавання запису через підготовлений SQL-запит
    $stmt = $pdo->prepare(
        'INSERT INTO students
         (name, group_name, average_grade)
         VALUES (:name, :group_name, :average_grade)'
    );

    $stmt->execute([
        'name' => $name,
        'group_name' => $groupName,
        'average_grade' => $grade
    ]);

    // Отримання ID нового студента
    $newId = (int)$pdo->lastInsertId();

    // Отримання створеного запису
    $stmt = $pdo->prepare(
        'SELECT id, name, group_name, average_grade
         FROM students
         WHERE id = :id'
    );

    $stmt->execute(['id' => $newId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student === false) {
        errorResponse(
            500,
            'Не вдалося отримати створений запис'
        );
    }

    // Успішне створення — HTTP 201
    sendResponse(201, [
        'success' => true,
        'data' => normalizeStudent($student)
    ]);
}

// POST action=top — найкращий студент у групі
function getTopStudent(PDO $pdo): never {
    $input = getPostData();

    // Перевірка обов'язкового поля group_name
    if (
        !isset($input['group_name']) ||
        !is_string($input['group_name']) ||
        trim($input['group_name']) === ''
    ) {
        errorResponse(
            400,
            'Відсутнє або некоректне поле: group_name'
        );
    }

    $groupName = trim($input['group_name']);

    if (mb_strlen($groupName, 'UTF-8') > 50) {
        errorResponse(
            400,
            'Некоректна довжина назви групи'
        );
    }

    // Пошук студента з максимальним середнім балом
    $stmt = $pdo->prepare(
        'SELECT id, name, group_name, average_grade
         FROM students
         WHERE group_name = :group_name
         ORDER BY average_grade DESC, id ASC
         LIMIT 1'
    );

    $stmt->execute([
        'group_name' => $groupName
    ]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Якщо студентів у групі немає
    if ($student === false) {
        errorResponse(
            404,
            'У вказаній групі студентів не знайдено'
        );
    }

    // Успішна відповідь — HTTP 200
    sendResponse(200, [
        'success' => true,
        'data' => normalizeStudent($student)
    ]);
}

// Отримання HTTP-методу та параметрів запиту
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$resource = getQueryParam('resource');
$id = getQueryParam('id');
$action = getQueryParam('action');

// Перевірка ресурсу
if ($resource !== 'students') {
    errorResponse(404, 'Ресурс не знайдено');
}

// Перевірка HTTP-методу
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');

    errorResponse(405, 'Метод не підтримується');
}

// Перевірка параметрів маршрутизації
if ($method === 'GET' && $action !== null) {
    errorResponse(
        400,
        'Параметр action не підтримується для GET'
    );
}

if ($method === 'POST' && $id !== null) {
    errorResponse(
        400,
        'Параметр id не підтримується для POST'
    );
}

if (
    $method === 'POST' &&
    $action !== null &&
    $action !== 'top'
) {
    errorResponse(400, 'Невідома дія');
}

// Підключення до MySQL та виконання запитів
try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3306;dbname=students_db;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // GET — список або один студент
    if ($method === 'GET') {
        if ($id === null) {
            getStudents($pdo);
        }

        getStudentById($pdo, $id);
    }

    // POST — доменна дія action=top
    if ($action === 'top') {
        getTopStudent($pdo);
    }

    // POST — створення студента
    createStudent($pdo);

} catch (PDOException $e) {
    error_log('Database error in api.php: ' . $e->getCode());

    errorResponse(
        500,
        'Помилка роботи з базою даних'
    );

} catch (Throwable $e) {
    error_log(
        'Server error in api.php: ' . get_class($e)
    );

    errorResponse(
        500,
        'Внутрішня помилка сервера'
    );
}
