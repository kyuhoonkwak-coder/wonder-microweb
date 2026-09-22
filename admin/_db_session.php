<?php
if (!defined('WONDER_APP')) {
    http_response_code(403);
    exit;
}

// Cloud Run은 인스턴스가 여러 개 뜰 수 있어서 파일 기반 세션이 인스턴스마다 따로 놀아
// 로그인이 유지되지 않습니다. 세션을 DB에 저장해 모든 인스턴스가 공유하도록 합니다.
class WonderDbSessionHandler implements SessionHandlerInterface {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, data, last_access) VALUES (:id, :data, :now)
             ON DUPLICATE KEY UPDATE data = :data2, last_access = :now2'
        );
        $now = time();
        return $stmt->execute([
            ':id' => $id,
            ':data' => $data,
            ':now' => $now,
            ':data2' => $data,
            ':now2' => $now,
        ]);
    }

    public function destroy($id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function gc($max_lifetime): int|false {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_access < :cutoff');
        $stmt->execute([':cutoff' => time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
