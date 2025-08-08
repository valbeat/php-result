<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests;

use PHPUnit\Framework\TestCase;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;
use Valbeat\Result\Result;

class ResultIntegrationTest extends TestCase
{
    public function testDivisionExample(): void
    {
        $divide = function (int $x, int $y): Result {
            if ($y === 0) {
                return new Err('Division by zero');
            }
            return new Ok($x / $y);
        };

        $result1 = $divide(10, 2);
        $this->assertTrue($result1->isOk());
        $this->assertEquals(5.0, $result1->unwrap());

        $result2 = $divide(10, 0);
        $this->assertTrue($result2->isErr());
        $this->assertSame('Division by zero', $result2->unwrapErr());
    }

    public function testParsingExample(): void
    {
        $parseInt = function (string $s): Result {
            if (!is_numeric($s)) {
                return new Err("Invalid number: $s");
            }
            return new Ok((int) $s);
        };

        $result1 = $parseInt('42');
        $this->assertTrue($result1->isOk());
        $this->assertSame(42, $result1->unwrap());

        $result2 = $parseInt('abc');
        $this->assertTrue($result2->isErr());
        $this->assertSame('Invalid number: abc', $result2->unwrapErr());
    }

    public function testChainedOperations(): void
    {
        $parseAndDouble = function (string $s): Result {
            $parseInt = function (string $s): Result {
                if (!is_numeric($s)) {
                    return new Err("Invalid number: $s");
                }
                return new Ok((int) $s);
            };

            return $parseInt($s)
                ->map(fn($x) => $x * 2)
                ->andThen(fn($x) => $x > 100 ? new Err('Too large') : new Ok($x));
        };

        $result1 = $parseAndDouble('20');
        $this->assertTrue($result1->isOk());
        $this->assertSame(40, $result1->unwrap());

        $result2 = $parseAndDouble('60');
        $this->assertTrue($result2->isErr());
        $this->assertSame('Too large', $result2->unwrapErr());

        $result3 = $parseAndDouble('abc');
        $this->assertTrue($result3->isErr());
        $this->assertSame('Invalid number: abc', $result3->unwrapErr());
    }

    public function testFileOperationExample(): void
    {
        $readFile = function (string $path): Result {
            if (!file_exists($path)) {
                return new Err("File not found: $path");
            }
            
            $content = @file_get_contents($path);
            if ($content === false) {
                return new Err("Could not read file: $path");
            }
            
            return new Ok($content);
        };

        $processFile = function (string $path) use ($readFile): Result {
            return $readFile($path)
                ->map(fn($content) => trim($content))
                ->map(fn($content) => strtoupper($content))
                ->mapErr(fn($error) => "Processing failed: $error");
        };

        $result = $processFile('/non/existent/file.txt');
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('Processing failed:', $result->unwrapErr());
    }

    public function testValidationChain(): void
    {
        $validateAge = function (mixed $age): Result {
            if (!is_int($age)) {
                return new Err('Age must be an integer');
            }
            if ($age < 0) {
                return new Err('Age cannot be negative');
            }
            if ($age > 150) {
                return new Err('Age seems unrealistic');
            }
            return new Ok($age);
        };

        $validateEmail = function (string $email): Result {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new Err('Invalid email format');
            }
            return new Ok($email);
        };

        $createUser = function (mixed $age, string $email) use ($validateAge, $validateEmail): Result {
            return $validateAge($age)->andThen(function ($validAge) use ($email, $validateEmail) {
                return $validateEmail($email)->map(function ($validEmail) use ($validAge) {
                    return ['age' => $validAge, 'email' => $validEmail];
                });
            });
        };

        $result1 = $createUser(25, 'user@example.com');
        $this->assertTrue($result1->isOk());
        $this->assertSame(['age' => 25, 'email' => 'user@example.com'], $result1->unwrap());

        $result2 = $createUser(-5, 'user@example.com');
        $this->assertTrue($result2->isErr());
        $this->assertSame('Age cannot be negative', $result2->unwrapErr());

        $result3 = $createUser(25, 'invalid-email');
        $this->assertTrue($result3->isErr());
        $this->assertSame('Invalid email format', $result3->unwrapErr());
    }

    public function testErrorRecovery(): void
    {
        $tryPrimary = function (): Result {
            return new Err('Primary failed');
        };

        $trySecondary = function (): Result {
            return new Ok('Secondary succeeded');
        };

        $result = $tryPrimary()
            ->orElse(fn() => $trySecondary())
            ->map(fn($value) => "Result: $value");

        $this->assertTrue($result->isOk());
        $this->assertSame('Result: Secondary succeeded', $result->unwrap());
    }

    public function testCollectingResults(): void
    {
        $results = [
            new Ok(1),
            new Ok(2),
            new Ok(3),
        ];

        $sum = array_reduce($results, function ($acc, Result $result) {
            if ($acc->isErr()) {
                return $acc;
            }
            return $result->map(fn($x) => $acc->unwrap() + $x);
        }, new Ok(0));

        $this->assertTrue($sum->isOk());
        $this->assertSame(6, $sum->unwrap());

        $resultsWithError = [
            new Ok(1),
            new Err('Error in second'),
            new Ok(3),
        ];

        $sumWithError = array_reduce($resultsWithError, function ($acc, Result $result) {
            if ($acc->isErr()) {
                return $acc;
            }
            if ($result->isErr()) {
                return $result;
            }
            return $result->map(fn($x) => $acc->unwrap() + $x);
        }, new Ok(0));

        $this->assertTrue($sumWithError->isErr());
        $this->assertSame('Error in second', $sumWithError->unwrapErr());
    }

    public function testMatchPattern(): void
    {
        $processValue = function (mixed $value): Result {
            if ($value === null) {
                return new Err('Value is null');
            }
            if (!is_numeric($value)) {
                return new Err('Value is not numeric');
            }
            return new Ok((float) $value);
        };

        $handleResult = function (mixed $value) use ($processValue): string {
            return $processValue($value)->match(
                fn($success) => "Processed value: $success",
                fn($error) => "Error occurred: $error"
            );
        };

        $this->assertSame('Processed value: 42', $handleResult(42));
        $this->assertSame('Processed value: 3.14', $handleResult('3.14'));
        $this->assertSame('Error occurred: Value is null', $handleResult(null));
        $this->assertSame('Error occurred: Value is not numeric', $handleResult('abc'));
    }

    public function testTransactionExample(): void
    {
        $balance = 100;

        $withdraw = function (float $amount) use (&$balance): Result {
            if ($amount <= 0) {
                return new Err('Amount must be positive');
            }
            if ($amount > $balance) {
                return new Err('Insufficient funds');
            }
            $balance -= $amount;
            return new Ok($balance);
        };

        $deposit = function (float $amount) use (&$balance): Result {
            if ($amount <= 0) {
                return new Err('Amount must be positive');
            }
            $balance += $amount;
            return new Ok($balance);
        };

        $transaction = $withdraw(30)
            ->andThen(fn() => $withdraw(20))
            ->andThen(fn() => $deposit(10));

        $this->assertTrue($transaction->isOk());
        $this->assertSame(60.0, $transaction->unwrap());
        $this->assertSame(60.0, $balance);

        $failedTransaction = $withdraw(100)
            ->andThen(fn() => $withdraw(20));

        $this->assertTrue($failedTransaction->isErr());
        $this->assertSame('Insufficient funds', $failedTransaction->unwrapErr());
    }

    public function testApiResponseHandling(): void
    {
        $apiCall = function (string $endpoint): Result {
            $responses = [
                '/users' => ['id' => 1, 'name' => 'John'],
                '/posts' => ['id' => 1, 'title' => 'Hello World'],
            ];

            if (!isset($responses[$endpoint])) {
                return new Err(['code' => 404, 'message' => 'Not Found']);
            }

            return new Ok($responses[$endpoint]);
        };

        $fetchUserWithPosts = function () use ($apiCall): Result {
            return $apiCall('/users')->andThen(function ($user) use ($apiCall) {
                return $apiCall('/posts')->map(function ($posts) use ($user) {
                    return ['user' => $user, 'posts' => $posts];
                });
            });
        };

        $result = $fetchUserWithPosts();
        $this->assertTrue($result->isOk());
        $data = $result->unwrap();
        $this->assertSame('John', $data['user']['name']);
        $this->assertSame('Hello World', $data['posts']['title']);

        $fetchWithError = function () use ($apiCall): Result {
            return $apiCall('/users')->andThen(function ($user) use ($apiCall) {
                return $apiCall('/invalid')->map(function ($data) use ($user) {
                    return ['user' => $user, 'data' => $data];
                });
            });
        };

        $errorResult = $fetchWithError();
        $this->assertTrue($errorResult->isErr());
        $error = $errorResult->unwrapErr();
        $this->assertSame(404, $error['code']);
    }

    public function testComplexTypeHandling(): void
    {
        $processData = function (array $data): Result {
            if (!isset($data['required_field'])) {
                return new Err(new \InvalidArgumentException('Missing required field'));
            }
            
            return new Ok($data);
        };

        $data1 = ['required_field' => 'value', 'optional' => 'data'];
        $result1 = $processData($data1);
        $this->assertTrue($result1->isOk());
        $this->assertSame($data1, $result1->unwrap());

        $data2 = ['optional' => 'data'];
        $result2 = $processData($data2);
        $this->assertTrue($result2->isErr());
        $error = $result2->unwrapErr();
        $this->assertInstanceOf(\InvalidArgumentException::class, $error);
        $this->assertSame('Missing required field', $error->getMessage());
    }

    public function testNullableValues(): void
    {
        $findUser = function (?int $id): Result {
            if ($id === null) {
                return new Err('User ID is required');
            }
            if ($id <= 0) {
                return new Err('Invalid user ID');
            }
            return new Ok(['id' => $id, 'name' => "User $id"]);
        };

        $result1 = $findUser(1);
        $this->assertTrue($result1->isOk());
        
        $result2 = $findUser(null);
        $this->assertTrue($result2->isErr());
        $this->assertSame('User ID is required', $result2->unwrapErr());
        
        $result3 = $findUser(-1);
        $this->assertTrue($result3->isErr());
        $this->assertSame('Invalid user ID', $result3->unwrapErr());
    }

    public function testInspectionForDebugging(): void
    {
        $log = [];
        
        $process = function (string $input) use (&$log): Result {
            $parseInt = function (string $s): Result {
                if (!is_numeric($s)) {
                    return new Err("Invalid number: $s");
                }
                return new Ok((int) $s);
            };

            return $parseInt($input)
                ->inspect(function ($value) use (&$log) {
                    $log[] = "Parsed: $value";
                })
                ->map(fn($x) => $x * 2)
                ->inspect(function ($value) use (&$log) {
                    $log[] = "Doubled: $value";
                })
                ->inspectErr(function ($error) use (&$log) {
                    $log[] = "Error: $error";
                });
        };

        $result1 = $process('5');
        $this->assertTrue($result1->isOk());
        $this->assertSame(10, $result1->unwrap());
        $this->assertSame(['Parsed: 5', 'Doubled: 10'], $log);

        $log = [];
        $result2 = $process('abc');
        $this->assertTrue($result2->isErr());
        $this->assertSame(['Error: Invalid number: abc'], $log);
    }
}