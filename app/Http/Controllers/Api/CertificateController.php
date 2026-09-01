<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $certificates = Certificate::whereNull('revoked_at')->where('user_id', $request->user()->id)
            ->with(['course', 'user'])->latest('issued_at')->get()->map(fn ($certificate) => $this->payload($certificate));

        return response()->json(['data' => $certificates]);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $certificate = Certificate::whereNull('revoked_at')->where('user_id', $request->user()->id)->where('course_id', $course->id)->with(['course', 'user'])->firstOrFail();

        return response()->json($this->payload($certificate));
    }

    public function verify(string $publicId): JsonResponse
    {
        $certificate = Certificate::where('public_id', $publicId)->with(['course', 'user'])->first();
        if (! $certificate) {
            return response()->json(['message' => 'Certificate not found.', 'status' => 'not_found'], 404);
        }
        if ($certificate->revoked_at) {
            return response()->json([
                'message' => 'This certificate has been revoked.',
                'status' => 'revoked',
                'revokedAt' => $certificate->revoked_at->toIso8601String(),
            ], 410);
        }

        return response()->json($this->payload($certificate));
    }

    private function payload(Certificate $certificate): array
    {
        return ['valid' => true, 'status' => 'verified', 'id' => $certificate->public_id, 'student' => $certificate->user->name, 'courseId' => $certificate->course_id, 'course' => $certificate->course->title, 'courseEn' => $certificate->course->title_en, 'instructor' => $certificate->course->instructor, 'instructorEn' => $certificate->course->instructor_en, 'issuedAt' => $certificate->issued_at->toIso8601String(), 'verificationUrl' => config('app.frontend_url').'/certificates/verify/'.$certificate->public_id];
    }
}
