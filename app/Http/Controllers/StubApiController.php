<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StubApiController extends Controller
{

    // GET /api/progress/summary
    public function progressSummary(): JsonResponse
    {
        return response()->json([
            'completedTests' => 18,
            'streakDays'     => 7,
            'avgScore'       => 82,
            'totalMinutes'   => 1240,
        ]);
    }

    // GET /api/progress/daily?range=30d
    public function progressDaily(Request $request): JsonResponse
    {
        // игнорируем range и всегда отдаём одно и то же
        return response()->json([
            'points' => [
                ['date' => '2025-07-13', 'score' => 65],
                ['date' => '2025-07-14', 'score' => 80],
                ['date' => '2025-07-15', 'score' => 74],
                ['date' => '2025-07-16', 'score' => 86],
                ['date' => '2025-07-17', 'score' => 79],
            ],
        ]);
    }

    // GET /api/progress/language-distribution
    public function languageDistribution(): JsonResponse
    {
        return response()->json([
            'items' => [
                ['language' => 'JavaScript', 'percent' => 40],
                ['language' => 'Python',     'percent' => 30],
                ['language' => 'C#',         'percent' => 20],
                ['language' => 'Rust',       'percent' => 10],
            ],
        ]);
    }

    // GET /api/tests/recommended?limit=6
    public function testsRecommended(Request $request): JsonResponse
    {
        $all = [
            ['id' => 101, 'title' => 'Async/Await Deep Dive',  'topic' => 'JavaScript', 'difficulty' => 'Intermediate'],
            ['id' => 202, 'title' => 'Ownership & Borrowing',  'topic' => 'Rust',       'difficulty' => 'Advanced'],
            ['id' => 303, 'title' => 'Generics Explained',     'topic' => 'TypeScript', 'difficulty' => 'Beginner'],
            ['id' => 404, 'title' => 'LINQ Fundamentals',      'topic' => 'C#',         'difficulty' => 'Intermediate'],
            ['id' => 505, 'title' => 'Pandas Tricks',          'topic' => 'Python',     'difficulty' => 'Intermediate'],
            ['id' => 606, 'title' => 'Error Handling Patterns','topic' => 'Go',         'difficulty' => 'Advanced'],
        ];
        $limit = (int) $request->query('limit', count($all));
        return response()->json(['items' => array_slice($all, 0, max(0, $limit))]);
    }
}
