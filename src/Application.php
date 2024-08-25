<?php

namespace Rak\BB;

require __DIR__ . '/../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7Server\ServerRequestCreator;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

abstract class Application
{
	abstract protected function setupRouteCollector(RouteCollector $collector);

	protected Psr17Factory $psr17Factory;
	protected Response $response;
	protected ServerRequest $serverRequest;
	protected Dispatcher $dispatcher;
	protected RouteInfo $routeInfo;

	public function __construct()
	{
		$this->psr17Factory = new Psr17Factory();
		$this->response = $this
			->psr17Factory
			->createResponse(200)
		;

		$this->serverRequest = (new ServerRequestCreator(
			$this->psr17Factory,
			$this->psr17Factory,
			$this->psr17Factory,
			$this->psr17Factory
		))->fromGlobals();

		$this->dispatcher = \FastRoute\simpleDispatcher(
			fn(\FastRoute\RouteCollector $r) => $this->setupRouteCollector($r)
		);

		$this->routeInfo = new RouteInfo($this->dispatcher->dispatch(
			$this->serverRequest->getMethod(),
			$this->serverRequest->getUri()->getPath()
		));
	}

	public function answer()
	{
		$dispatcherMap = [
			Dispatcher::NOT_FOUND => [$this, 'dispatchNotFound'],
			Dispatcher::METHOD_NOT_ALLOWED => [$this, 'dispatchMethodNotAllowed'],
			Dispatcher::FOUND => [$this, 'dispatchFound'],
		];
		$handler = $dispatcherMap[$this->routeInfo->status] ?? null;

		ob_start(fn (string $buffer, int $phase) => null);

		if ($handler)
			$handler();

		$this->emitResponse($this->response);
	}

	protected function dispatchNotFound()
	{
		$this->response = $this->psr17Factory->createResponse(404);
	}

	protected function dispatchMethodNotAllowed()
	{
		$this->response = $this
			->psr17Factory
			->createResponse(405)
			->withHeader('Allow', implode(', ', $this->routeInfo->allowedMethods))
		;
	}

	protected function dispatchFound()
	{
		($this->routeInfo->handler)();
	}

	protected function defaultHandler()
	{
		$this->response = $this->psr17Factory->createResponse(500);
	}

	protected function redirect(int $status, string $location)
	{
		$this->response = $this
			->psr17Factory
			->createResponse(301)
			->withHeader('Location', '/')
		;
	}

	protected function emitResponse(Response $response)
	{
		$emitter = new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

		$emitter->emit(
			$response->withBody(
				$this->psr17Factory->createStream(ob_get_flush())
			)
		);
	}
}
