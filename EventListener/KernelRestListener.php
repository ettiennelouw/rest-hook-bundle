<?php
/**
 * A listener class that "hooks" into the kernels response events.
 * It will reformat the response according to the configuration
 * @author Sacheen Dhanjie
 *
 */
namespace SD\RestHookBundle\EventListener;

use SD\RestHookBundle\Util\ExceptionFormatter;
use Symfony\Bundle\TwigBundle\Extension\CodeExtension;
use JMS\SerializerBundle\Serializer\Serializer;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;


use SD\KernelHookBundle\Util\Codes;

class KernelRestListener
{


    protected $serializer;

    private $formats;

    private $patterns;

    private $jsonCallback;

    public function __construct($serializer, $formats, $patterns, $jsonCallback = null)
    {
        $this->serializer = $serializer;
        $this->formats = $formats;
        $this->patterns = $patterns;
        $this->jsonCallback = $jsonCallback;
    }

    /**
     * Checks if the requested uri pattern is valid
     * @param string $uri
     * @return bool
     */
    private function isValidPattern($uri)
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handles the response of a controller that meets the configurations specifications
     * It will only action if the pattern and format requirement is met
     * optional is support for a jsoncallback, it this is set the response is encapsulated using the callback string specified
     *
     * @param GetResponseForControllerResultEvent $event
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $responseData = null;

        if (in_array($event->getRequest()->getRequestFormat(), $this->formats) && $this->isValidPattern($event->getRequest()->getRequestUri())) {

            $responseData = $this->serializer->serialize($event->getControllerResult(), $event->getRequest()->getRequestFormat());
            $response = new Response();
            if (! is_null($this->jsonCallback) && $event->getRequest()->get($this->jsonCallback) && $event->getRequest()->getRequestFormat() == 'json') {
                $responseData = $event->getRequest()->get($this->jsonCallback).'('.$responseData.')';
            }
            $response->setContent($responseData);
            $event->setResponse($response);


        }

    }

    /**
     * Handles the exception of a controller that meets the configurations specifications
     * It will only action if the pattern and format requirement is met
     * optional is support for a jsoncallback, it this is set the response is encapsulated using the callback string specified
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        /* @var $event GetResponseForExceptionEvent */
        $responseData = null;

        if (in_array($event->getRequest()->getRequestFormat(), $this->formats) && $this->isValidPattern($event->getRequest()->getRequestUri())) {

            $exception = $event->getException();

            $code = 500;
            if (array_key_exists($exception->getCode(), array_flip(Codes::$codeMap))) {
                $code = $exception->getCode();
            }

            $exceptionFormatter = new ExceptionFormatter($code, $exception->getMessage());

            /* @var $serializer Serializer */
            $responseData = $this->serializer->serialize($exceptionFormatter, $event->getRequest()->getRequestFormat());

            if (! is_null($this->jsonCallback) && $event->getRequest()->get($this->jsonCallback) && $event->getRequest()->getRequestFormat() == 'json') {
                $responseData = $event->getRequest()->get($this->jsonCallback).'('.$responseData.')';
            }

            $response = new Response();
            $response->setStatusCode($code, Codes::getCodeMessage($code));
            $response->setContent($responseData);
            $event->setResponse($response);
        }

    }
}