<?php

namespace Mindgruve\ReverseProxy\WordPress;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

class WordPressBootstrap implements ControllerResolverInterface
{
    protected $bootstrapFile;

    protected $cacheDecisionManager;

    /**
     * THE PATH TO THE WORDPRESS INSTALLATION
     *
     * @param $bootstrapFile
     */
    public function __construct($bootstrapFile, $cacheDecisionManager)
    {
        $this->bootstrapFile = $bootstrapFile;
        $this->cacheDecisionManager = $cacheDecisionManager;
    }

    /**
     * THE ARGUMENTS PASSED TO THE CALLABLE
     *
     * @param Request $request
     * @param callable $controller
     * @return array
     */
    public function getArguments(Request $request, $controller)
    {
        return array(
            $this->bootstrapFile,
        );
    }

    /**
     * BOOTSTRAPS WORDPRESS, AND COLLECTS THE OUTPUT INTO A RESPONSE OBJECT
     *
     * @param Request $request
     * @return callable
     */
    public function getController(Request $request)
    {
        $bootstrapFile = $this->bootstrapFile;
        $cacheDecisionManager = $this->cacheDecisionManager;
        return function () use ($request, $bootstrapFile, $cacheDecisionManager) {
            if (!file_exists($bootstrapFile)) {
                throw new \Exception('Unable to bootstrap WordPress.');
            }

            ob_start();
            include_once($bootstrapFile);
            $output = ob_get_contents();
            ob_end_clean();

            $response = new Response($output);
            $response->headers->add(getallheaders());
            $response->setStatusCode(http_response_code());

            $cacheDecisionManager->applyCacheRules( $request, $response);

            return $response;
        };
    }
}