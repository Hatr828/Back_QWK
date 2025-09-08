<?php
namespace App\Services;

use Symfony\Component\Process\Process;

class JsDockerEvaluator
{
    private string $docker;
    private string $image;
    private int $timeoutSec;

    public function __construct()
    {
        $this->docker = env('DOCKER_BIN')
            ?: (PHP_OS_FAMILY === 'Windows' ? 'com.docker.cli.exe' : 'docker');

        $this->image = env('JS_RUNNER_IMAGE', 'code-runner-js:1');
        $this->timeoutSec = (int) env('JS_RUNNER_TIMEOUT', 8);
    }

    public function evaluate(array $testsJson, string $code): array
    {
        $cmd = [
            $this->docker, 'run', '--rm', '-i',
            '--network','none',
            '--cpus=0.5','--memory=128m','--pids-limit=64',
            '--cap-drop=ALL','--security-opt','no-new-privileges',
            '--read-only','--tmpfs','/tmp:rw,noexec,nosuid,size=64m',
            $this->image,
        ];

        $payload = json_encode(['code'=>$code,'tests'=>$testsJson], JSON_UNESCAPED_UNICODE);

        $p = new Process($cmd);
        $p->setTimeout($this->timeoutSec);
        $p->setInput($payload);
        $p->run();

        $out = trim($p->getOutput());
        if ($out === '') {
            $err = trim($p->getErrorOutput());
            return ['passed'=>false,'output'=>'Runner error','feedback'=>$err ?: 'No output','results'=>[]];
        }

        $json = json_decode($out, true);
        if (!is_array($json)) {
            return ['passed'=>false,'output'=>'Runner error','feedback'=>'Bad JSON from runner','results'=>[]];
        }

        return [
            'passed'   => (bool)($json['passed'] ?? false),
            'output'   => (string)($json['output'] ?? ''),
            'feedback' => (string)($json['feedback'] ?? ''),
            'results'  => $json['results'] ?? [],
        ];
    }
}
