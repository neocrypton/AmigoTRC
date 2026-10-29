<?php
declare(strict_types=1);

namespace Amigo\Core;

use PDO;
use PDOStatement;

/**
 * Dünne PDO-Schicht für MySQL/MariaDB und SQLite.
 *
 * Tabellennamen werden in SQL als {name} geschrieben und mit dem konfigurierten
 * Präfix ersetzt. Werte gehen ausschließlich über gebundene Parameter in die Abfrage.
 */
final class Database
{
    private PDO $pdo;

    private function __construct(PDO $pdo, public readonly string $driver, private readonly string $prefix)
    {
        $this->pdo = $pdo;
    }

    public static function connect(array $cfg): self
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        $driver = $cfg['driver'] ?? 'sqlite';
        $prefix = (string)($cfg['prefix'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]{0,20}$/', $prefix)) {
            throw new \RuntimeException('Ungültiges Tabellen-Präfix.');
        }

        if ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $cfg['host'] ?? 'localhost',
                (int)($cfg['port'] ?? 3306),
                $cfg['name'] ?? ''
            );
            $pdo = new PDO($dsn, (string)($cfg['user'] ?? ''), (string)($cfg['pass'] ?? ''), $options);
            $pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        } elseif ($driver === 'sqlite') {
            $path = (string)($cfg['path'] ?? '');
            if ($path === '') {
                throw new \RuntimeException('Kein SQLite-Pfad konfiguriert.');
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            // Gelöschte Daten werden überschrieben statt nur freigegeben.
            $pdo->exec('PRAGMA secure_delete = ON');
        } else {
            throw new \RuntimeException('Unbekannter Datenbanktreiber: ' . $driver);
        }

        return new self($pdo, $driver, $prefix);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function table(string $name): string
    {
        return $this->quoteIdent($this->prefix . $name);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function quoteIdent(string $ident): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $ident)) {
            throw new \InvalidArgumentException('Ungültiger Bezeichner.');
        }
        return $this->driver === 'mysql' ? "`$ident`" : "\"$ident\"";
    }

    /** Ersetzt {tabelle} durch den gequoteten, präfixierten Tabellennamen. */
    public function sql(string $sql): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => $this->table($m[1]), $sql);
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($this->sql($sql));
        $positional = array_is_list($params);
        foreach ($params as $key => $value) {
            $name = $positional ? $key + 1 : ':' . ltrim((string)$key, ':');
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int)$value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_map([$this, 'quoteIdent'], array_keys($data));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table($table),
            implode(', ', $cols),
            implode(', ', array_fill(0, count($data), '?'))
        );
        $this->run($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    /** UPDATE mit WHERE-Bedingung als Spalte => Wert (UND-verknüpft). */
    public function update(string $table, array $data, array $where): int
    {
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = $this->quoteIdent($col) . ' = ?';
        }
        $cond = [];
        foreach (array_keys($where) as $col) {
            $cond[] = $this->quoteIdent($col) . ' = ?';
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->table($table), implode(', ', $set), implode(' AND ', $cond));
        return $this->run($sql, [...array_values($data), ...array_values($where)])->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $cond = [];
        foreach (array_keys($where) as $col) {
            $cond[] = $this->quoteIdent($col) . ' = ?';
        }
        $sql = sprintf('DELETE FROM %s WHERE %s', $this->table($table), implode(' AND ', $cond));
        return $this->run($sql, array_values($where))->rowCount();
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Erzeugt "?, ?, ?" für IN-Listen. */
    public static function placeholders(int $count): string
    {
        return implode(', ', array_fill(0, max(1, $count), '?'));
    }

    /** Escaped %, _ und ! für LIKE-Suchen; im SQL immer mit "LIKE ? ESCAPE '!'" verwenden. */
    public static function like(string $term): string
    {
        return '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
