
-- Практична робота №6
-- Варіант 11 — Система обліку студентів/оцінок
-- База даних: students_db
-- СУБД: MySQL

-- Створення бази даних
CREATE DATABASE IF NOT EXISTS students_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE students_db;

-- Видалення таблиці, якщо вона вже існує
-- УВАГА: при повторному імпорті таблиця буде замінена
DROP TABLE IF EXISTS students;

-- Створення таблиці студентів
CREATE TABLE students (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    group_name VARCHAR(50) NOT NULL,
    average_grade DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Додавання тестових записів
INSERT INTO students
(id, name, group_name, average_grade)
VALUES
(1, 'Іван Петренко', 'ІО-41', 92.50),
(2, 'Марія Коваль', 'ІО-41', 95.00),
(3, 'Олена Шевченко', 'ІО-42', 89.50),
(4, 'Андрій Мельник', 'ІО-42', 96.00),
(5, 'Софія Бондар', 'ІО-41', 87.50),
(6, 'Дмитро Савченко', 'ІО-41', 91.50),
(7, 'Катерина Лисенко', 'ІО-43', 93.50);

-- Наступний запис отримуватиме ID 8
ALTER TABLE students AUTO_INCREMENT = 8;

-- Перевірка даних
SELECT * FROM students ORDER BY id;
