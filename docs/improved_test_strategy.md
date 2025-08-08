# PHP Result型ライブラリのテスト戦略改善案

## Rustのアプローチから学ぶ

### 1. ドキュメントテストの導入

PHPでもdoctestに相当する仕組みを作れます：

```php
/**
 * 結果が成功（Ok）の場合に true を返します.
 * 
 * ## Examples
 * 
 * ```php
 * $ok = new Ok(42);
 * assert($ok->isOk() === true);
 * 
 * $err = new Err('error');
 * assert($err->isOk() === false);
 * ```
 * 
 * @phpstan-assert-if-true Ok<T> $this
 * @return bool
 */
public function isOk(): bool;
```

### 2. テストの構成

```
tests/
├── Unit/           # 個別メソッドの単体テスト
│   ├── OkTest.php
│   └── ErrTest.php
├── Integration/    # 複数機能の組み合わせテスト
│   └── ResultIntegrationTest.php
├── Examples/       # ドキュメントの例を実行可能なテストに
│   ├── BasicUsageTest.php
│   ├── ErrorHandlingTest.php
│   └── RealWorldExamplesTest.php
└── DocTest/        # ドキュメントから抽出したテスト
    └── ExtractedExamplesTest.php
```

### 3. 実装提案

#### A. DocTestランナーの作成

```php
<?php

namespace Valbeat\Result\Tests\DocTest;

use PHPUnit\Framework\TestCase;

class DocTestRunner extends TestCase
{
    /**
     * @dataProvider provideDocExamples
     */
    public function testDocExample(string $code, string $file, int $line): void
    {
        // コード例を実行して検証
        eval($code);
        $this->addToAssertionCount(1);
    }
    
    public static function provideDocExamples(): array
    {
        $examples = [];
        $files = glob(__DIR__ . '/../../src/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // PHPDocから```phpブロックを抽出
            if (preg_match_all('/```php\n(.*?)```/s', $content, $matches)) {
                foreach ($matches[1] as $i => $code) {
                    $examples[] = [$code, $file, $i];
                }
            }
        }
        
        return $examples;
    }
}
```

#### B. 実世界の使用例テスト

```php
<?php

namespace Valbeat\Result\Tests\Examples;

use PHPUnit\Framework\TestCase;
use Valbeat\Result\Ok;
use Valbeat\Result\Err;
use Valbeat\Result\Result;

class RealWorldExamplesTest extends TestCase
{
    /**
     * HTTPレスポンスの処理例
     */
    public function testHttpResponseHandling(): void
    {
        $fetchUser = function(int $id): Result {
            // 実際のAPIコールのシミュレーション
            if ($id <= 0) {
                return new Err(['code' => 400, 'message' => 'Invalid ID']);
            }
            if ($id === 404) {
                return new Err(['code' => 404, 'message' => 'User not found']);
            }
            return new Ok(['id' => $id, 'name' => "User $id"]);
        };
        
        // 成功ケース
        $result = $fetchUser(1)
            ->map(fn($user) => $user['name'])
            ->mapErr(fn($error) => "Error {$error['code']}: {$error['message']}");
        
        $this->assertTrue($result->isOk());
        $this->assertSame('User 1', $result->unwrap());
        
        // エラーケース
        $result = $fetchUser(404)
            ->map(fn($user) => $user['name'])
            ->mapErr(fn($error) => "Error {$error['code']}: {$error['message']}");
        
        $this->assertTrue($result->isErr());
        $this->assertSame('Error 404: User not found', $result->unwrapErr());
    }
    
    /**
     * データベーストランザクション例
     */
    public function testDatabaseTransaction(): void
    {
        $db = new class {
            private array $data = [];
            private bool $inTransaction = false;
            
            public function beginTransaction(): Result {
                if ($this->inTransaction) {
                    return new Err('Already in transaction');
                }
                $this->inTransaction = true;
                return new Ok(null);
            }
            
            public function insert(string $table, array $data): Result {
                if (!$this->inTransaction) {
                    return new Err('No active transaction');
                }
                $this->data[$table][] = $data;
                return new Ok(count($this->data[$table]));
            }
            
            public function commit(): Result {
                if (!$this->inTransaction) {
                    return new Err('No active transaction');
                }
                $this->inTransaction = false;
                return new Ok(true);
            }
            
            public function rollback(): Result {
                $this->data = [];
                $this->inTransaction = false;
                return new Ok(true);
            }
        };
        
        // トランザクションの成功パターン
        $result = $db->beginTransaction()
            ->andThen(fn() => $db->insert('users', ['name' => 'Alice']))
            ->andThen(fn() => $db->insert('users', ['name' => 'Bob']))
            ->andThen(fn() => $db->commit());
        
        $this->assertTrue($result->isOk());
        
        // エラー時のロールバック
        $result = $db->beginTransaction()
            ->andThen(fn() => $db->insert('users', ['name' => 'Charlie']))
            ->andThen(fn() => new Err('Validation failed'))
            ->orElse(fn($error) => $db->rollback()->map(fn() => $error));
        
        $this->assertTrue($result->isOk());
        $this->assertSame('Validation failed', $result->unwrap());
    }
    
    /**
     * バリデーションチェーン例
     */
    public function testValidationChain(): void
    {
        $validateEmail = function(string $email): Result {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new Err("Invalid email format: $email");
            }
            return new Ok($email);
        };
        
        $validateDomain = function(string $email): Result {
            $domain = explode('@', $email)[1] ?? '';
            if (!checkdnsrr($domain, 'MX')) {
                return new Err("Invalid domain: $domain");
            }
            return new Ok($email);
        };
        
        $normalizeEmail = function(string $email): Result {
            return new Ok(strtolower(trim($email)));
        };
        
        // 正常なメールアドレス
        $result = $normalizeEmail('User@Example.COM')
            ->andThen($validateEmail)
            ->map(fn($email) => ['email' => $email, 'verified' => false]);
        
        $this->assertTrue($result->isOk());
        $this->assertSame('user@example.com', $result->unwrap()['email']);
        
        // 不正なメールアドレス
        $result = $normalizeEmail('invalid-email')
            ->andThen($validateEmail)
            ->andThen($validateDomain);
        
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('Invalid email format', $result->unwrapErr());
    }
    
    /**
     * ファイル操作の例
     */
    public function testFileOperations(): void
    {
        $readConfig = function(string $path): Result {
            if (!file_exists($path)) {
                return new Err("File not found: $path");
            }
            
            $content = @file_get_contents($path);
            if ($content === false) {
                return new Err("Cannot read file: $path");
            }
            
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new Err("Invalid JSON: " . json_last_error_msg());
            }
            
            return new Ok($data);
        };
        
        $validateConfig = function(array $config): Result {
            if (!isset($config['version'])) {
                return new Err('Missing version field');
            }
            if (!isset($config['settings'])) {
                return new Err('Missing settings field');
            }
            return new Ok($config);
        };
        
        // テスト用の一時ファイルを作成
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, json_encode([
            'version' => '1.0',
            'settings' => ['debug' => true]
        ]));
        
        $result = $readConfig($tempFile)
            ->andThen($validateConfig)
            ->map(fn($config) => $config['version']);
        
        $this->assertTrue($result->isOk());
        $this->assertSame('1.0', $result->unwrap());
        
        unlink($tempFile);
        
        // 存在しないファイル
        $result = $readConfig('/non/existent/file.json')
            ->andThen($validateConfig);
        
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('File not found', $result->unwrapErr());
    }
}
```

### 4. カバレッジの測定

```bash
# カバレッジレポートの生成
docker run --rm -v "$(pwd)":/app php-result-test \
    ./vendor/bin/phpunit --coverage-html coverage

# メトリクスの確認
docker run --rm -v "$(pwd)":/app php-result-test \
    ./vendor/bin/phpunit --coverage-text
```

### 5. CI/CDでの自動テスト

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.3', '8.4']
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install
      
      - name: Run tests
        run: composer test
      
      - name: Run static analysis
        run: composer phpstan
      
      - name: Check code style
        run: composer cs-check
      
      - name: Generate coverage report
        run: ./vendor/bin/phpunit --coverage-clover coverage.xml
      
      - name: Upload coverage
        uses: codecov/codecov-action@v2
```

## まとめ

Rustのアプローチから学んだポイント：

1. **ドキュメントが生きたテスト** - 例示コードを実際に実行して検証
2. **階層的なテスト構成** - 単体テスト、統合テスト、実例テスト
3. **実用的な使用例** - 実際のユースケースをテストで示す
4. **網羅的なカバレッジ** - すべての公開APIをテスト

これらを取り入れることで、PHPのResult型ライブラリもより堅牢で信頼性の高いものになります。