<?php

namespace TacraftSociality\AutoDuea\Api;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TacraftSociality\AutoDuea\AutoDuea;

class RunController implements RequestHandlerInterface
{
    public function __construct(protected AutoDuea $autoDuea)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = (array) $request->getParsedBody();
        $all = ! empty($body['all']);

        $deleted = $all ? $this->autoDuea->runAll() : $this->autoDuea->run();

        return new JsonResponse([
            'deleted' => $deleted,
            'status' => $this->autoDuea->counts(),
        ]);
    }
}
