<?php

/**
 * @file
 * Generates interface EthMethods.
 *
 * Generating from resources/ethjs-schema.json -> objects.
 *
 * @ingroup generators
 */

require_once (__DIR__ . "/generator-commons.php");

use gossi\codegen\generator\CodeGenerator;
use gossi\codegen\model\PhpMethod;
use gossi\codegen\model\PhpClass;
use gossi\codegen\model\PhpParameter;
use gossi\codegen\model\PhpProperty;
use Ethereum\EthDataTypePrimitive;
use Ethereum\EthDataType;



/**
 * @var string TARGET_PATH Generator destination.
 */
define('TARGET_PATH', __DIR__ . '/../src');

/**
 * @var string TARGET_PATH Generator destination.
 */
$scriptName  = 'scripts/'. basename(__FILE__);

echo "### GENERATING COMPLEX DATA TYPE CLASSES ###\n";
//echo "# File generated " . $conf['destination'] . "\n";
echo "#############################################\n";


$file_header = <<<EOF
<?php
/**
 * @file
 * This is a file generated by $scriptName.
 * 
 * DO NOT EDIT THIS FILE.
 * 
 * @ingroup generated
  * @ingroup dataTypesComplex
 */

EOF;

$schema = getSchema();

$limit = 100;
foreach ($schema['objects'] as $obj_name => $params) {

    $limit--;
    if ($limit === 0) break;

    /** @var $useStatements - array collects data types. */
    $useStatements = [];

    $phpClass = new PhpClass();


    printMe("\n\n# $obj_name");

    // Preparing params.
    $required = $params['__required'];
    unset($params['__required']);

    /**
     * @var $param  ['params'   => ['name'=> Type, 'name'=> Type ...],
     *               'required' => ['name', 'name' ...] ]
     */
    $params = reorderParams(['params' => $params, 'required' => $required]);

    // Prepare class properties.

    $phpClass->setQualifiedName('\\Ethereum\\' . $obj_name)
        ->setParentClassName('EthDataType')
        ->setDescription(array(
            'Ethereum data type ' . $obj_name .'.',
            '',
            "Generated by $scriptName based on resources/ethjs-schema.json.",
        ))
        ->setProperties(makeProperties($params))
        ->setUseStatements($useStatements)
    ;

    $methods = [];
    // Add constructor.
    $methods[] = PhpMethod::create('__construct')
        ->setParameters(makeConstructorParams($params))
        ->setBody(makeConstructorContent($params));

    // Add Method getTypeArray().
    $methods[] = PhpMethod::create('getTypeArray')
        ->setDescription('Returns a name => type array.')
        ->setBody(makeTypeArrayBody($params))
        ->setStatic(true)
    ;

    // Add Method toArray().
    $methods[] = PhpMethod::create('toArray')
        ->setDescription('Returns array with values.')
        ->setBody(makeToArrayBody($params));


    // Add setter functions.
    $phpClass->setMethods($methods);
    // $phpClass->setMethods(array_merge($methods, makeSetFunctions($params)));

    $generator = new CodeGenerator([
        'generateScalarTypeHints' => TRUE,
        'generateReturnTypeHints' => TRUE,
        'enableSorting' => FALSE,
    ]);
    $phpClassText = $generator->generate($phpClass);

    #print $file_header . $phpClassText;

    $destination_path = TARGET_PATH . '/' . ucfirst($obj_name) . '.php';
    echo "generated file: $destination_path";
    copy($destination_path, $destination_path . '.bak');
    file_put_contents($destination_path , $file_header . $phpClassText);
}




/**
 * Reorder parameters.
 *
 * Prioritizing required params over unrequired ones.
 *
 * @param array $input Parameter array.
 *
 * @return array Parameter array.
 */
function reorderParams(Array $input)
{
    $params = $input;
    $params['params'] = [];
    // Required params first.
    foreach ($input['params'] as $name => $type) {
        if (in_array($name, $input['required'])) {
            $params['params'][$name] = $type;
        }
    }
    // ... then non required params.
    foreach ($input['params'] as $name => $type) {
        if (!in_array($name, $input['required'])) {
            $params['params'][$name] = $type;
        }
    }

    return $params;
}


/**
 * Create constructor parameters.
 *
 * @param array $input -
 *                     ['params' => ['name'=> Type, 'name'=> Type ...],
 *                     'required' => ['name', 'name' ...] ]
 * @return array
 *      Array of PhpParameter.
 */
function makeConstructorParams(Array &$input)
{
    $params = [];
    // Required params first.
    foreach ($input['params'] as $name => $type) {
        $description = null;
        $optionalValue = false;
        if (!is_array($type)) {
            $type = EthDataTypePrimitive::typeMap($type);
            if (!in_array($name, $input['required'])) {
                $optionalValue = true;
            }
        } else {
            if (!in_array($name, $input['required'])) {
              $optionalValue = true;
            }
            $subtype = EthDataTypePrimitive::typeMap($type[0]) ? EthDataTypePrimitive::typeMap($type[0]) : $type[0];
            $type = 'array';
            $description = 'Array of ' . $subtype;
        }


        $tmp = new PhpParameter($name);
        $tmp->setType($type);
        if ($description) {
            $tmp->setTypeDescription($description);
        }
        if ($optionalValue) {
            $tmp->setValue(NULL);
        }
        $params[] = $tmp;
    }
    return $params;
}


/**
 * Create properties.
 *
 * @param array $input -
 *                     ['params' => ['name'=> Type, 'name'=> Type ...],
 *                     'required' => ['name', 'name' ...] ]
 * @return array
 */
function makeProperties(Array $input)
{
    $properties = [];
    // Required params first.
    foreach ($input['params'] as $name => $type) {
         $p = new PhpProperty($name);
         $p->setDescription("Value for '$name'.");
         $properties[] = $p;
    }

    return $properties;
}


/**
 * Create constructor content.
 *
 * @param array $input -
 *                     ['params' => ['name'=> Type, 'name'=> Type ...],
 *                     'required' => ['name', 'name' ...] ]
 * @return bool|string
 */
function makeConstructorContent(Array $input)
{
    $properties = '';
    // Required params first.
    foreach ($input['params'] as $name => $type) {
        $properties .= '$this->' . $name . " = $$name;  \n";
    }

    return substr($properties, 0, -2);
}


/**
 * Create set_<PROPERTY> functions content.
 *
 * @param array $input
 *    ['params' => ['name'=> Type, 'name'=> Type ...],
 *    'required' => ['name', 'name' ...] ]
 * @return array
 * @throws Exception
 */
function makeSetFunctions(array $input)
{

    $functions = [];
    // Required params first.
    foreach ($input['params'] as $name => $type) {

        // Property.
        $prop = new PhpParameter('value');
        $prop->setType(EthDataType::getTypeClass($type, true));

        // Method body.
        $body  = 'if (is_object($value) && is_a($value, \'' . EthDataType::getTypeClass($type, true) . "')) {\n";
        $body .= "\t" . '$this->' . $name . ' = $value;' . "\n";
        $body .= '}' . "\n";
        $body .= 'else {' . "\n";
        $body .= "\t" . '$this->' . $name . ' = new ' . EthDataType::getTypeClass($type, true) . '($value);' . "\n";
        $body .= '}' . "\n";

        // Method.
        $tmp = new PhpMethod('set' . ucfirst($name));
        $tmp->addParameter($prop)
            ->setDescription("Setter for '$name'.")
            ->setBody($body)
        ;

        $functions[] = $tmp;



    }

    return $functions;
}


/**
 * Create Constructor from array.
 *
 * @param array $input
 *    ['params' => ['name'=> Type, 'name'=> Type ...],
 *    'required' => ['name', 'name' ...] ]
 * @return string
 */
function makeTypeArrayBody(Array $input)
{
    $data[] = "return array( ";
    foreach ($input['params'] as $name => $type) {
        if (is_array($type)) {
            $data[] = "\t'" . $name . "' => '" . EthDataTypePrimitive::typeMap($type[0]) . "',";
        } else {
            $data[] = "\t'" . $name . "' => '" . EthDataTypePrimitive::typeMap($type) . "',";
        }
    }
    $data[] = ");";
    return implode("\n", $data);
}


/**
 * Create return array.
 *
 * @param array $input -
 *                     ['params' => ['name'=> Type, 'name'=> Type ...],
 *                     'required' => ['name', 'name' ...] ]
 * @return string
 */
function makeToArrayBody(Array $input)
{
    $return = '$return = [];' . "\n";
    // Required params first.
    foreach ($input['params'] as $name => $type) {

        if (is_array($type)) {
            $return .= '(!is_null($this->'
                . $name
                . ')) ? $return['
                . "'$name'"
                . '] = EthereumStatic::valueArray($this->'
                . $name
                . ", '"
                . EthDataType::getTypeClass($type)
                . "') : array(); \n";
        } else {
            $return .= '(!is_null($this->'
                . $name
                . ')) ? $return['
                . "'$name'"
                . '] = $this->'
                . $name
                . "->hexVal() : NULL; \n";
        }

    }

    $return .= 'return $return;';

    return $return;
}
