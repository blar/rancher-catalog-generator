<?php

use GrahamCampbell\GitHub\Facades\GitHub;
use GrahamCampbell\GitLab\Facades\GitLab;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

function copyGitlab($source, $target, $options) {
    $file = Gitlab::repositoryFiles()->getFile(
        $options['source']['project_id'],
        $source,
        $options['source']['ref']
    );

    $targetRef = $options['target']['ref'] ?? 'master';

    try {
        Gitlab::repositoryFiles()->getFile(
            $options['source']['project_id'],
            $source,
            $options['source']['ref']
        );
        Gitlab::repositoryFiles()->updateFile($options['target']['project_id'], [
            'file_path' => $target,
            'content' => $file['content'],
            'encoding' => 'base64',
            'branch' => $targetRef,
            'commit_message' => 'test'
        ]);
    }
    catch(Throwable $e) {
        Gitlab::repositoryFiles()->createFile($options['target']['project_id'], [
            'file_path' => $target,
            'content' => $file['content'],
            'encoding' => 'base64',
            'branch' => $targetRef,
            'commit_message' => 'test'
        ]);
    }
}

Route::post('/gitlab/system_hook', function(Request $request) {
    if($request->header('X-Gitlab-Event') !== 'System Hook') {
        return;
    }
    if($request->header('X-Gitlab-Token') !== getenv('GITLAB_SYSTEM_HOOK_SECRET')) {
        return;
    }

    $json = json_decode($request->getContent());
    if($json->event_name !== 'tag_push') {
        return;
    }
    $targetBasePath = 'templates/'.$json->project->name;
    $ref = explode('/', $json->ref, 3)[2];

    if(substr($ref, 0, 1) !== 'v') {
        return;
    }
    $shortRef = substr($ref, 1);
    $copyOptions = [
        'source' => [
            'project_id' => $json->project_id,
            'ref' => $ref
        ],
        'target' => [
            'project_id' => getenv('GITLAB_RANCHER_CATALOG_PROJECT_ID'),
            'ref' => 'master'
        ]
    ];

    copyGitlab('rancher/config.yml', $targetBasePath.'/config.yml', $copyOptions);
    copyGitlab('rancher/docker-compose.yml', $targetBasePath.'/'.$shortRef.'/docker-compose.yml', $copyOptions);
    copyGitlab('rancher/rancher-compose.yml', $targetBasePath.'/'.$shortRef.'/rancher-compose.yml', $copyOptions);

    try {
        copyGitlab('rancher/catalogIcon-entry.svg', $targetBasePath.'/catalogIcon-entry.svg', $copyOptions);
    }
    catch(Throwable $e) {
    }

    try {
        copyGitlab('README.md', $targetBasePath.'/'.$shortRef.'/README.md', $copyOptions);
    }
    catch(Throwable $e) {
    }
});
