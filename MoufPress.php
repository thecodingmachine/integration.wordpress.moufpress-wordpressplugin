<?php
use Mouf\Mvc\Splash\Services\SplashUtils;
use Mouf\MoufManager;
use Mouf\Reflection\MoufReflectionClass;
use Mouf\Mvc\Splash\Services\FilterUtils;
use Mouf\Reflection\MoufReflectionMethod;
use Mouf\Mvc\Splash\Services\SplashRequestContext;
use Mouf\Mvc\Splash\Utils\SplashException;

/*
 * This file is part of the Moufpress package.
*
* (c) 2014 David Negrier <david@mouf-php.com>
*
* For the full copyright and license information, please view the LICENSE.txt
* file that was distributed with this source code.
*/

require_once dirname(__FILE__).'/../../../mouf/Mouf.php';

class MoufPress {
	public function __construct() {
		add_action('wp_router_generate_routes', array($this, 'generate_routes'), 10, 1);
		
		add_action( 'widgets_init', function(){
			register_widget( 'Mouf\\Integration\\Wordpress\\Moufpress\\MoufpressWidget' );
		});
	}
	
	public function generate_routes( WP_Router $router ) {
		
		$routes = $this->getRoutesWithoutCache();
		foreach ($routes as $i=>$route) {
			$router->add_route('moufpress_route_'.$i, $route);
		} 
		
		/*$router->add_route('moufpress-sample', array(
				'path' => '^moufpress/(.*?)$',
				'query_vars' => array(
						'sample_argument' => 1,
				),
				'page_callback' => array($this, 'sample_callback'),
				'page_arguments' => array('sample_argument'),
				'access_callback' => TRUE,
				'title' => 'WP Router Sample Page',
				//'template' => false
		));*/
	}

	/*public function sample_callback( $argument ) {
		echo '<p>Welcome to the WP Router sample page. You can find the code that generates this page in '.__FILE__.'</p>';
		echo '<p>This page helpfully tells you the value of the <code>sample_argument</code> query variable: '.esc_html($argument).'</p>';
	}*/
	
	
	
	
	
	
	
	
	/**
	 * Returns the list of routes as an array of arrays.
	 * Bypasses the cache mechanism.
	 * 
	 * @throws SplashException
	 * @return array<array>
	 */
	public function getRoutesWithoutCache() {
		$allConstants = get_defined_constants();
		$urlsList = SplashUtils::getSplashUrlManager()->getUrlsList(false);
		
		$items = array();
		
		foreach ($urlsList as $urlCallback) {
			/* @var $urlCallback SplashCallback */
				
			$url = $urlCallback->url;
			// remove trailing slash
			$url = rtrim($url, "/");
				
			$title = null;
			if ($urlCallback->title !== null) {
				$title = $urlCallback->title ;
			}
				
			
			//////////////// Let's analyze the URL for parameter ////////////////////
			$trimmedUrl = trim($url, '/');
			$urlParts = explode("/", $trimmedUrl);
			$urlPartsNew = array();
			$parametersList = array();
				
			for ($i=0; $i<count($urlParts); $i++) {
				$urlPart = $urlParts[$i];
				if (strpos($urlPart, "{") === 0 && strpos($urlPart, "}") === strlen($urlPart)-1) {
					// Parameterized URL element
					$varName = substr($urlPart, 1, strlen($urlPart)-2);
						
					$parametersList[$varName] = $i;
					$urlPartsNew[] = '(.*?)';
				} else {
					$urlPartsNew[] = $urlPart;
				}
			}
				
			// Let's rewrite the URL, but replacing the {var} parameters with a regexp wildcard
			$url = '^'.implode('/', $urlPartsNew).'$';
			///////////////// End URL analysis ////////////////////
				
			//getItemMenuSettings from annotation
			$annotations = MoufManager::getMoufManager()->getInstanceDescriptor($urlCallback->controllerInstanceName)->getClassDescriptor()->getMethod($urlCallback->methodName)->getAnnotations("DrupalMenuSettings");
			$settings = array();
			if ($annotations){
				if (count($annotations) > 1){
					throw new SplashException('Action '.$urlCallback->methodName.' for controller '.$urlCallback->controllerInstanceName.' should have at most 1 "DrupalMenuSettings" annotation');
				}
				else{
					$settings = json_decode($annotations[0]);
				}
			}
			
			// Recover function filters
			
			// Note: rights management is managed directly by the RequireRights annotation.
			// We could uncomment and work on the code below to have the rights management managed by wp-router instead.
			/*
			$phpDocComment = new MoufPhpDocComment($urlCallback->fullComment);
			$requiresRightArray = $phpDocComment->getAnnotations('RequiresRight');
			$accessArguments = array();
			if(count($requiresRightArray)) {
				foreach ($requiresRightArray as $requiresRight) {
					/* @var $requiresRight RequiresRight * /
					$accessArguments[] = $requiresRight->getName();
				}
			} else {
				$accessArguments[] = 'access content';
			}*/
				
			$httpMethods = $urlCallback->httpMethods;
			if (empty($httpMethods)) {
				$httpMethods["default"] = 'moufpress_execute_action';
			} else {
				foreach ($httpMethods as $httpMethod) {
					$httpMethods[strtoupper($httpMethod)] = 'moufpress_execute_action';
				}
			}
				
			foreach ($httpMethods as $httpMethod) {
				$item= array(
						'path' => $url,
						
						'page_callback' => $httpMethods,
						// First argument passed to execute_action as the instance name, second argument is the method.
						'page_arguments' => array($urlCallback->controllerInstanceName, $urlCallback->methodName, $parametersList, $urlCallback->parameters, $urlCallback->filters),
						'access_callback' => TRUE,
						//'page arguments' => array(array($httpMethod => array("instance"=>$urlCallback->controllerInstanceName, "method"=>$urlCallback->methodName, "urlParameters"=>$parametersList))),
				);
				
				if ($title) {
					$item['title'] = $title;
				}
				
				$items[] = $item;
				
				/*if (isset($items[$url])) {
					// Check that the URL has not been already declared.
					if (isset($items[$url]['page_callback'][$httpMethod])) {
						$msg = "Error! The URL '".$url."' ";
						if ($httpMethod != "default") {
							$msg .= "for HTTP method '".$httpMethod."' ";
						}
						$msg .= " has been declared twice: once for instance '".$urlCallback->controllerInstanceName."' and method '".$urlCallback->methodName."' ";
						$oldCallback = $items[$url]['page arguments'][0][$httpMethod];
						$msg .= " and once for instance '".$oldCallback['instance']."' and method '".$oldCallback['method']."'. The instance  '".$oldCallback['instance']."', method '".$oldCallback['method']."' will be ignored.";
						//throw new MoufException($msg);
						
						add_settings_error(
							'splash_error',
							esc_attr('settings_updated'),
							$msg,
							'updated'
						);
						
						add_action('admin_notices', function() {
							settings_errors( 'splash_error' );
						});
					}
						
					$items[$url]['page_callback'][$httpMethod] = array("instance"=>$urlCallback->controllerInstanceName, "method"=>$urlCallback->methodName, "urlParameters"=>$parametersList);
				} else {
					$items[$url] = array(
							'path' => $url,
							'title' => $title,
							'query_vars' => array(
									'instance' => 1,
									'method' => 1,
							),
							'page_callback' => array($this, 'execute_action'),
							'page_arguments' => array('sample_argument'),
							'access_callback' => TRUE,
							'page arguments' => array(array($httpMethod => array("instance"=>$urlCallback->controllerInstanceName, "method"=>$urlCallback->methodName, "urlParameters"=>$parametersList))),
					);
						
					foreach ($settings as $key => $value){
						if ($key == "type"){
							$value = $allConstants[$value];
						}
						$items[$url][$key] = $value;
					}
				}*/
			}
				
		}
		
		return $items;
	}
	
	public function executeAction($instanceName, $method, $urlParameters, $parameters, $filters) {
		$controller = MoufManager::getMoufManager()->get($instanceName);
		
		if (method_exists($controller,$method)) {
			$refClass = new MoufReflectionClass(get_class($controller));
			// FIXME: the analysis should be performed during getDrupalMenus for performance.
			$refMethod = $refClass->getMethod($method); // $refMethod is an instance of MoufReflectionMethod
		
			
			
			
			$pathinfo = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '';
			list( $pathinfo ) = explode( '?', $pathinfo );
			$pathinfo = str_replace( "%", "%25", $pathinfo );
			list( $req_uri ) = explode( '?', $_SERVER['REQUEST_URI'] );
			$home_path = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );
			
			// Trim path info from the end and the leading home path from the
			// front.
			$req_uri = str_replace($pathinfo, '', $req_uri);
			$req_uri = trim($req_uri, '/');
			$req_uri = preg_replace("|^$home_path|i", '', $req_uri);
			$req_uri = trim($req_uri, '/');
			$requestParts = explode('/', $req_uri);
			
			
			
			$context = new SplashRequestContext();
			$context->setUrlParameters(array_map(function($itemPos) use ($requestParts) { return $requestParts[$itemPos]; }, $urlParameters));
		
			/****/
			$args = array();
			foreach ($parameters as $paramFetcher) {
				/* @var $param SplashParameterFetcherInterface */
				try {
					$args[] = $paramFetcher->fetchValue($context);
				} catch (SplashValidationException $e) {
		
					$e->setPrependedMessage(SplashUtils::translate("validate.error.while.validating.parameter", $paramFetcher->getName()));
					throw $e;
				}
			}
		
			// Handle action__GET or action__POST method (for legacy code).
			if(method_exists($controller, $method.'__'.$_SERVER['REQUEST_METHOD'])) {
				$method = $method.'__'.$_SERVER['REQUEST_METHOD'];
			}
		
		
			// Apply filters
			for ($i=count($filters)-1; $i>=0; $i--) {
				$filters[$i]->beforeAction();
			}
		
			// Ok, now, let's store the parameters.
			//call_user_func_array(array($this,$method), AdminBag::getInstance()->argsArray);
			//$result = call_user_func_array(array($this,$method), $argsArray);
		
			ob_start();
			try {
				echo call_user_func_array(array($controller,$method), $args);
			} catch (Exception $e) {
				ob_end_clean();
				// Rethrow and keep stack trace.
				throw $e;
			}
			/*foreach ($this->content as $element) {
			 $element->toHtml();
			}*/
						
			$wordpressTemplate = Mouf::getWordpressTemplate();
			if ($wordpressTemplate->isDisplayTriggered()) {
				$title = $wordpressTemplate->getTitle();
				if ($title) {
					add_filter('the_title', function($previousTitle) use ($title) {
						if (in_the_loop()) {
							return $title;
						}
						return $previousTitle;
					}, 11);
				}
				
				$wordpressTemplate->getWebLibraryManager()->toHtml();
				$wordpressTemplate->getContentBlock()->toHtml();
			}
			$result = ob_get_clean();
		
			foreach ($filters as $filter) {
				$filter->afterAction();
			}
		
			// Now, let's see if we must output everything in the template or out the template.
		
			if ($wordpressTemplate->isDisplayTriggered()) {
				echo $result;
			} else {
				echo $result;
				exit;
			}
		
		} else {
			global $wp_query;
			$wp_query->set_404();
		}
		
	}
	
}

function moufpress_execute_action($instanceName, $methodName, $urlParameters, $parameters, $filters) {
	global $moufPress;
	$moufPress->executeAction($instanceName, $methodName, $urlParameters, $parameters, $filters);
}