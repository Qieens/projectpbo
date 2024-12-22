<?php
function getDatabaseConnection() {
    try {
        $dsn = 'mysql:host=localhost;dbname=karyawan_db';
        $username = 'root';
        $password = '';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        echo "Koneksi gagal: " . $e->getMessage() . "\n";
        exit;
    }
}