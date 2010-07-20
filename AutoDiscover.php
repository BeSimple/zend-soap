<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Soap
 * @subpackage AutoDiscover
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @namespace
 */
namespace Zend\Soap;

use Zend\URI,
    Zend\Soap\WSDL;

/**
 * \Zend\Soap\AutoDiscover
 *
 * @uses       \Zend\Server\AbstractServer
 * @uses       \Zend\Server\Server
 * @uses       \Zend\Server\Reflection
 * @uses       \Zend\Soap\AutoDiscover\Exception
 * @uses       \Zend\Soap\WSDL
 * @uses       \Zend\URI\URI
 * @category   Zend
 * @package    Zend_Soap
 * @subpackage AutoDiscover
 */
class AutoDiscover implements \Zend\Server\Server
{
    /**
     * @var \Zend\Soap\WSDL
     */
    protected $_wsdl = null;

    /**
     * @var \Zend\Server\Reflection
     */
    protected $_reflection = null;

    /**
     * @var array
     */
    protected $_functions = array();

    /**
     * @var boolean
     */
    protected $_strategy;

    /**
     * Url where the WSDL file will be available at.
     *
     * @var WSDL Uri
     */
    protected $_uri;

    /**
     * soap:body operation style options
     *
     * @var array
     */
    protected $_operationBodyStyle = array('use' => 'encoded', 'encodingStyle' => "http://schemas.xmlsoap.org/soap/encoding/");

    /**
     * soap:operation style
     *
     * @var array
     */
    protected $_bindingStyle = array('style' => 'rpc', 'transport' => 'http://schemas.xmlsoap.org/soap/http');

    /**
     * Name of the class to handle the WSDL creation.
     *
     * @var string
     */
    protected $_wsdlClass = '\Zend\Soap\WSDL';

    /**
     * Constructor
     *
     * @param boolean|string|\Zend\Soap\WSDL\Strategy $strategy
     * @param string|\Zend\URI\URI $uri
     * @param string $wsdlClass
     */
    public function __construct($strategy = true, $uri=null, $wsdlClass=null)
    {
        $this->_reflection = new \Zend\Server\Reflection();
        $this->setComplexTypeStrategy($strategy);

        if($uri !== null) {
            $this->setUri($uri);
        }

        if($wsdlClass !== null) {
            $this->setWSDLClass($wsdlClass);
        }
    }

    /**
     * Set the location at which the WSDL file will be availabe.
     *
     * @throws \Zend\Soap\AutoDiscover\Exception
     * @param  \Zend\URI\URI|string $uri
     * @return \Zend\Soap\AutoDiscover
     */
    public function setUri($uri)
    {
        if(!is_string($uri) && !($uri instanceof URI\URI)) {
            throw new AutoDiscoverException(
                'No uri given to \Zend\Soap\AutoDiscover::setUri as string or \Zend\URI\URI instance.'
            );
        }
        $this->_uri = $uri;

        // change uri in WSDL file also if existant
        if($this->_wsdl instanceof WSDL) {
            $this->_wsdl->setUri($uri);
        }

        return $this;
    }

    /**
     * Return the current Uri that the SOAP WSDL Service will be located at.
     *
     * @return \Zend\URI\URI
     */
    public function getUri()
    {
        if($this->_uri !== null) {
            $uri = $this->_uri;
        } else {
            $schema     = $this->getSchema();
            $host       = $this->getHostName();
            $scriptName = $this->getRequestUriWithoutParameters();
            $uri = new URI\URL($schema . '://' . $host . $scriptName);
            $this->setUri($uri);
        }
        return $uri;
    }

    /**
     * Set the name of the WSDL handling class.
     *
     * @throws \Zend\Soap\AutoDiscover\Exception
     * @param  string $wsdlClass
     * @return \Zend\Soap\AutoDiscover
     */
    public function setWSDLClass($wsdlClass)
    {
        if(!is_string($wsdlClass) && !is_subclass_of($wsdlClass, 'Zend\Soap\WSDL')) {
            throw new AutoDiscoverException(
                'No \Zend\Soap\WSDL subclass given to Zend\Soap\AutoDiscover::setWSDLClass as string.'
            );
        }
        $this->_wsdlClass = $wsdlClass;

        return $this;
    }

    /**
     * Return the name of the WSDL handling class.
     *
     * @return string
     */
    public function getWSDLClass()
    {
        return $this->_wsdlClass;
    }

    /**
     * Set options for all the binding operations soap:body elements.
     *
     * By default the options are set to 'use' => 'encoded' and
     * 'encodingStyle' => "http://schemas.xmlsoap.org/soap/encoding/".
     *
     * @param  array $operationStyle
     * @return \Zend\Soap\AutoDiscover
     */
    public function setOperationBodyStyle(array $operationStyle=array())
    {
        if(!isset($operationStyle['use'])) {
            throw new AutoDiscoverException("Key 'use' is required in Operation soap:body style.");
        }
        $this->_operationBodyStyle = $operationStyle;
        return $this;
    }

    /**
     * Set Binding soap:binding style.
     *
     * By default 'style' is 'rpc' and 'transport' is 'http://schemas.xmlsoap.org/soap/http'.
     *
     * @param  array $bindingStyle
     * @return \Zend\Soap\AutoDiscover
     */
    public function setBindingStyle(array $bindingStyle=array())
    {
        if(isset($bindingStyle['style'])) {
            $this->_bindingStyle['style'] = $bindingStyle['style'];
        }
        if(isset($bindingStyle['transport'])) {
            $this->_bindingStyle['transport'] = $bindingStyle['transport'];
        }
        return $this;
    }

    /**
     * Detect and returns the current HTTP/HTTPS Schema
     *
     * @return string
     */
    protected function getSchema()
    {
        $schema = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $schema = 'https';
        }
        return $schema;
    }

    /**
     * Detect and return the current hostname
     *
     * @return string
     */
    protected function getHostName()
    {
        if(isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            $host = $_SERVER['SERVER_NAME'];
        }
        return $host;
    }

    /**
     * Detect and return the current script name without parameters
     *
     * @return string
     */
    protected function getRequestUriWithoutParameters()
    {
        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
        } else {
            $requestUri = $_SERVER['SCRIPT_NAME'];
        }
        if( ($pos = strpos($requestUri, "?")) !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        return $requestUri;
    }

    /**
     * Set the strategy that handles functions and classes that are added AFTER this call.
     *
     * @param  boolean|string|\Zend\Soap\WSDL\Strategy $strategy
     * @return \Zend\Soap\AutoDiscover
     */
    public function setComplexTypeStrategy($strategy)
    {
        $this->_strategy = $strategy;
        if($this->_wsdl instanceof WSDL) {
            $this->_wsdl->setComplexTypeStrategy($strategy);
        }

        return $this;
    }

    /**
     * Set the Class the SOAP server will use
     *
     * @param string $class Class Name
     * @param string $namespace Class Namspace - Not Used
     * @param array $argv Arguments to instantiate the class - Not Used
     */
    public function setClass($class, $namespace = '', $argv = null)
    {
        $uri = $this->getUri();

        $translatedClass = WSDL::translateType($class);

        $wsdl = new $this->_wsdlClass($translatedClass, $uri, $this->_strategy);

        // The wsdl:types element must precede all other elements (WS-I Basic Profile 1.1 R2023)
        $wsdl->addSchemaTypeSection();

        $port = $wsdl->addPortType($translatedClass . 'Port');
        $binding = $wsdl->addBinding($translatedClass . 'Binding', 'tns:' . $translatedClass . 'Port');

        $wsdl->addSoapBinding($binding, $this->_bindingStyle['style'], $this->_bindingStyle['transport']);
        $wsdl->addService($translatedClass . 'Service',
                          $translatedClass . 'Port',
                          'tns:' . $translatedClass . 'Binding', $uri);
        foreach ($this->_reflection->reflectClass($class)->getMethods() as $method) {
            $this->_addFunctionToWSDL($method, $wsdl, $port, $binding);
        }
        $this->_wsdl = $wsdl;
    }

    /**
     * Add a Single or Multiple Functions to the WSDL
     *
     * @param string $function Function Name
     * @param string $namespace Function namespace - Not Used
     */
    public function addFunction($function, $namespace = '')
    {
        static $port;
        static $operation;
        static $binding;

        if (!is_array($function)) {
            $function = (array) $function;
        }

        $uri = $this->getUri();

        if (!($this->_wsdl instanceof WSDL)) {
            $parts = explode('.', basename($_SERVER['SCRIPT_NAME']));
            $name = $parts[0];
            $wsdl = new WSDL($name, $uri, $this->_strategy);

            // The wsdl:types element must precede all other elements (WS-I Basic Profile 1.1 R2023)
            $wsdl->addSchemaTypeSection();

            $port = $wsdl->addPortType($name . 'Port');
            $binding = $wsdl->addBinding($name . 'Binding', 'tns:' .$name. 'Port');

            $wsdl->addSoapBinding($binding, $this->_bindingStyle['style'], $this->_bindingStyle['transport']);
            $wsdl->addService($name . 'Service', $name . 'Port', 'tns:' . $name . 'Binding', $uri);
        } else {
            $wsdl = $this->_wsdl;
        }

        foreach ($function as $func) {
            $method = $this->_reflection->reflectFunction($func);
            $this->_addFunctionToWSDL($method, $wsdl, $port, $binding);
        }
        $this->_wsdl = $wsdl;
    }

    /**
     * Add a function to the WSDL document.
     *
     * @param $function \Zend\Server\Reflection\AbstractFunction function to add
     * @param $wsdl \Zend\Soap\WSDL WSDL document
     * @param $port object wsdl:portType
     * @param $binding object wsdl:binding
     * @return void
     */
    protected function _addFunctionToWSDL($function, $wsdl, $port, $binding)
    {
        $uri = $this->getUri();

        // We only support one prototype: the one with the maximum number of arguments
        $prototype = null;
        $maxNumArgumentsOfPrototype = -1;
        foreach ($function->getPrototypes() as $tmpPrototype) {
            $numParams = count($tmpPrototype->getParameters());
            if ($numParams > $maxNumArgumentsOfPrototype) {
                $maxNumArgumentsOfPrototype = $numParams;
                $prototype = $tmpPrototype;
            }
        }
        if ($prototype === null) {
            throw new AutoDiscoverException("No prototypes could be found for the '" . $function->getName() . "' function");
        }

        $functionName = WSDL::translateType($function->getName());

        // Add the input message (parameters)
        $args = array();
        if ($this->_bindingStyle['style'] == 'document') {
            // Document style: wrap all parameters in a sequence element
            $sequence = array();
            foreach ($prototype->getParameters() as $param) {
                $sequenceElement = array(
                    'name' => $param->getName(),
                    'type' => $wsdl->getType($param->getType())
                );
                if ($param->isOptional()) {
                    $sequenceElement['nillable'] = 'true';
                }
                $sequence[] = $sequenceElement;
            }
            $element = array(
                'name' => $functionName,
                'sequence' => $sequence
            );
            // Add the wrapper element part, which must be named 'parameters'
            $args['parameters'] = array('element' => $wsdl->addElement($element));
        } else {
            // RPC style: add each parameter as a typed part
            foreach ($prototype->getParameters() as $param) {
                $args[$param->getName()] = array('type' => $wsdl->getType($param->getType()));
            }
        }
        $wsdl->addMessage($functionName . 'In', $args);

        $isOneWayMessage = false;
        if($prototype->getReturnType() == "void") {
            $isOneWayMessage = true;
        }

        if($isOneWayMessage == false) {
            // Add the output message (return value)
            $args = array();
            if ($this->_bindingStyle['style'] == 'document') {
                // Document style: wrap the return value in a sequence element
                $sequence = array();
                if ($prototype->getReturnType() != "void") {
                    $sequence[] = array(
                        'name' => $functionName . 'Result',
                        'type' => $wsdl->getType($prototype->getReturnType())
                    );
                }
                $element = array(
                    'name' => $functionName . 'Response',
                    'sequence' => $sequence
                );
                // Add the wrapper element part, which must be named 'parameters'
                $args['parameters'] = array('element' => $wsdl->addElement($element));
            } else if ($prototype->getReturnType() != "void") {
                // RPC style: add the return value as a typed part
                $args['return'] = array('type' => $wsdl->getType($prototype->getReturnType()));
            }
            $wsdl->addMessage($functionName . 'Out', $args);
        }

        // Add the portType operation
        if($isOneWayMessage == false) {
            $portOperation = $wsdl->addPortOperation($port, $functionName, 'tns:' . $functionName . 'In', 'tns:' . $functionName . 'Out');
        } else {
            $portOperation = $wsdl->addPortOperation($port, $functionName, 'tns:' . $functionName . 'In', false);
        }
        $desc = $function->getDescription();
        if (strlen($desc) > 0) {
            $wsdl->addDocumentation($portOperation, $desc);
        }

        // When using the RPC style, make sure the operation style includes a 'namespace' attribute (WS-I Basic Profile 1.1 R2717)
        if ($this->_bindingStyle['style'] == 'rpc' && !isset($this->_operationBodyStyle['namespace'])) {
            $this->_operationBodyStyle['namespace'] = ''.$uri;
        }

        // Add the binding operation
        $operation = $wsdl->addBindingOperation($binding, $functionName,  $this->_operationBodyStyle, $this->_operationBodyStyle);
        $wsdl->addSoapOperation($operation, $uri . '#' . $functionName);

        // Add the function name to the list
        $this->_functions[] = $function->getName();
    }

    /**
     * Action to take when an error occurs
     *
     * @param string $fault
     * @param string|int $code
     */
    public function fault($fault = null, $code = null)
    {
        throw new AutoDiscoverException('Function has no use in AutoDiscover.');
    }

    /**
     * Handle the Request
     *
     * @param string $request A non-standard request - Not Used
     */
    public function handle($request = false)
    {
        if (!headers_sent()) {
            header('Content-Type: text/xml');
        }
        $this->_wsdl->dump();
    }

    /**
     * Proxy to WSDL dump function
     *
     * @param string $filename
     */
    public function dump($filename)
    {
        if($this->_wsdl !== null) {
            return $this->_wsdl->dump($filename);
        } else {
            throw new AutoDiscoverException('Cannot dump autodiscovered contents, WSDL file has not been generated yet.');
        }
    }

    /**
     * Proxy to WSDL toXml() function
     */
    public function toXml()
    {
        if($this->_wsdl !== null) {
            return $this->_wsdl->toXml();
        } else {
            throw new AutoDiscoverException('Cannot return autodiscovered contents, WSDL file has not been generated yet.');
        }
    }

    /**
     * Return an array of functions in the WSDL
     *
     * @return array
     */
    public function getFunctions()
    {
        return $this->_functions;
    }

    /**
     * Load Functions
     *
     * @param unknown_type $definition
     */
    public function loadFunctions($definition)
    {
        throw new AutoDiscoverException('Function has no use in AutoDiscover.');
    }

    /**
     * Set Persistance
     *
     * @param int $mode
     */
    public function setPersistence($mode)
    {
        throw new AutoDiscoverException('Function has no use in AutoDiscover.');
    }

    /**
     * Returns an XSD Type for the given PHP type
     *
     * @param string $type PHP Type to get the XSD type for
     * @return string
     */
    public function getType($type)
    {
        if (!($this->_wsdl instanceof WSDL)) {
            /** @todo Exception throwing may be more correct */

            // WSDL is not defined yet, so we can't recognize type in context of current service
            return '';
        } else {
            return $this->_wsdl->getType($type);
        }
    }
}
