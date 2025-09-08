<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\Question;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminTestsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $rules = [
            'test'                        => 'required|array',
            'test.title'                  => 'required|string|max:255',
            'test.description'            => 'nullable|string',
            'test.difficulty'             => 'required|string|in:Beginner,Intermediate,Advanced',
            'test.estimatedMinutes'       => 'required|integer|min:0',

            'questions'                   => 'required|array|min:1',
            'questions.*.type'            => 'required|string|in:mcq,code',
            'questions.*.title'           => 'required|string|max:255',
            'questions.*.prompt'          => 'nullable|string',

            // MCQ
            'questions.*.multiple'        => 'sometimes|boolean',
            'questions.*.options'         => 'required_if:questions.*.type,mcq|array|min:2',
            'questions.*.options.*'       => 'required_if:questions.*.type,mcq|string|min:1',
            'questions.*.correct'         => 'required_if:questions.*.type,mcq|array|min:1',
            'questions.*.correct.*'       => 'integer|min:0',

            // CODE 
            'questions.*.language'        => 'required_if:questions.*.type,code|string|max:64',
            'questions.*.starter'         => 'nullable|string',
            'questions.*.tests'           => 'required_if:questions.*.type,code|array',
            'questions.*.tests.type'      => 'required_if:questions.*.type,code|in:io',
            'questions.*.tests.function'  => 'required_if:questions.*.type,code|string|min:1',
            'questions.*.tests.equality'  => 'nullable|in:deep,strict',
            'questions.*.tests.timeoutMs' => 'nullable|integer|min:1|max:2000',
            'questions.*.tests.cases'     => 'required_if:questions.*.type,code|array|min:1',
            'questions.*.tests.cases.*'   => 'array', 
        ];

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($v) use ($request) {
            $qs = $request->input('questions', []);
            foreach ($qs as $i => $q) {
                if (($q['type'] ?? null) === 'mcq') {
                    $options = $q['options'] ?? [];
                    $correct = $q['correct'] ?? [];
                    $multiple = (bool)($q['multiple'] ?? false);

                    $maxIdx = count($options) - 1;
                    foreach ($correct as $idx) {
                        if (!is_int($idx) || $idx < 0 || $idx > $maxIdx) {
                            $v->errors()->add("questions.$i.correct", "Correct index $idx out of range 0..$maxIdx");
                        }
                    }

                    if (count($correct) !== count(array_unique($correct))) {
                        $v->errors()->add("questions.$i.correct", "Correct indices contain duplicates");
                    }

                    if (!$multiple && count($correct) !== 1) {
                        $v->errors()->add("questions.$i.correct", "Single-choice must have exactly 1 correct option");
                    }
                }

                if (($q['type'] ?? null) === 'code') {
                    $tests = $q['tests'] ?? null;
                    if (is_array($tests) && isset($tests['cases']) && is_array($tests['cases'])) {
                        foreach ($tests['cases'] as $ci => $case) {
                            if (!array_key_exists('args', $case) && !array_key_exists('expect', $case) && !array_key_exists('throws', $case)) {
                                $v->errors()->add("questions.$i.tests.cases.$ci", "Case must have args + (expect|throws)");
                            }
                        }
                    }
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $payload = $request->all();

        $test = DB::transaction(function () use ($payload) {
            $test = Test::create([
                'title'             => $payload['test']['title'],
                'description'       => $payload['test']['description'] ?? null,
                'difficulty'        => $payload['test']['difficulty'],
                'estimated_minutes' => $payload['test']['estimatedMinutes'],
            ]);

            foreach ($payload['questions'] as $pos => $q) {
                /** @var Question $question */
                $question = Question::create([
                    'test_id'   => $test->id,
                    'type'      => $q['type'],
                    'title'     => $q['title'],
                    'prompt'    => $q['prompt'] ?? null,
                    'multiple'  => $q['type'] === 'mcq' ? (bool)($q['multiple'] ?? false) : null,
                    'language'  => $q['type'] === 'code' ? ($q['language'] ?? null) : null,
                    'starter'   => $q['type'] === 'code' ? ($q['starter'] ?? null) : null,
                    'tests_json' => $q['type'] === 'code' ? ($q['tests'] ?? null) : null,
                    'position'  => $pos + 1,
                ]);

                if ($q['type'] === 'mcq') {
                    $optionIdsByIndex = [];
                    foreach ($q['options'] as $optPos => $text) {
                        $opt = Option::create([
                            'question_id' => $question->id,
                            'text'        => $text,
                            'position'    => $optPos + 1,
                        ]);
                        $optionIdsByIndex[$optPos] = $opt->id;
                    }

                    $ids = [];
                    foreach ($q['correct'] as $correctIdx) {
                        if (array_key_exists($correctIdx, $optionIdsByIndex)) {
                            $ids[] = $optionIdsByIndex[$correctIdx];
                        }
                    }
                    if (!empty($ids)) {
                        $question->correctOptions()->attach($ids);
                    }
                }
            }

            return $test;
        });

        return response()->json([
            'test' => [
                'id'    => $test->id,
                'title' => $test->title,
            ],
        ], 201);
    }
}
