<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests\Examples;

use PHPUnit\Framework\TestCase;
use Valbeat\Result\Ok;
use Valbeat\Result\Err;
use Valbeat\Result\Result;

/**
 * データベース操作の実例
 */
class DatabaseOperationsTest extends TestCase
{
    /**
     * トランザクション処理の例
     */
    public function testDatabaseTransaction(): void
    {
        $db = $this->createMockDatabase();

        // 成功するトランザクション
        $result = $db->beginTransaction()
            ->andThen(fn() => $db->insert('users', ['name' => 'Alice', 'email' => 'alice@example.com']))
            ->andThen(fn($userId) => $db->insert('profiles', ['user_id' => $userId, 'bio' => 'Developer']))
            ->andThen(fn($profileId) => $db->commit()->map(fn() => $profileId));

        $this->assertTrue($result->isOk());
        $this->assertIsInt($result->unwrap());

        // 失敗してロールバックするトランザクション
        $db->reset();
        $result = $db->beginTransaction()
            ->andThen(fn() => $db->insert('users', ['name' => 'Bob', 'email' => 'bob@example.com']))
            ->andThen(fn() => new Err('Validation failed: duplicate email'))
            ->orElse(function ($error) use ($db) {
                return $db->rollback()->map(fn() => "Rolled back: $error");
            });

        $this->assertTrue($result->isOk());
        $this->assertSame('Rolled back: Validation failed: duplicate email', $result->unwrap());
    }

    /**
     * データベースクエリの連鎖処理
     */
    public function testQueryChaining(): void
    {
        $db = $this->createMockDatabase();

        // ユーザーを検索して関連データを取得
        $getUserWithPosts = function (int $userId) use ($db): Result {
            return $db->findById('users', $userId)
                ->andThen(function ($user) use ($db) {
                    return $db->findAll('posts', ['user_id' => $user['id']])
                        ->map(function ($posts) use ($user) {
                            return array_merge($user, ['posts' => $posts]);
                        });
                });
        };

        $result = $getUserWithPosts(1);
        $this->assertTrue($result->isOk());
        $data = $result->unwrap();
        $this->assertSame('Alice', $data['name']);
        $this->assertCount(2, $data['posts']);

        // 存在しないユーザー
        $result = $getUserWithPosts(999);
        $this->assertTrue($result->isErr());
        $this->assertSame('Record not found in users with id: 999', $result->unwrapErr());
    }

    /**
     * バッチ処理の例
     */
    public function testBatchProcessing(): void
    {
        $db = $this->createMockDatabase();

        $batchInsert = function (array $records) use ($db): Result {
            $results = [];
            
            foreach ($records as $record) {
                $result = $db->insert('products', $record);
                if ($result->isErr()) {
                    return new Err("Batch failed at record: " . json_encode($record));
                }
                $results[] = $result->unwrap();
            }
            
            return new Ok($results);
        };

        // 成功するバッチ処理
        $products = [
            ['name' => 'Product A', 'price' => 100],
            ['name' => 'Product B', 'price' => 200],
            ['name' => 'Product C', 'price' => 300],
        ];

        $result = $batchInsert($products);
        $this->assertTrue($result->isOk());
        $this->assertCount(3, $result->unwrap());

        // 失敗するバッチ処理（不正なデータを含む）
        $invalidProducts = [
            ['name' => 'Product D', 'price' => 400],
            ['name' => '', 'price' => 500], // 名前が空
            ['name' => 'Product F', 'price' => 600],
        ];

        $result = $batchInsert($invalidProducts);
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('Batch failed at record', $result->unwrapErr());
    }

    /**
     * マイグレーション処理の例
     */
    public function testMigrationHandling(): void
    {
        $migrator = new class {
            private array $migrations = [];
            private array $applied = [];

            public function register(string $name, callable $up, callable $down): void {
                $this->migrations[$name] = ['up' => $up, 'down' => $down];
            }

            public function up(string $name): Result {
                if (!isset($this->migrations[$name])) {
                    return new Err("Migration not found: $name");
                }
                
                if (in_array($name, $this->applied)) {
                    return new Err("Migration already applied: $name");
                }

                $result = ($this->migrations[$name]['up'])();
                if ($result->isOk()) {
                    $this->applied[] = $name;
                }
                
                return $result;
            }

            public function down(string $name): Result {
                if (!isset($this->migrations[$name])) {
                    return new Err("Migration not found: $name");
                }
                
                if (!in_array($name, $this->applied)) {
                    return new Err("Migration not applied: $name");
                }

                $result = ($this->migrations[$name]['down'])();
                if ($result->isOk()) {
                    $this->applied = array_diff($this->applied, [$name]);
                }
                
                return $result;
            }

            public function runAll(): Result {
                foreach (array_keys($this->migrations) as $name) {
                    if (!in_array($name, $this->applied)) {
                        $result = $this->up($name);
                        if ($result->isErr()) {
                            return $result;
                        }
                    }
                }
                return new Ok('All migrations applied');
            }
        };

        // マイグレーションを登録
        $migrator->register(
            '001_create_users',
            fn() => new Ok('Created users table'),
            fn() => new Ok('Dropped users table')
        );

        $migrator->register(
            '002_create_posts',
            fn() => new Ok('Created posts table'),
            fn() => new Ok('Dropped posts table')
        );

        $migrator->register(
            '003_add_indexes',
            fn() => new Err('Failed to add index: duplicate key'),
            fn() => new Ok('Removed indexes')
        );

        // すべてのマイグレーションを実行（途中で失敗）
        $result = $migrator->runAll();
        $this->assertTrue($result->isErr());
        $this->assertSame('Failed to add index: duplicate key', $result->unwrapErr());

        // 個別にマイグレーションを実行
        $result = $migrator->down('002_create_posts')
            ->andThen(fn() => $migrator->down('001_create_users'));
        $this->assertTrue($result->isOk());
    }

    /**
     * コネクションプールの管理例
     */
    public function testConnectionPoolManagement(): void
    {
        $pool = new class {
            private array $connections = [];
            private int $maxConnections = 3;
            private int $activeCount = 0;

            public function getConnection(): Result {
                if ($this->activeCount >= $this->maxConnections) {
                    return new Err('Connection pool exhausted');
                }

                $this->activeCount++;
                $connectionId = uniqid('conn_');
                $this->connections[$connectionId] = true;

                return new Ok($connectionId);
            }

            public function releaseConnection(string $connectionId): Result {
                if (!isset($this->connections[$connectionId])) {
                    return new Err("Invalid connection ID: $connectionId");
                }

                unset($this->connections[$connectionId]);
                $this->activeCount--;

                return new Ok(true);
            }

            public function withConnection(callable $operation): Result {
                return $this->getConnection()
                    ->andThen(function ($connectionId) use ($operation) {
                        $result = $operation($connectionId);
                        $this->releaseConnection($connectionId);
                        return $result;
                    });
            }
        };

        // コネクションを使った処理
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $pool->withConnection(function ($connId) {
                // データベース操作のシミュレーション
                return new Ok("Processed with connection: $connId");
            });
        }

        foreach ($results as $result) {
            $this->assertTrue($result->isOk());
            $this->assertStringContainsString('Processed with connection', $result->unwrap());
        }

        // プール枯渇のテスト
        $connections = [];
        for ($i = 0; $i < 3; $i++) {
            $connections[] = $pool->getConnection();
        }

        $result = $pool->getConnection();
        $this->assertTrue($result->isErr());
        $this->assertSame('Connection pool exhausted', $result->unwrapErr());

        // コネクションを解放
        foreach ($connections as $conn) {
            if ($conn->isOk()) {
                $pool->releaseConnection($conn->unwrap());
            }
        }
    }

    private function createMockDatabase(): object
    {
        return new class {
            private array $data = [
                'users' => [
                    1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                    2 => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
                ],
                'posts' => [
                    1 => ['id' => 1, 'user_id' => 1, 'title' => 'First Post'],
                    2 => ['id' => 2, 'user_id' => 1, 'title' => 'Second Post'],
                    3 => ['id' => 3, 'user_id' => 2, 'title' => 'Bob\'s Post'],
                ],
                'profiles' => [],
                'products' => [],
            ];
            private bool $inTransaction = false;
            private array $transactionData = [];
            private int $nextId = 100;

            public function beginTransaction(): Result {
                if ($this->inTransaction) {
                    return new Err('Already in transaction');
                }
                $this->inTransaction = true;
                $this->transactionData = [];
                return new Ok(true);
            }

            public function commit(): Result {
                if (!$this->inTransaction) {
                    return new Err('No active transaction');
                }
                
                foreach ($this->transactionData as $table => $records) {
                    foreach ($records as $id => $record) {
                        $this->data[$table][$id] = $record;
                    }
                }
                
                $this->inTransaction = false;
                $this->transactionData = [];
                return new Ok(true);
            }

            public function rollback(): Result {
                if (!$this->inTransaction) {
                    return new Err('No active transaction');
                }
                
                $this->inTransaction = false;
                $this->transactionData = [];
                return new Ok(true);
            }

            public function insert(string $table, array $record): Result {
                if (isset($record['name']) && empty($record['name'])) {
                    return new Err('Name cannot be empty');
                }

                $id = $this->nextId++;
                $record['id'] = $id;

                if ($this->inTransaction) {
                    $this->transactionData[$table][$id] = $record;
                } else {
                    $this->data[$table][$id] = $record;
                }

                return new Ok($id);
            }

            public function findById(string $table, int $id): Result {
                if (isset($this->data[$table][$id])) {
                    return new Ok($this->data[$table][$id]);
                }
                return new Err("Record not found in $table with id: $id");
            }

            public function findAll(string $table, array $conditions = []): Result {
                $results = [];
                
                foreach ($this->data[$table] as $record) {
                    $match = true;
                    foreach ($conditions as $key => $value) {
                        if (!isset($record[$key]) || $record[$key] !== $value) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        $results[] = $record;
                    }
                }

                return new Ok($results);
            }

            public function reset(): void {
                $this->inTransaction = false;
                $this->transactionData = [];
            }
        };
    }
}