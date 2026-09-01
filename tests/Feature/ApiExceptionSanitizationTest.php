<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class ApiExceptionSanitizationTest extends TestCase
{
    public function test_database_exceptions_return_a_safe_api_message_without_sql_details(): void
    {
        $exception = new QueryException(
            'mysql',
            'update student_onboarding_profiles set placement_answers = ?',
            ['sensitive-ciphertext'],
            new RuntimeException('SQLSTATE[23000]: secret database constraint and path'),
        );
        $request = Request::create('/api/student/onboarding/placement', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $this->app->make(ExceptionHandler::class)->render($request, $exception);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'The service could not save your changes. Please try again.'],
            json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('sensitive-ciphertext', $response->getContent());
    }
}
