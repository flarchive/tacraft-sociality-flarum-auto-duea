<?php

namespace TacraftSociality\AutoDuea\Api;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TacraftSociality\AutoDuea\AutoDuea;

class StatusController implements RequestHandlerInterface
{
    public function __construct(protected AutoDuea $autoDuea)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return new JsonResponse($this->autoDuea->counts());
    }
}
