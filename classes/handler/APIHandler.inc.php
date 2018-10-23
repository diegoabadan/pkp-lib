<?php

/**
 * @file lib/pkp/classes/handler/APIHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class APIHandler
 * @ingroup handler
 *
 * @brief Base request API handler
 */
AppLocale::requireComponents(LOCALE_COMPONENT_PKP_API, LOCALE_COMPONENT_APP_API);
import('lib.pkp.classes.handler.PKPHandler');

use \Slim\App;
import('lib.pkp.classes.core.APIResponse');
import('classes.core.ServicesContainer');

class APIHandler extends PKPHandler {
	protected $_app;
	protected $_request;
	protected $_endpoints = array();
	protected $_slimRequest = null;
	protected $_apiToken = null;

	/** @var string The endpoint pattern for this handler */
	protected $_pathPattern;

	/** @var string The unique endpoint string for this handler */
	protected $_handlerPath = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		import('lib.pkp.classes.security.authorization.internal.ApiAuthorizationMiddleware');
		import('lib.pkp.classes.security.authorization.internal.ApiTokenDecodingMiddleware');
		import('lib.pkp.classes.security.authorization.internal.ApiCsrfMiddleware');
		$this->_app = new \Slim\App(array(
			// Load custom response handler
			'response' => function($c) {
				return new APIResponse();
			},
			'settings' => array(
				// we need access to route within middleware
				'determineRouteBeforeAppMiddleware' => true,
			)
		));
		$this->_app->add(new ApiAuthorizationMiddleware($this));
		$this->_app->add(new ApiTokenDecodingMiddleware($this));
		$this->_app->add(new ApiCsrfMiddleware($this));
		// remove trailing slashes
		$this->_app->add(function ($request, $response, $next) {
			$uri = $request->getUri();
			$path = $uri->getPath();
			if ($path != '/' && substr($path, -1) == '/') {
				// path with trailing slashes to non-trailing counterpart
				$uri = $uri->withPath(substr($path, 0, -1));
				if($request->getMethod() == 'GET') {
					return $response->withRedirect((string)$uri, 301);
				}
				else {
					return $next($request->withUri($uri), $response);
				}
			}
			return $next($request, $response);
		});
		// if pathinfo is disabled, rewrite URI to match Slim's expectation
		$app = $this->getApp();
		$this->_app->add(function ($request, $response, $next) use($app) {
			$uri = $request->getUri();
			$endpoint = trim($request->getQueryParam('endpoint'));
			$pathInfoEnabled = Config::getVar('general', 'disable_path_info') ? false : true;
			$path = $uri->getPath();
			if (!$pathInfoEnabled && !is_null($endpoint) && !isset($_SERVER['PATH_INFO']) && ($path == '/')) {
				$basePath = $uri->getBasePath();
				if($request->getMethod() == 'GET') {
					$uri = $uri->withPath($basePath . $endpoint);
					return $response->withRedirect((string)$uri, 301);
				}
				else {
					// because the route is calculated before any middleware is executed
					// we need to call App::process because the URI changed so that dispatch happens again
					$uri = $uri->withPath($endpoint);
					return $app->process($request->withUri($uri), $response);
				}
			}
			return $next($request, $response);
		});
		// Allow remote requests to the API
		$this->_app->add(function ($request, $response, $next) {
			$response = $response->withHeader('Access-Control-Allow-Origin', '*');
			return $next($request, $response);
		});
		$this->_request = Application::getRequest();
		$this->setupEndpoints();
	}

	/**
	 * Return PKP request object
	 *
	 * @return PKPRequest
	 */
	public function getRequest() {
		return $this->_request;
	}

	/**
	 * Return API token string
	 *
	 * @return string|null
	 */
	public function getApiToken() {
		return $this->_apiToken;
	}

	/**
	 * Set API token string
	 *
	 */
	public function setApiToken($apiToken) {
		return $this->_apiToken = $apiToken;
	}

	/**
	 * Return Slim request object
	 *
	 * @return SlimRequest|null
	 */
	public function getSlimRequest() {
		return $this->_slimRequest;
	}

	/**
	 * Set Slim request object
	 *
	 */
	public function setSlimRequest($slimRequest) {
		return $this->_slimRequest = $slimRequest;
	}

	/**
	 * Get the Slim application.
	 * @return App
	 */
	public function getApp() {
		return $this->_app;
	}

	/**
	 * Get the endpoint pattern for this handler
	 *
	 * Compiles the URI path pattern from the context, api version and the
	 * unique string for the this handler.
	 *
	 * @return string
	 */
	public function getEndpointPattern() {

		if (!isset($this->_pathPattern)) {
			$this->_pathPattern = '/{contextPath}/api/{version}/' . $this->_handlerPath;
		}

		return $this->_pathPattern;
	}

	/**
	 * Get the entity ID for a specified parameter name.
	 * (Parameter names are generally defined in authorization policies
	 * @return int|string?
	 */
	public function getEntityId($parameterName) {
		assert(false);
		return null;
	}

	/**
	 * setup endpoints
	 */
	public function setupEndpoints() {
		$app = $this->getApp();
		$endpoints = $this->getEndpoints();
		foreach ($endpoints as $method => $definitions) {
			foreach ($definitions as $parameters) {
				$method = strtolower($method);
				$pattern = $parameters['pattern'];
				$handler = $parameters['handler'];
				$roles = isset($parameters['roles']) ? $parameters['roles'] : null;
				$app->$method($pattern, $handler)->setName($handler[1]);
				if (!is_null($roles) && is_array($roles)) {
					$this->addRoleAssignment($roles, $handler[1]);
				}
			}
		}
	}

	/**
	 * Returns the list of endpoints
	 *
	 * @return array
	 */
	public function getEndpoints() {
		return $this->_endpoints;
	}

	/**
	 * Fetches parameter value
	 *
	 * @param string $parameterName
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function getParameter($parameterName, $default = null) {
		$slimRequest = $this->getSlimRequest();
		if ($slimRequest == null) {
			return $default;
		}

		$route = $slimRequest->getAttribute('route');

		// we probably have an invalid url if route is null
		if (!is_null($route)) {
			$arguments = $route->getArguments();
			if (isset($arguments[$parameterName])) {
				return $arguments[$parameterName];
			}

			$queryParams = $slimRequest->getQueryParams();
			if (isset($queryParams[$parameterName])) {
				return $queryParams[$parameterName];
			}
		}

		return $default;
	}

	/**
	 * Convert string values in boolean, integer and number parameters to their
	 * appropriate type when the string is in a recognizable format.
	 *
	 * Converted booleans: False: "0", "false". True: "true", "1"
	 * Converted integers: Anything that passes ctype_digit()
	 * Converted floats: Anything that passes is_numeric()
	 *
	 * Empty strings will be converted to null.
	 *
	 * @param $schema string One of the SCHEMA_... constants
	 * @param $params array Key/value parameters to be validated
	 * @return array Converted parameters
	 */
	public function convertStringsToSchema($schema, $params) {
		$schemaService = ServicesContainer::instance()->get('schema');
		$schema = $schemaService->get($schema);

		foreach ($params as $paramName => $paramValue) {
			if (!property_exists($schema->properties, $paramName)) {
				continue;
			}
			if (!empty($schema->properties->{$paramName}->multilingual)) {
				foreach ($paramValue as $localeKey => $localeValue) {
					$params[$paramName][$localeKey] = $this->_convertStringsToSchema(
						$localeValue,
						$schema->properties->{$paramName}->type,
						$schema->properties->{$paramName}
					);
				}
			} else {
				$params[$paramName] = $this->_convertStringsToSchema(
					$paramValue,
					$schema->properties->{$paramName}->type,
					$schema->properties->{$paramName}
				);
			}
		}

		return $params;
	}

	/**
	 * Helper function to convert a string to a specified type if it meets
	 * certain conditions.
	 *
	 * This function can be called recursively on nested objects and arrays.
	 *
	 * @see self::convertStringsToTypes
	 * @param $value
	 * @param $type One of boolean, integer or number
	 */
	private function _convertStringsToSchema($value, $type, $schema) {
		// Convert all empty strings to null
		if (is_string($value) && !strlen($value)) {
			return null;
		}
		switch ($type) {
			case 'boolean':
				if (is_string($value)) {
					if ($value === 'true' || $value === '1') {
						return true;
					} elseif ($value === 'false' || $value === '0') {
						return false;
					}
				}
				break;
			case 'integer':
				if (is_string($value) && ctype_digit($value)) {
					return (int) $value;
				}
				break;
			case 'number':
				if (is_string($value) && is_numeric($value)) {
					return floatval($value);
				}
				break;
			case 'array':
				if (is_array($value)) {
					$newArray = [];
					if (is_array($schema->items)) {
						foreach ($schema->items as $i => $itemSchema) {
							$newArray[$i] = $this->_convertStringsToSchema($value[$i], $itemSchema->type, $itemSchema);
						}
					} else {
						foreach ($value as $i => $v) {
							$newArray[$i] = $this->_convertStringsToSchema($v, $schema->items->type, $schema->items);
						}
					}
					return $newArray;
				}
				break;
			case 'object':
				if (is_array($value)) {
					$newObject = [];
					foreach ($schema->properties as $propName => $propSchema) {
						if (!isset($value[$propName])) {
							continue;
						}
						$newObject[$propName] = $this->_convertStringsToSchema($value[$propName], $propSchema->type, $propSchema);
					}
					return $value;
				}
				break;
		}
		return $value;
	}
}
