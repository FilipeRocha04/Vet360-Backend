<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ChatHistoryController;
use App\Http\Controllers\FlashcardAIController;
use App\Http\Controllers\CaseAIController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\IAClinicalCaseController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\StudyTrailController;
use App\Http\Controllers\StudyPlanController;
use App\Http\Controllers\TestController;

// Handle OPTIONS requests for CORS
Route::options('/{any}', function (Request $request) {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');
})->where('any', '.*');

// ROTAS PÚBLICAS
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Backend está funcionando!',
        'timestamp' => now()
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/reset', [PasswordResetController::class, 'reset']);

// Flashcards e outros AI Services
Route::post('/generate-flashcards', [FlashcardAIController::class, 'generate']);
Route::post('/generate-case', [CaseAIController::class, 'generate']);
Route::post('/generate-quiz', [QuizController::class, 'generate']);
Route::post('/generate-clinical-case', [ClinicalCaseController::class, 'generate']);
Route::post('/generate-prescription', [PrescriptionController::class, 'generate']);
Route::post('/generate-study-trail', [StudyTrailController::class, 'generate']);
Route::post('/generate-study-plan', [StudyPlanController::class, 'generateStudyPlan']);
Route::post('/generate-study-plan-pdf', [StudyPlanController::class, 'generateStudyPlanPDF']);
Route::post('/generate-clinical-case', [IAClinicalCaseController::class, 'generate']);

// Chat Histories (CRUD)
Route::post('/chat-histories', [ChatHistoryController::class, 'store']);
Route::get('/chat-histories/{userId}', [ChatHistoryController::class, 'index']);
Route::get('/chat-histories/{id}/show', [ChatHistoryController::class, 'show']);  // Opcional
Route::put('/chat-histories/{id}', [ChatHistoryController::class, 'update']);
Route::delete('/chat-histories/{id}', [ChatHistoryController::class, 'destroy']);

// ROTAS PROTEGIDAS (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
