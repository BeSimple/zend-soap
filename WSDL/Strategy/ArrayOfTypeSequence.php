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
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @namespace
 */
namespace Zend\Soap\WSDL\Strategy;

/**
 * Zend_Soap_WSDL_Strategy_ArrayOfTypeSequence
 *
 * @uses       \Zend\Soap\WSDL\Strategy\DefaultComplexType
 * @category   Zend
 * @package    Zend_Soap
 * @subpackage WSDL
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
use Zend\Soap\WSDL;

class ArrayOfTypeSequence extends DefaultComplexType
{
    /**
     * Add an unbounded ArrayOfType based on the xsd:sequence syntax if type[] is detected in return value doc comment.
     *
     * @param string $type
     * @return string tns:xsd-type
     */
    public function addComplexType($type)
    {
        $nestedCounter = $this->_getNestedCount($type);

        if($nestedCounter > 0) {
            $singularType = $this->_getSingularType($type);

            for($i = 1; $i <= $nestedCounter; $i++) {
                $complexType = $this->_getTypeBasedOnNestingLevel($singularType, $i);
                $complexTypeName = substr($complexType, strpos($complexType, ':') + 1);

                $childType = $this->_getTypeBasedOnNestingLevel($singularType, $i-1);

                $this->_addSequenceType($complexTypeName,
                                        $childType,
                                        $singularType . str_repeat('[]', $i));
            }
            // adding the PHP type which is resolved to a nested XSD type. therefore add only once.
            $this->getContext()->addType($type);

            return 'tns:' . $complexTypeName;
        } else if (!in_array($type, $this->getContext()->getTypes())) {
            // New singular complex type
            return parent::addComplexType($type);
        } else {
            // Existing complex type
            return $this->getContext()->getType($type);
        }
    }

    /**
     * Return the ArrayOf or simple type name based on the singular xsdtype and the nesting level
     *
     * @param  string $singularType
     * @param  int    $level
     * @return string
     */
    protected function _getTypeBasedOnNestingLevel($singularType, $level)
    {
        if($level == 0) {
            // This is not an Array anymore, return the xsd simple type
            return $this->getContext()->getType($singularType);
        } else {
            return 'tns:' . str_repeat('ArrayOf', $level) . ucfirst(WSDL::translateType($singularType));
        }
    }

    /**
     * From a nested defintion with type[], get the singular xsd:type
     *
     * @throws \Zend\Soap\WSDLException When no xsd:simpletype can be detected.
     * @param  string $type
     * @return string
     */
    protected function _getSingularType($type)
    {
        return str_replace('[]', '', $type);
    }

    /**
     * Return the array nesting level based on the type name
     *
     * @param  string $type
     * @return integer
     */
    protected function _getNestedCount($type)
    {
        return substr_count($type, '[]');
    }

    /**
     * Append the complex type definition to the WSDL via the context access
     *
     * @param  string $arrayTypeName  Array type name (e.g. 'ArrayOf...')
     * @param  string $childType      Qualified array items type (e.g. 'xsd:int', 'tns:ArrayOfInt')
     * @param  string $phpArrayType   PHP type (e.g. 'int[][]', '\MyNamespace\MyClassName[][][]')
     * @return void
     */
    protected function _addSequenceType($arrayTypeName, $childType, $phpArrayType)
    {
        if (!in_array($phpArrayType, $this->getContext()->getTypes())) {
            $dom = $this->getContext()->toDomDocument();

            $complexType = $dom->createElement('xsd:complexType');
            $complexType->setAttribute('name', $arrayTypeName);

            $sequence = $dom->createElement('xsd:sequence');

            $element = $dom->createElement('xsd:element');
            $element->setAttribute('name',      'item');
            $element->setAttribute('type',      $childType);
            $element->setAttribute('minOccurs', 0);
            $element->setAttribute('maxOccurs', 'unbounded');
            $sequence->appendChild($element);

            $complexType->appendChild($sequence);

            $this->getContext()->getSchema()->appendChild($complexType);
            $this->getContext()->addType($phpArrayType);
        }
    }
}
