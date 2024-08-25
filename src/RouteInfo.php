<?php

namespace Rak\BB;

use FastRoute\Dispatcher;

class RouteInfo {
	public readonly int $status;
	public $handler;
	public array $args;
	public array $allowedMethods;

	public function __construct(array $routeInfo) {
		$this->status = $routeInfo[0];
		$this->args = $routeInfo[2] ?? [];

		if ($this->status === Dispatcher::METHOD_NOT_ALLOWED)
			$this->allowedMethods = $routeInfo[1];
		else
			$this->handler = $routeInfo[1] ?? null;
	}
}
