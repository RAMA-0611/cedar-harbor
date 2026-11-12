<?php

/**
 * Espera a que PostgreSQL acepte conexiones (no basta con que el puerto esté abierto).
 * Usa el NOMBRE DEL SERVICIO (DB_HOST=db), nunca 127.0.0.1: dentro del contenedor "localhost" es el propio contenedor.
 */
$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_DATABASE') ?: 'rotor';
$user = getenv('DB_USERNAME') ?: 'rotor';
$pass = getenv('DB_PASSWORD') ?: '';
$maxSeconds = (int) (getenv('DB_WAIT_SECONDS') ?: 90);

$deadline = time() + $maxSeconds;
$last = '';
while (time() < $deadline) {
    try {
        new PDO("pgsql:host={$host};port={$port};dbname={$name}", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
        fwrite(STDOUT, "[rotor] Base de datos lista en {$host}:{$port}\n");
        exit(0);
    } catch (Throwable $e) {
        $last = $e->getMessage();
        fwrite(STDOUT, "[rotor] Esperando a la base de datos ({$host}:{$port})…\n");
        sleep(2);
    }
}
fwrite(STDERR, "[rotor] La base de datos no respondió en {$maxSeconds}s: {$last}\n");
exit(1);
