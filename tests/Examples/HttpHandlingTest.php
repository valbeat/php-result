<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests\Examples;

use PHPUnit\Framework\TestCase;
use Valbeat\Result\Ok;
use Valbeat\Result\Err;
use Valbeat\Result\Result;

/**
 * HTTPリクエスト/レスポンス処理の実例
 */
class HttpHandlingTest extends TestCase
{
    /**
     * APIレスポンスの処理パターン
     */
    public function testApiResponseHandling(): void
    {
        $apiClient = new class {
            public function get(string $endpoint): Result {
                $responses = [
                    '/api/users/1' => ['status' => 200, 'data' => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']],
                    '/api/users/2' => ['status' => 200, 'data' => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com']],
                    '/api/users/999' => ['status' => 404, 'error' => 'User not found'],
                    '/api/posts' => ['status' => 200, 'data' => [['id' => 1, 'title' => 'First Post']]],
                ];

                if (!isset($responses[$endpoint])) {
                    return new Err(['status' => 404, 'error' => 'Endpoint not found']);
                }

                $response = $responses[$endpoint];
                if ($response['status'] !== 200) {
                    return new Err(['status' => $response['status'], 'error' => $response['error']]);
                }

                return new Ok($response['data']);
            }
        };

        // 成功パターン：ユーザー情報を取得して名前を抽出
        $result = $apiClient->get('/api/users/1')
            ->map(fn($user) => $user['name'])
            ->map(fn($name) => "Welcome, $name!");

        $this->assertTrue($result->isOk());
        $this->assertSame('Welcome, Alice!', $result->unwrap());

        // エラーハンドリング：存在しないユーザー
        $result = $apiClient->get('/api/users/999')
            ->mapErr(fn($error) => "API Error {$error['status']}: {$error['error']}")
            ->unwrapOrElse(fn($errorMsg) => $errorMsg);

        $this->assertSame('API Error 404: User not found', $result);
    }

    /**
     * 複数のAPIコールをチェーンする例
     */
    public function testChainedApiCalls(): void
    {
        $api = new class {
            public function fetchUser(int $id): Result {
                if ($id === 1) {
                    return new Ok(['id' => 1, 'name' => 'Alice', 'teamId' => 10]);
                }
                return new Err('User not found');
            }

            public function fetchTeam(int $teamId): Result {
                if ($teamId === 10) {
                    return new Ok(['id' => 10, 'name' => 'Development Team']);
                }
                return new Err('Team not found');
            }

            public function fetchProjects(int $teamId): Result {
                if ($teamId === 10) {
                    return new Ok([
                        ['id' => 1, 'name' => 'Project Alpha'],
                        ['id' => 2, 'name' => 'Project Beta'],
                    ]);
                }
                return new Err('No projects found');
            }
        };

        // ユーザー -> チーム -> プロジェクトを順番に取得
        $result = $api->fetchUser(1)
            ->andThen(function ($user) use ($api) {
                return $api->fetchTeam($user['teamId'])
                    ->map(function ($team) use ($user) {
                        return ['user' => $user, 'team' => $team];
                    });
            })
            ->andThen(function ($data) use ($api) {
                return $api->fetchProjects($data['team']['id'])
                    ->map(function ($projects) use ($data) {
                        return array_merge($data, ['projects' => $projects]);
                    });
            });

        $this->assertTrue($result->isOk());
        $data = $result->unwrap();
        $this->assertSame('Alice', $data['user']['name']);
        $this->assertSame('Development Team', $data['team']['name']);
        $this->assertCount(2, $data['projects']);
    }

    /**
     * リトライロジックの実装例
     */
    public function testRetryLogic(): void
    {
        $httpClient = new class {
            private int $attempts = 0;

            public function request(string $url): Result {
                $this->attempts++;
                
                // 3回目で成功するシミュレーション
                if ($this->attempts < 3) {
                    return new Err(['code' => 'TIMEOUT', 'attempt' => $this->attempts]);
                }
                
                return new Ok(['status' => 200, 'body' => 'Success']);
            }

            public function reset(): void {
                $this->attempts = 0;
            }
        };

        $retry = function (callable $operation, int $maxAttempts = 3): Result {
            $lastError = null;
            
            for ($i = 0; $i < $maxAttempts; $i++) {
                $result = $operation();
                if ($result->isOk()) {
                    return $result;
                }
                $lastError = $result->unwrapErr();
            }
            
            return new Err(['error' => 'Max attempts reached', 'lastError' => $lastError]);
        };

        // リトライが成功するケース
        $result = $retry(fn() => $httpClient->request('https://api.example.com/data'));
        $this->assertTrue($result->isOk());
        $this->assertSame('Success', $result->unwrap()['body']);

        // リトライが失敗するケース（最大試行回数を1に設定）
        $httpClient->reset();
        $result = $retry(fn() => $httpClient->request('https://api.example.com/data'), 1);
        $this->assertTrue($result->isErr());
        $error = $result->unwrapErr();
        $this->assertSame('Max attempts reached', $error['error']);
    }

    /**
     * レート制限の処理例
     */
    public function testRateLimitHandling(): void
    {
        $rateLimiter = new class {
            private int $requests = 0;
            private int $limit = 3;

            public function checkLimit(): Result {
                if ($this->requests >= $this->limit) {
                    return new Err(['code' => 'RATE_LIMIT', 'retryAfter' => 60]);
                }
                $this->requests++;
                return new Ok(true);
            }

            public function reset(): void {
                $this->requests = 0;
            }
        };

        $makeRequest = function () use ($rateLimiter): Result {
            return $rateLimiter->checkLimit()
                ->andThen(fn() => new Ok(['data' => 'Response data']));
        };

        // 制限内のリクエスト
        for ($i = 0; $i < 3; $i++) {
            $result = $makeRequest();
            $this->assertTrue($result->isOk());
        }

        // 制限を超えたリクエスト
        $result = $makeRequest();
        $this->assertTrue($result->isErr());
        $error = $result->unwrapErr();
        $this->assertSame('RATE_LIMIT', $error['code']);
        $this->assertSame(60, $error['retryAfter']);
    }

    /**
     * レスポンスのパースとバリデーション
     */
    public function testResponseParsingAndValidation(): void
    {
        $parseJson = function (string $json): Result {
            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new Err('Invalid JSON: ' . json_last_error_msg());
            }
            return new Ok($data);
        };

        $validateUser = function (array $data): Result {
            $required = ['id', 'name', 'email'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return new Err("Missing required field: $field");
                }
            }
            
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return new Err("Invalid email format: {$data['email']}");
            }
            
            return new Ok($data);
        };

        // 正常なJSONレスポンス
        $response = '{"id": 1, "name": "Alice", "email": "alice@example.com"}';
        $result = $parseJson($response)
            ->andThen($validateUser)
            ->map(fn($user) => "User {$user['name']} validated successfully");

        $this->assertTrue($result->isOk());
        $this->assertSame('User Alice validated successfully', $result->unwrap());

        // 不正なJSON
        $response = '{"id": 1, "name": "Bob"'; // 閉じ括弧なし
        $result = $parseJson($response)->andThen($validateUser);
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('Invalid JSON', $result->unwrapErr());

        // バリデーションエラー（emailフィールドなし）
        $response = '{"id": 2, "name": "Charlie"}';
        $result = $parseJson($response)->andThen($validateUser);
        $this->assertTrue($result->isErr());
        $this->assertSame('Missing required field: email', $result->unwrapErr());

        // バリデーションエラー（不正なemail）
        $response = '{"id": 3, "name": "Dave", "email": "not-an-email"}';
        $result = $parseJson($response)->andThen($validateUser);
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('Invalid email format', $result->unwrapErr());
    }
}