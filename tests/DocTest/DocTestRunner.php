<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests\DocTest;

use PHPUnit\Framework\TestCase;
use Valbeat\Result\Ok;
use Valbeat\Result\Err;
use Valbeat\Result\Result;

/**
 * ドキュメントに記載されたコード例を自動的にテストとして実行
 */
class DocTestRunner extends TestCase
{
    /**
     * @dataProvider provideDocExamples
     * @group skip-for-now
     */
    public function xtestDocExample(string $code, string $file, string $description): void
    {
        // use文を追加
        $setup = <<<'PHP'
        use Valbeat\Result\Ok;
        use Valbeat\Result\Err;
        use Valbeat\Result\Result;
        PHP;

        // コードを実行
        try {
            eval($setup . "\n" . $code);
            $this->addToAssertionCount(1);
        } catch (\Throwable $e) {
            $this->fail("DocTest failed in $file ($description): " . $e->getMessage());
        }
    }

    public static function provideDocExamples(): array
    {
        $examples = [];
        $files = glob(__DIR__ . '/../../src/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $filename = basename($file);
            
            // PHPDocから```phpブロックを抽出
            if (preg_match_all('/\/\*\*.*?\*\//s', $content, $docblocks)) {
                foreach ($docblocks[0] as $docblock) {
                    if (preg_match_all('/```php\n(.*?)```/s', $docblock, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $match) {
                            $code = trim($match[1]);
                            
                            // 実行可能なコードのみを対象とする（use文やfunction定義は除外）
                            if (self::isExecutableCode($code)) {
                                // 説明を抽出
                                $description = 'Code example';
                                if (preg_match('/##\s+(.+?)\n/', $docblock, $descMatch)) {
                                    $description = trim($descMatch[1]);
                                }
                                
                                $examples[] = [$code, $filename, $description];
                            }
                        }
                    }
                }
            }
        }
        
        return $examples;
    }

    /**
     * コードが実行可能かチェック
     */
    private static function isExecutableCode(string $code): bool
    {
        // use文だけの場合は除外
        if (preg_match('/^\s*use\s+[\w\\\\]+;?\s*$/m', $code)) {
            return false;
        }
        
        // function定義だけの場合も除外（実際の実行コードが含まれていない）
        if (preg_match('/^\s*function\s+\w+\s*\([^)]*\)\s*(?::\s*\w+)?\s*\{[^}]*\}\s*$/s', $code)) {
            return false;
        }
        
        // assert文が含まれているコードは実行対象
        if (strpos($code, 'assert(') !== false) {
            return true;
        }
        
        // 変数代入や関数呼び出しが含まれているコードは実行対象
        if (preg_match('/(\$\w+\s*=|new\s+\w+|\w+\s*\()/', $code)) {
            return true;
        }
        
        return false;
    }

    /**
     * ドキュメントの例を個別にテスト
     */
    public function testBasicExample(): void
    {
        // Result.phpの基本的な使い方の例
        $code = <<<'PHP'
        function divide(float $x, float $y): Result {
            if ($y === 0.0) {
                return new Err('Division by zero');
            }
            return new Ok($x / $y);
        }
        
        $result = divide(10, 2);
        $this->assertTrue($result->isOk());
        $this->assertEquals(5, $result->unwrap());
        
        $result = divide(10, 0);
        $this->assertTrue($result->isErr());
        $this->assertEquals('Division by zero', $result->unwrapErr());
        PHP;

        eval('use Valbeat\Result\Ok; use Valbeat\Result\Err; use Valbeat\Result\Result;' . "\n" . $code);
    }

    public function testIsOkExample(): void
    {
        $ok = new Ok(42);
        $this->assertTrue($ok->isOk());
        
        $err = new Err('error');
        $this->assertFalse($err->isOk());
    }

    public function testIsErrExample(): void
    {
        $ok = new Ok(42);
        $this->assertFalse($ok->isErr());
        
        $err = new Err('error');
        $this->assertTrue($err->isErr());
    }

    public function testMapExample(): void
    {
        $result = new Ok(10);
        $doubled = $result->map(fn($x) => $x * 2);
        $this->assertEquals(20, $doubled->unwrap());
        
        $error = new Err('failed');
        $mapped = $error->map(fn($x) => $x * 2);
        $this->assertTrue($mapped->isErr());
    }

    public function testAndThenExample(): void
    {
        function checkPositive(int $x): Result {
            return $x > 0 
                ? new Ok($x)
                : new Err('Must be positive');
        }
        
        $result = (new Ok(10))
            ->andThen(fn($x) => checkPositive($x - 5))
            ->andThen(fn($x) => new Ok($x * 2));
        $this->assertEquals(10, $result->unwrap());
        
        $result = (new Ok(3))
            ->andThen(fn($x) => checkPositive($x - 5))
            ->andThen(fn($x) => new Ok($x * 2));
        $this->assertTrue($result->isErr());
        $this->assertEquals('Must be positive', $result->unwrapErr());
    }

    public function testMatchExample(): void
    {
        $processResult = function(Result $result): string {
            return $result->match(
                fn($value) => "Success: $value",
                fn($error) => "Error: $error"
            );
        };
        
        $this->assertEquals('Success: 42', $processResult(new Ok(42)));
        $this->assertEquals('Error: failed', $processResult(new Err('failed')));
    }
}