<?php
namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\Question;
use App\Services\JsDockerEvaluator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    public function getTest(int $id): JsonResponse
    {
        $test = Test::with([
            'questions' => function ($q) {
                $q->orderBy('position')->orderBy('id')
                  ->select('id','test_id','type','title','prompt','multiple','language','starter','position');
            },
            'questions.options' => function ($q) {
                $q->orderBy('position')->orderBy('id')
                  ->select('id','question_id','text','position');
            },
        ])->find($id);

        if (!$test) {
            return response()->json(['message' => 'Test not found'], 404);
        }

        $questions = $test->questions->map(function ($q) {
            $base = [
                'id'     => $q->id,
                'type'   => $q->type,
                'title'  => $q->title,
                'prompt' => $q->prompt,
            ];

            if ($q->type === 'mcq') {
                $base['multiple'] = (bool) $q->multiple;
                $base['options']  = $q->options
                    ->sortBy(['position','id'])
                    ->values()
                    ->map(fn($opt) => ['id' => $opt->id, 'text' => $opt->text])
                    ->all();
            } elseif ($q->type === 'code') {
                $base['language'] = $q->language;
                $base['starter']  = $q->starter;
            }

            return $base;
        })->values();

        return response()->json([
            'test' => [
                'id'               => $test->id,
                'title'            => $test->title,
                'description'      => $test->description,
                'difficulty'       => $test->difficulty,
                'estimatedMinutes' => (int) $test->estimated_minutes,
            ],
            'questions' => $questions,
        ]);
    }

    
    // POST /api/tests/{id}/evaluate
    public function evaluateCode(int $id, Request $request, JsDockerEvaluator $evaluator): JsonResponse
    {
        $this->validate($request, [
            'questionId' => 'required|integer',
            'language'   => 'required|string|in:javascript',
            'code'       => 'required|string|min:1',
        ]);

        $q = Question::where('test_id', $id)
            ->where('id', $request->integer('questionId'))
            ->where('type', 'code')
            ->select('id','test_id','type','language','tests_json')
            ->first();

        if (!$q) {
            return response()->json(['message' => 'Question not found or not code type'], 404);
        }
        if (!is_array($q->tests_json)) {
            return response()->json(['message' => 'No tests for this question'], 409);
        }

        $result = $evaluator->evaluate($q->tests_json, $request->input('code'));

        return response()->json([
            'passed'   => $result['passed'],
            'output'   => $result['output'],
            'feedback' => $result['feedback'],
            'results'  => $result['results'],
        ]);
    }

      // POST /api/tests/{id}/submit
    public function submitTest(int $id, Request $request, JsDockerEvaluator $evaluator): JsonResponse
    {
        $this->validate($request, [
            'answers'                   => 'required|array|min:1',
            'answers.*.questionId'      => 'required|integer',
            'answers.*.type'            => 'required|string|in:mcq,code',
            // MCQ:
            'answers.*.selected'        => 'required_if:answers.*.type,mcq|array',
            'answers.*.selected.*'      => 'integer',
            // CODE:
            'answers.*.language'        => 'required_if:answers.*.type,code|string|in:javascript',
            'answers.*.code'            => 'required_if:answers.*.type,code|string|min:1',
        ]);

        $answers = collect($request->input('answers', []));

        $questions = Question::with([
            'options:id,question_id,text,position',
            'correctOptions:id',
        ])
        ->where('test_id', $id)
        ->select('id','test_id','type','title','prompt','multiple','language','starter','tests_json','position')
        ->orderBy('position')->orderBy('id')
        ->get();

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'No questions for this test'], 404);
        }

        $qById = $questions->keyBy('id');

        $gradable = $questions->whereIn('type', ['mcq','code']);
        $total = $gradable->count();
        if ($total === 0) {
            return response()->json([
                'scorePercent' => 0,
                'results' => [],
            ]);
        }

        $results = [];
        $correctCount = 0;

        foreach ($gradable as $q) {
            $qid = $q->id;
            $ans = $answers->firstWhere('questionId', $qid);

            if (!$ans) {
                $results[] = ['questionId' => $qid, 'correct' => false, 'feedback' => 'No answer'];
                continue;
            }

            if ($q->type === 'mcq') {
                $optionIds = $q->options->pluck('id')->all();
                $correctIds = $q->correctOptions->pluck('id')->sort()->values()->all();

                $selected = collect($ans['selected'] ?? [])
                    ->filter(fn($v) => is_int($v) || ctype_digit((string)$v))
                    ->map(fn($v) => (int)$v)
                    ->filter(fn($idOpt) => in_array($idOpt, $optionIds, true))
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $isCorrect = false;
                $feedback = null;

                if ($q->multiple) {
                    $isCorrect = ($selected === $correctIds);
                    if (!$isCorrect) {
                        $feedback = 'Wrong selection';
                    }
                } else {
                    $isCorrect = (count($correctIds) === 1 && count($selected) === 1 && $selected[0] === $correctIds[0]);
                    if (!$isCorrect) {
                        $feedback = 'Wrong option';
                    }
                }

                if ($isCorrect) $correctCount++;
                $res = ['questionId' => $qid, 'correct' => $isCorrect];
                if (!$isCorrect && $feedback) $res['feedback'] = $feedback;
                $results[] = $res;
            }
            elseif ($q->type === 'code') {
                $tests = $q->tests_json;
                if (!is_array($tests)) {
                    $results[] = ['questionId' => $qid, 'correct' => false, 'feedback' => 'No tests configured'];
                    continue;
                }

                $eval = $evaluator->evaluate($tests, (string)$ans['code']);
                $ok = (bool)($eval['passed'] ?? false);
                if ($ok) $correctCount++;

                $res = [
                    'questionId' => $qid,
                    'correct'    => $ok,
                ];
                if (!$ok && !empty($eval['feedback'])) {
                    $res['feedback'] = (string)$eval['feedback'];
                }
                $results[] = $res;
            }
        }

        $scorePercent = (int) round(100 * $correctCount / max(1, $total));

        return response()->json([
            'scorePercent' => $scorePercent,
            'results'      => $results,
        ]);
    }

    // GET /api/tests
    public function index(): JsonResponse
    {
        $rows = Test::query()
            ->select('id','title','description','difficulty','estimated_minutes','created_at','updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Test $t) {
                return [
                    'id'               => (int) $t->id,
                    'title'            => (string) $t->title,
                    'description'      => (string) ($t->description ?? ''),
                    'difficulty'       => (string) ($t->difficulty ?? 'Beginner'),
                    'estimatedMinutes' => (int) ($t->estimated_minutes ?? 0),
                    'createdAt'        => $t->created_at?->copy()->utc()->toISOString(),
                    'updatedAt'        => $t->updated_at?->copy()->utc()->toISOString(),
                ];
            })
            ->values();

        return response()->json($rows);
    }
}
